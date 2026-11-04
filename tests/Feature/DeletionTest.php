<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Product;
use Pharaonic\Laravel\Follows\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Follows\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\TestCase;

class DeletionTest extends TestCase
{
    public function test_deleting_a_followable_deletes_the_follows_it_received(): void
    {
        $user = User::create();
        [$deleted, $kept] = [Post::create(), Post::create()];
        $user->follow($deleted);
        Bot::create()->follow($deleted);
        $user->follow($kept);

        $deleted->delete();

        $this->assertSame(0, Follow::whereFollowable($deleted)->count());
        $this->assertTrue($user->isFollowing($kept));
        $this->assertSame(1, Follow::query()->count());
    }

    public function test_deleting_a_follower_deletes_the_follows_it_gave(): void
    {
        $bot = Bot::create();
        $other = Bot::create();
        $post = Post::create();
        $bot->follow($post);
        $bot->follow(Product::create());
        $other->follow($post);

        $bot->delete();

        $this->assertSame(0, Follow::whereFollower($bot)->count());
        $this->assertTrue($post->followedBy($other));
    }

    public function test_deleting_a_model_with_both_traits_deletes_both_directions(): void
    {
        $alice = User::create();
        $bob = User::create();
        $post = Post::create();

        $alice->follow($bob);
        $bob->follow($alice);
        $alice->follow($alice);
        $alice->follow($post);
        $bob->follow($post);

        $alice->delete();

        $this->assertSame(1, Follow::query()->count());
        $this->assertTrue($post->followedBy($bob));
    }

    public function test_a_soft_deleted_followable_keeps_its_follows_until_force_deleted(): void
    {
        $user = User::create();
        $post = SoftDeletablePost::create();
        $user->follow($post);

        $post->delete();
        $this->assertSame(1, Follow::query()->count());

        $post->restore();
        $this->assertTrue($post->followedBy($user));

        $post->delete();
        $post->forceDelete();
        $this->assertSame(0, Follow::query()->count());
    }

    public function test_force_deleting_a_followable_that_was_never_soft_deleted_deletes_its_follows(): void
    {
        $post = SoftDeletablePost::create();
        User::create()->follow($post);

        $post->forceDelete();

        $this->assertSame(0, Follow::query()->count());
    }

    public function test_a_soft_deleted_follower_keeps_its_follows_until_force_deleted(): void
    {
        $member = SoftDeletableMember::create();
        $post = Post::create();
        $member->follow($post);

        $member->delete();
        $this->assertSame(1, Follow::query()->count());

        $member->restore();
        $this->assertTrue($member->isFollowing($post));

        $member->forceDelete();
        $this->assertSame(0, Follow::query()->count());
    }

    public function test_cleanup_can_be_turned_off(): void
    {
        $this->app['config']->set('pharaonic.follows.cleanup_on_delete', false);

        $user = User::create();
        $post = Post::create();
        $user->follow($post);
        $post->delete();
        $user->delete();

        $this->assertSame(1, Follow::query()->count());
    }

    public function test_query_builder_deletes_skip_model_events_and_cleanup(): void
    {
        $post = Post::create();
        User::create()->follow($post);

        Post::query()->whereKey($post->getKey())->delete();

        $this->assertSame(1, Follow::query()->count());

        // The documented manual cleanup.
        Follow::query()->where('followable_type', $post->getMorphClass())
            ->whereNotIn('followable_id', Post::query()->pluck('id')->map(function ($value) {
                return (string) $value;
            }))
            ->delete();

        $this->assertSame(0, Follow::query()->count());
    }
}
