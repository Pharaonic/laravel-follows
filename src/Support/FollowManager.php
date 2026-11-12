<?php

namespace Pharaonic\Laravel\Follows\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Pharaonic\Laravel\Follows\Events\FollowCreated;
use Pharaonic\Laravel\Follows\Events\FollowRemoved;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Relations\FollowsMorphMany;
use Stringable;

/**
 * The one place that reads and writes follows, shared by CanFollow and HasFollowers.
 *
 * @internal
 */
final class FollowManager
{
    /**
     * The longest morph type the follows table stores.
     */
    public const TYPE_LENGTH = 191;

    /**
     * The longest key the follows table stores.
     */
    public const KEY_LENGTH = 64;

    /**
     * How many times a write runs when a concurrent request keeps getting in its way.
     */
    private const ATTEMPTS = 3;

    /**
     * Follow the followable. True when this call created the follow.
     */
    public static function follow(Model $follower, Model $followable): bool
    {
        return self::insert($follower, $followable)[1];
    }

    /**
     * Remove the follow. True when this call deleted it.
     */
    public static function unfollow(Model $follower, Model $followable): bool
    {
        $follow = Follow::query()->useWritePdo()->where(self::pair($follower, $followable))->first();

        return $follow instanceof Follow && self::remove($follow, $follower, $followable);
    }

    /**
     * Follow the followable, or remove the follow when it exists. Returns the new state.
     *
     * The insert comes first, so the unique index rather than a read decides
     * which branch runs; no lock is held between the two steps.
     */
    public static function toggle(Model $follower, Model $followable): bool
    {
        [$follow, $created] = self::insert($follower, $followable);

        if ($created) {
            return true;
        }

        if ($follow === null) {
            return self::has($follower, $followable);
        }

        self::remove($follow, $follower, $followable);

        return false;
    }

    /**
     * Whether the follower follows the followable. False when either one is unsaved.
     */
    public static function has(Model $follower, Model $followable): bool
    {
        if (! self::isPersisted($follower) || ! self::isPersisted($followable)) {
            return false;
        }

        return Follow::query()->where(self::pair($follower, $followable))->exists();
    }

    /**
     * The follows given (follower) or received (followable) by the model.
     */
    public static function relation(Model $parent, string $side): MorphMany
    {
        $follow = new Follow;
        $table = $follow->getTable();

        return new FollowsMorphMany(
            $follow->newQuery(),
            $parent,
            $table.'.'.$side.'_type',
            $table.'.'.$side.'_id',
            $parent->getKeyName()
        );
    }

    /**
     * Limit a follows query to one follower or followable. An unsaved model matches nothing.
     */
    public static function constrain(Builder $query, string $side, Model $model): void
    {
        if (! self::isPersisted($model)) {
            $query->whereRaw('0 = 1');

            return;
        }

        [$type, $key] = self::endpoint($model, $side);

        $query->where($query->qualifyColumn($side.'_type'), $type);
        $query->where($query->qualifyColumn($side.'_id'), $key);
    }

    /**
     * Delete the follows of a model that is being deleted, unless it is only soft deleted.
     */
    public static function cleanUp(Model $model, string $side): void
    {
        if (! (bool) Config::get('pharaonic.follows.cleanup_on_delete', true)) {
            return;
        }

        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            return;
        }

        $key = self::key($model);

        if ($key === null) {
            return;
        }

        Follow::query()->toBase()
            ->where($side.'_type', $model->getMorphClass())
            ->where($side.'_id', $key)
            ->delete();
    }

    /**
     * Whether the database refused a row because of a unique index.
     */
    public static function isUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];
        $sqlState = isset($errorInfo[0]) && is_string($errorInfo[0]) ? $errorInfo[0] : (string) $exception->getCode();
        $driverCode = $errorInfo[1] ?? null;

        // PostgreSQL: unique_violation.
        if ($sqlState === '23505') {
            return true;
        }

        // MySQL and MariaDB: ER_DUP_ENTRY, ER_DUP_ENTRY_WITH_KEY_NAME.
        // SQL Server: duplicate key in a unique index or constraint.
        if (in_array($driverCode, [1062, 1586, 2601, 2627], true)) {
            return true;
        }

        // SQLite reports every constraint failure as code 19.
        return str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * Whether the database rolled the statement back to break a deadlock.
     */
    public static function isDeadlock(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];
        $sqlState = isset($errorInfo[0]) && is_string($errorInfo[0]) ? $errorInfo[0] : (string) $exception->getCode();
        $driverCode = $errorInfo[1] ?? null;

        // MySQL and MariaDB report ER_LOCK_DEADLOCK (1213) as 40001, like SQL
        // Server's deadlock victim; PostgreSQL uses 40P01 for deadlock_detected.
        return in_array($sqlState, ['40001', '40P01'], true) || $driverCode === 1213;
    }

    /**
     * Insert the follow, unless the pair already has one.
     *
     * @return array{0: Follow|null, 1: bool} the follow (null when a model event
     *                                        cancelled the insert), and whether this call created it
     */
    private static function insert(Model $follower, Model $followable): array
    {
        $pair = self::pair($follower, $followable);

        for ($attempt = 1; ; $attempt++) {
            $follow = new Follow($pair);
            $connection = $follow->getConnection();

            try {
                // Inside a transaction, a savepoint keeps a refused insert from
                // aborting the caller's transaction (PostgreSQL aborts it otherwise).
                $saved = $connection->transactionLevel() > 0
                    ? $connection->transaction(function () use ($follow) {
                        return $follow->save();
                    })
                    : $follow->save();
            } catch (QueryException $exception) {
                if (self::isUniqueViolation($exception)) {
                    // A locking read: inside a transaction, a plain read on MySQL
                    // (REPEATABLE READ) returns the snapshot the transaction started
                    // from and misses a follow another request committed since. The
                    // refused insert already holds that lock.
                    $existing = Follow::query()->useWritePdo()->where($pair)->sharedLock()->first();

                    if ($existing instanceof Follow) {
                        return [$existing, false];
                    }

                    // The follow was deleted between the insert and the read: insert
                    // again. When it keeps missing, another unique index refused the
                    // row, which is not ours to swallow.
                } elseif (! self::canRetry($exception, $connection)) {
                    throw $exception;
                }

                if ($attempt >= self::ATTEMPTS) {
                    throw $exception;
                }

                continue;
            }

            if (! $saved) {
                return [null, false];
            }

            Event::dispatch(new FollowCreated($follow, $follower, $followable));

            return [$follow, true];
        }
    }

    /**
     * Delete the follow. True when this call deleted the row.
     */
    private static function remove(Follow $follow, Model $follower, Model $followable): bool
    {
        $deleted = false;

        for ($attempt = 1; ; $attempt++) {
            try {
                $deleted = Follow::query()->toBase()->where($follow->getKeyName(), $follow->getKey())->delete() > 0;

                break;
            } catch (QueryException $exception) {
                if ($attempt >= self::ATTEMPTS || ! self::canRetry($exception, $follow->getConnection())) {
                    throw $exception;
                }
            }
        }

        if ($deleted) {
            $follow->exists = false;

            Event::dispatch(new FollowRemoved($follow, $follower, $followable));
        }

        return $deleted;
    }

    /**
     * Whether a failed statement can simply run again: a deadlock outside a
     * transaction. Inside one, the database rolled the whole transaction back,
     * so only the caller can retry it.
     */
    private static function canRetry(QueryException $exception, ConnectionInterface $connection): bool
    {
        return self::isDeadlock($exception) && $connection->transactionLevel() === 0;
    }

    /**
     * The four columns that identify the follow of a pair.
     *
     * @return array{follower_type: string, follower_id: string, followable_type: string, followable_id: string}
     */
    private static function pair(Model $follower, Model $followable): array
    {
        [$followerType, $followerId] = self::endpoint($follower, 'follower');
        [$followableType, $followableId] = self::endpoint($followable, 'followable');

        return [
            'follower_type' => $followerType,
            'follower_id' => $followerId,
            'followable_type' => $followableType,
            'followable_id' => $followableId,
        ];
    }

    /**
     * The morph type and key the follows table stores for the model.
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException when the model is unsaved, or its type or key does not fit
     */
    private static function endpoint(Model $model, string $side): array
    {
        if (! $model->exists) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] is not saved: save it before it follows or is followed.', $side, get_class($model)
            ));
        }

        $key = self::key($model);

        if ($key === null) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] has no usable primary key.', $side, get_class($model)
            ));
        }

        if (mb_strlen($key) > self::KEY_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The key of the %s [%s] is %d characters long; the follows table stores keys of up to %d characters.',
                $side, get_class($model), mb_strlen($key), self::KEY_LENGTH
            ));
        }

        $type = $model->getMorphClass();

        if (mb_strlen($type) > self::TYPE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The morph type of the %s [%s] is %d characters long; the follows table stores types of up to %d characters. '
                .'Register a shorter alias with Relation::morphMap().',
                $side, get_class($model), mb_strlen($type), self::TYPE_LENGTH
            ));
        }

        return [$type, $key];
    }

    /**
     * Whether the model is saved and has a usable key.
     */
    private static function isPersisted(Model $model): bool
    {
        return $model->exists && self::key($model) !== null;
    }

    /**
     * The model key as the string the follows table stores, or null when it has none.
     */
    private static function key(Model $model): ?string
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key) && ! $key instanceof Stringable) {
            return null;
        }

        $key = (string) $key;

        return $key === '' ? null : $key;
    }
}
