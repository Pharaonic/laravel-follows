<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Product;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\Fixtures\UuidModel;

test('follow returns true only when it creates the follow', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->follow($post));
    $this->assertFalse($user->follow($post));
    $this->assertFalse($user->follow($post));
    $this->assertSame(1, Follow::query()->count());
});

test('unfollow returns true only when it removes the follow', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->unfollow($post));

    $user->follow($post);

    $this->assertTrue($user->unfollow($post));
    $this->assertFalse($user->unfollow($post));
    $this->assertSame(0, Follow::query()->count());
});

test('is following and followed by report the current state', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->isFollowing($post));
    $this->assertFalse($post->followedBy($user));

    $user->follow($post);

    $this->assertTrue($user->isFollowing($post));
    $this->assertTrue($post->followedBy($user));
});

test('follow by and unfollow by return whether they changed anything', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($post->followBy($user));
    $this->assertFalse($post->followBy($user));
    $this->assertTrue($post->unfollowBy($user));
    $this->assertFalse($post->unfollowBy($user));
});

test('both sides work on the same row', function () {
    $user = User::create();
    $post = Post::create();

    $user->follow($post);
    $this->assertTrue($post->followedBy($user));
    $this->assertFalse($post->followBy($user));

    $this->assertTrue($post->unfollowBy($user));
    $this->assertFalse($user->isFollowing($post));

    $this->assertTrue($post->followBy($user));
    $this->assertTrue($user->isFollowing($post));
    $this->assertTrue($user->unfollow($post));
    $this->assertSame(0, Follow::query()->count());
});

test('the legacy un follow by spelling calls unfollow by', function () {
    $user = User::create();
    $post = Post::create();
    $post->followBy($user);

    // PHP method names are case-insensitive.
    $this->assertTrue($post->unFollowBy($user));
});

test('toggle follow returns the new state', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->toggleFollow($post));
    $this->assertSame(1, Follow::query()->count());

    $this->assertFalse($user->toggleFollow($post));
    $this->assertSame(0, Follow::query()->count());

    $this->assertTrue($user->toggleFollow($post));
    $this->assertTrue($user->isFollowing($post));
    $this->assertSame(1, Follow::query()->count());
});

test('a follower can follow any model', function () {
    $user = User::create();
    $other = User::create();

    $this->assertTrue($user->follow(Post::create()));
    $this->assertTrue($user->follow(Comment::create()));
    $this->assertTrue($user->follow(Product::create()));
    $this->assertTrue($user->follow($other));
    $this->assertTrue($user->follow($user));

    $this->assertSame(5, $user->followings()->count());
    $this->assertTrue($other->followedBy($user));
    $this->assertTrue($user->followedBy($user));
});

test('a post and a comment with the same id are different targets', function () {
    $user = User::create();
    $post = Post::create();
    $comment = Comment::create();
    $this->assertSame($post->getKey(), $comment->getKey());

    $user->follow($post);

    $this->assertTrue($user->isFollowing($post));
    $this->assertFalse($user->isFollowing($comment));
    $this->assertTrue($user->follow($comment));
});

test('many followers can follow many targets', function () {
    $alice = User::create();
    $bob = User::create();
    $first = Post::create();
    $second = Post::create();

    $this->assertTrue($alice->follow($first));
    $this->assertTrue($bob->follow($first));
    $this->assertTrue($alice->follow($second));

    $this->assertSame(2, $first->followers()->count());
    $this->assertSame(1, $second->followers()->count());
    $this->assertSame(2, $alice->followings()->count());
    $this->assertSame(1, $bob->followings()->count());
});

test('the follower does not have to be a user or signed in', function () {
    $bot = Bot::create();
    $post = Post::create();

    $this->assertFalse(Auth::check());
    $this->assertTrue($bot->follow($post));
    $this->assertTrue($post->followedBy($bot));
    $this->assertInstanceOf(Bot::class, $post->followers()->firstOrFail()->follower);
});

test('writes reject an unsaved follower without creating a row', function () {
    $post = Post::create();

    foreach (['follow', 'unfollow', 'toggleFollow'] as $method) {
        try {
            (new User)->{$method}($post);
            $this->fail("{$method}() accepted an unsaved follower.");
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('The follower ['.User::class.'] is not saved', $exception->getMessage());
        }
    }

    $this->expectException(InvalidArgumentException::class);

    try {
        $post->followBy(new User);
    } finally {
        $this->assertSame(0, Follow::query()->count());
    }
});

test('writes reject an unsaved followable without creating a row', function () {
    $user = User::create();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The followable ['.Post::class.'] is not saved');

    try {
        $user->follow(new Post);
    } finally {
        $this->assertSame(0, Follow::query()->count());
    }
});

test('reads treat an unsaved model as not followed', function () {
    $user = User::create();
    $post = Post::create();
    $user->follow($post);

    $this->assertFalse($user->isFollowing(new Post));
    $this->assertFalse($post->followedBy(new User));
    $this->assertSame(0, Post::whereFollowedBy(new User)->count());
});

test('a key longer than the column is rejected', function () {
    $target = new UuidModel;
    $target->exists = true;
    $target->id = str_repeat('a', 65);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('is 65 characters long; the follows table stores keys of up to 64 characters');

    try {
        User::create()->follow($target);
    } finally {
        $this->assertSame(0, Follow::query()->count());
    }
});

test('a morph type longer than the column is rejected', function () {
    Relation::morphMap([str_repeat('p', 192) => Post::class]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Register a shorter alias with Relation::morphMap()');

    User::create()->follow(Post::create());
});
