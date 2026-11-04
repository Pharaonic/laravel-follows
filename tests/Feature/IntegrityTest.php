<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Follows\Events\FollowCreated;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

test('a follow written by another connection is reported as already followed', function () {
    $this->useSharedDatabase();
    $user = User::create();
    $post = Post::create();

    // A second, independent connection to the same database, as another request would have.
    $this->app['config']->set('database.connections.other', $this->app['config']->get('database.connections.testing'));
    DB::connection('other')->table('follows')->insert([
        'follower_type' => User::class,
        'follower_id' => (string) $user->getKey(),
        'followable_type' => Post::class,
        'followable_id' => (string) $post->getKey(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);
    DB::disconnect('other');

    Event::fake([FollowCreated::class]);

    // follow() does not read first: the insert hits the unique index.
    $this->assertFalse($user->follow($post));
    $this->assertSame(1, Follow::query()->count());
    Event::assertNotDispatched(FollowCreated::class);
});

test('a violation of another unique index is not swallowed', function () {
    Schema::table('follows', function (Blueprint $table) {
        $table->unique(['follower_type', 'follower_id'], 'follows_one_per_follower');
    });

    $user = User::create();
    $this->assertTrue($user->follow(Post::create()));

    $this->expectException(QueryException::class);

    $user->follow(Post::create());
});

test('other database errors are not swallowed', function () {
    $user = User::create();
    $post = Post::create();
    Schema::drop('follows');

    foreach (['follow', 'unfollow', 'isFollowing', 'toggleFollow'] as $method) {
        try {
            $user->{$method}($post);
            $this->fail("{$method}() hid a missing table.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('follows', $exception->getMessage());
        }
    }
});

test('an already followed pair leaves the callers transaction usable', function () {
    $user = User::create();
    $post = Post::create();
    $user->follow($post);

    DB::transaction(function () use ($user, $post) {
        $this->assertFalse($user->follow($post));
        $this->assertFalse($user->toggleFollow($post));
        $this->assertTrue($user->follow($post));
        $this->assertSame(1, Post::query()->count());
    });

    $this->assertTrue($user->isFollowing($post));
});

test('a rolled back transaction rolls the follow back', function () {
    $user = User::create();
    $post = Post::create();

    DB::beginTransaction();
    $this->assertTrue($user->follow($post));
    DB::rollBack();

    $this->assertFalse($user->isFollowing($post));
});

test('a model event can cancel the insert', function () {
    Follow::creating(function (Model $follow) {
        return false;
    });

    Event::fake([FollowCreated::class]);
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->follow($post));
    $this->assertFalse($user->toggleFollow($post));
    $this->assertSame(0, Follow::query()->count());
    Event::assertNotDispatched(FollowCreated::class);
});

/**
 * Make the first $times inserts of a follow fail as MySQL's deadlock victim.
 */
function deadlockOnCreating(int $times): stdClass
{
    $attempts = new stdClass;
    $attempts->count = 0;

    Follow::creating(function () use ($attempts, $times) {
        if (++$attempts->count > $times) {
            return;
        }

        $message = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';
        $pdo = new PDOException($message);
        $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

        throw new QueryException('insert into `follows` ...', [], $pdo);
    });

    return $attempts;
}

test('a deadlock outside a transaction is retried', function () {
    $attempts = deadlockOnCreating(1);

    Event::fake([FollowCreated::class]);
    $user = User::create();
    $post = Post::create();

    expect($user->follow($post))->toBeTrue()
        ->and($attempts->count)->toBe(2)
        ->and(Follow::query()->count())->toBe(1);
    Event::assertDispatchedTimes(FollowCreated::class, 1);
});

test('a deadlock that keeps happening is thrown', function () {
    $attempts = deadlockOnCreating(PHP_INT_MAX);
    $user = User::create();
    $post = Post::create();

    expect(fn () => $user->follow($post))->toThrow(QueryException::class, 'Deadlock found')
        ->and($attempts->count)->toBe(3)
        ->and(Follow::query()->count())->toBe(0);
});

test('a deadlock inside a transaction is left to the caller', function () {
    $attempts = deadlockOnCreating(1);
    $user = User::create();
    $post = Post::create();

    DB::beginTransaction();

    try {
        // Laravel 11+ rethrows it as DeadlockException, so the caller's DB::transaction() retries.
        expect(fn () => $user->follow($post))->toThrow(PDOException::class, 'Deadlock found');
    } finally {
        DB::rollBack();
    }

    expect($attempts->count)->toBe(1);
});
