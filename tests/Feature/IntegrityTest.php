<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

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
use Pharaonic\Laravel\Follows\Tests\TestCase;

class IntegrityTest extends TestCase
{
    public function test_a_follow_written_by_another_connection_is_reported_as_already_followed(): void
    {
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
    }

    public function test_a_violation_of_another_unique_index_is_not_swallowed(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            $table->unique(['follower_type', 'follower_id'], 'follows_one_per_follower');
        });

        $user = User::create();
        $this->assertTrue($user->follow(Post::create()));

        $this->expectException(QueryException::class);

        $user->follow(Post::create());
    }

    public function test_other_database_errors_are_not_swallowed(): void
    {
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
    }

    public function test_an_already_followed_pair_leaves_the_callers_transaction_usable(): void
    {
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
    }

    public function test_a_rolled_back_transaction_rolls_the_follow_back(): void
    {
        $user = User::create();
        $post = Post::create();

        DB::beginTransaction();
        $this->assertTrue($user->follow($post));
        DB::rollBack();

        $this->assertFalse($user->isFollowing($post));
    }

    public function test_a_model_event_can_cancel_the_insert(): void
    {
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
    }

    public function test_a_deadlock_outside_a_transaction_is_retried(): void
    {
        $attempts = $this->deadlockOnCreating(1);

        Event::fake([FollowCreated::class]);
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->follow($post));
        $this->assertSame(2, $attempts->count);
        $this->assertSame(1, Follow::query()->count());
        Event::assertDispatchedTimes(FollowCreated::class, 1);
    }

    public function test_a_deadlock_that_keeps_happening_is_thrown(): void
    {
        $attempts = $this->deadlockOnCreating(PHP_INT_MAX);
        $user = User::create();
        $post = Post::create();

        try {
            $user->follow($post);
            $this->fail('The deadlock was swallowed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Deadlock found', $exception->getMessage());
        }

        $this->assertSame(3, $attempts->count);
        $this->assertSame(0, Follow::query()->count());
    }

    public function test_a_deadlock_inside_a_transaction_is_left_to_the_caller(): void
    {
        $attempts = $this->deadlockOnCreating(1);
        $user = User::create();
        $post = Post::create();

        DB::beginTransaction();

        try {
            $user->follow($post);
            $this->fail('The deadlock was retried inside the caller\'s transaction.');
        } catch (\PDOException $exception) {
            // Laravel 11+ rethrows it as DeadlockException, so the caller's DB::transaction() retries.
            $this->assertStringContainsString('Deadlock found', $exception->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame(1, $attempts->count);
    }

    /**
     * Make the first $times inserts of a follow fail as MySQL's deadlock victim.
     */
    private function deadlockOnCreating(int $times): \stdClass
    {
        $attempts = new \stdClass;
        $attempts->count = 0;

        Follow::creating(function () use ($attempts, $times) {
            if (++$attempts->count > $times) {
                return;
            }

            $message = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';
            $pdo = new \PDOException($message);
            $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

            throw new QueryException('insert into `follows` ...', [], $pdo);
        });

        return $attempts;
    }
}
