<?php

namespace Pharaonic\Laravel\Follows\Relations;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Support\FollowManager;

/**
 * The follows relation of a follower or followable model.
 *
 * The follows table stores ids as strings, so integer, UUID and ULID keys can
 * share it. This relation compares the parent key as a string too: PostgreSQL
 * refuses to compare a bigint or uuid column with a varchar one, and on MySQL
 * and SQLite the string comparison is what lets the unique index be used.
 *
 * @internal
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<Follow, TDeclaringModel>
 */
final class FollowsMorphMany extends MorphMany
{
    /**
     * Declared here so PHPStan infers TDeclaringModel from `new FollowsMorphMany(...)`:
     * PHPStan 2.1.0 does not read it from the inherited constructor.
     *
     * @param  Builder<Follow>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     */
    public function __construct(Builder $query, Model $parent, $type, $id, $localKey)
    {
        parent::__construct($query, $parent, $type, $id, $localKey);
    }

    /**
     * The parent key, as the string the follows table stores.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        $key = parent::getParentKey();

        return FollowManager::keyToString($key) ?? $key;
    }

    /**
     * Constrain eager loading to the parents' keys, as strings.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $keys = [];

        foreach ($this->getKeys($models, $this->localKey) as $key) {
            $key = FollowManager::keyToString($key);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $this->query->whereIn($this->foreignKey, $keys);
        $this->query->where($this->morphType, $this->morphClass);
    }

    /**
     * The subquery behind has(), whereHas() and withCount().
     *
     * @param  Builder<Follow>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return Builder<Follow>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $grammar = $query->getQuery()->getGrammar();
        $parentKey = $grammar->wrap($this->getQualifiedParentKeyName());

        $connection = $query->getQuery()->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;

        $parentKeyAsString = match ($driver) {
            // CONCAT() takes the collation of the follows column, so a connection
            // collation that differs from the table's cannot make it fail.
            'mysql', 'mariadb' => 'concat('.$parentKey.')',
            'pgsql' => 'cast('.$parentKey.' as varchar)',
            'sqlite' => 'cast('.$parentKey.' as text)',
            default => null,
        };

        if ($parentKeyAsString === null || $query->getQuery()->from == $parentQuery->getQuery()->from) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        $query->select($columns);
        $query->whereRaw($parentKeyAsString.' = '.$grammar->wrap($this->getExistenceCompareKey()));
        $query->where($query->qualifyColumn($this->getMorphType()), $this->morphClass);

        return $query;
    }
}
