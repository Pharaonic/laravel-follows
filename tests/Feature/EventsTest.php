<?php

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Follows\Events\FollowCreated;
use Pharaonic\Laravel\Follows\Events\FollowRemoved;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

test('follow created is dispatched once per inserted follow', function () {
    Event::fake([FollowCreated::class, FollowRemoved::class]);

    $user = User::create();
    $post = Post::create();
    $user->follow($post);
    $user->follow($post);
    $post->followBy($user);

    Event::assertDispatchedTimes(FollowCreated::class, 1);
    Event::assertDispatched(FollowCreated::class, function (FollowCreated $event) use ($user, $post) {
        return $event->follower->is($user)
            && $event->followable->is($post)
            && $event->follow->exists
            && $event->follow->is(Follow::query()->firstOrFail());
    });
    Event::assertNotDispatched(FollowRemoved::class);
});

test('follow removed is dispatched once per deleted follow', function () {
    $user = User::create();
    $post = Post::create();
    $user->follow($post);
    $id = Follow::query()->firstOrFail()->getKey();

    Event::fake([FollowCreated::class, FollowRemoved::class]);

    $user->unfollow($post);
    $user->unfollow($post);
    $post->unfollowBy($user);

    Event::assertDispatchedTimes(FollowRemoved::class, 1);
    Event::assertDispatched(FollowRemoved::class, function (FollowRemoved $event) use ($user, $post, $id) {
        return $event->follower->is($user)
            && $event->followable->is($post)
            && $event->follow->getKey() === $id
            && ! $event->follow->exists;
    });
    Event::assertNotDispatched(FollowCreated::class);
});

test('toggle follow dispatches the event of the change it made', function () {
    Event::fake([FollowCreated::class, FollowRemoved::class]);

    $user = User::create();
    $post = Post::create();

    $user->toggleFollow($post);
    Event::assertDispatchedTimes(FollowCreated::class, 1);
    Event::assertNotDispatched(FollowRemoved::class);

    $user->toggleFollow($post);
    Event::assertDispatchedTimes(FollowCreated::class, 1);
    Event::assertDispatchedTimes(FollowRemoved::class, 1);
});

test('listeners run after the write', function () {
    $counts = [];

    Event::listen(FollowCreated::class, function () use (&$counts) {
        $counts[] = Follow::query()->count();
    });
    Event::listen(FollowRemoved::class, function () use (&$counts) {
        $counts[] = Follow::query()->count();
    });

    $user = User::create();
    $post = Post::create();
    $user->follow($post);
    $user->unfollow($post);

    $this->assertSame([1, 0], $counts);
});

test('cleanup on delete dispatches no follow removed', function () {
    $post = Post::create();
    User::create()->follow($post);

    Event::fake([FollowRemoved::class]);
    $post->delete();

    Event::assertNotDispatched(FollowRemoved::class);
    $this->assertSame(0, Follow::query()->count());
});
