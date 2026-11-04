<?php

use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Product;
use Pharaonic\Laravel\Follows\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Follows\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

test('deleting a followable deletes the follows it received', function () {
    $user = User::create();
    [$deleted, $kept] = [Post::create(), Post::create()];
    $user->follow($deleted);
    Bot::create()->follow($deleted);
    $user->follow($kept);

    $deleted->delete();

    $this->assertSame(0, Follow::whereFollowable($deleted)->count());
    $this->assertTrue($user->isFollowing($kept));
    $this->assertSame(1, Follow::query()->count());
});

test('deleting a follower deletes the follows it gave', function () {
    $bot = Bot::create();
    $other = Bot::create();
    $post = Post::create();
    $bot->follow($post);
    $bot->follow(Product::create());
    $other->follow($post);

    $bot->delete();

    $this->assertSame(0, Follow::whereFollower($bot)->count());
    $this->assertTrue($post->followedBy($other));
});

test('deleting a model with both traits deletes both directions', function () {
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
});

test('a soft deleted followable keeps its follows until force deleted', function () {
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
});

test('force deleting a followable that was never soft deleted deletes its follows', function () {
    $post = SoftDeletablePost::create();
    User::create()->follow($post);

    $post->forceDelete();

    $this->assertSame(0, Follow::query()->count());
});

test('a soft deleted follower keeps its follows until force deleted', function () {
    $member = SoftDeletableMember::create();
    $post = Post::create();
    $member->follow($post);

    $member->delete();
    $this->assertSame(1, Follow::query()->count());

    $member->restore();
    $this->assertTrue($member->isFollowing($post));

    $member->forceDelete();
    $this->assertSame(0, Follow::query()->count());
});

test('cleanup can be turned off', function () {
    $this->app['config']->set('pharaonic.follows.cleanup_on_delete', false);

    $user = User::create();
    $post = Post::create();
    $user->follow($post);
    $post->delete();
    $user->delete();

    $this->assertSame(1, Follow::query()->count());
});

test('query builder deletes skip model events and cleanup', function () {
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
});
