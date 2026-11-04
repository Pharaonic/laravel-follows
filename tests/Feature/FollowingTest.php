<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Product;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\Follows\Tests\TestCase;

class FollowingTest extends TestCase
{
    public function test_follow_returns_true_only_when_it_creates_the_follow(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->follow($post));
        $this->assertFalse($user->follow($post));
        $this->assertFalse($user->follow($post));
        $this->assertSame(1, Follow::query()->count());
    }

    public function test_unfollow_returns_true_only_when_it_removes_the_follow(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertFalse($user->unfollow($post));

        $user->follow($post);

        $this->assertTrue($user->unfollow($post));
        $this->assertFalse($user->unfollow($post));
        $this->assertSame(0, Follow::query()->count());
    }

    public function test_is_following_and_followed_by_report_the_current_state(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertFalse($user->isFollowing($post));
        $this->assertFalse($post->followedBy($user));

        $user->follow($post);

        $this->assertTrue($user->isFollowing($post));
        $this->assertTrue($post->followedBy($user));
    }

    public function test_follow_by_and_unfollow_by_return_whether_they_changed_anything(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($post->followBy($user));
        $this->assertFalse($post->followBy($user));
        $this->assertTrue($post->unfollowBy($user));
        $this->assertFalse($post->unfollowBy($user));
    }

    public function test_both_sides_work_on_the_same_row(): void
    {
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
    }

    public function test_the_legacy_un_follow_by_spelling_calls_unfollow_by(): void
    {
        $user = User::create();
        $post = Post::create();
        $post->followBy($user);

        // PHP method names are case-insensitive.
        $this->assertTrue($post->unFollowBy($user));
    }

    public function test_toggle_follow_returns_the_new_state(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->toggleFollow($post));
        $this->assertSame(1, Follow::query()->count());

        $this->assertFalse($user->toggleFollow($post));
        $this->assertSame(0, Follow::query()->count());

        $this->assertTrue($user->toggleFollow($post));
        $this->assertTrue($user->isFollowing($post));
        $this->assertSame(1, Follow::query()->count());
    }

    public function test_a_follower_can_follow_any_model(): void
    {
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
    }

    public function test_a_post_and_a_comment_with_the_same_id_are_different_targets(): void
    {
        $user = User::create();
        $post = Post::create();
        $comment = Comment::create();
        $this->assertSame($post->getKey(), $comment->getKey());

        $user->follow($post);

        $this->assertTrue($user->isFollowing($post));
        $this->assertFalse($user->isFollowing($comment));
        $this->assertTrue($user->follow($comment));
    }

    public function test_many_followers_can_follow_many_targets(): void
    {
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
    }

    public function test_the_follower_does_not_have_to_be_a_user_or_signed_in(): void
    {
        $bot = Bot::create();
        $post = Post::create();

        $this->assertFalse(Auth::check());
        $this->assertTrue($bot->follow($post));
        $this->assertTrue($post->followedBy($bot));
        $this->assertInstanceOf(Bot::class, $post->followers()->firstOrFail()->follower);
    }

    public function test_writes_reject_an_unsaved_follower_without_creating_a_row(): void
    {
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
    }

    public function test_writes_reject_an_unsaved_followable_without_creating_a_row(): void
    {
        $user = User::create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The followable ['.Post::class.'] is not saved');

        try {
            $user->follow(new Post);
        } finally {
            $this->assertSame(0, Follow::query()->count());
        }
    }

    public function test_reads_treat_an_unsaved_model_as_not_followed(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->follow($post);

        $this->assertFalse($user->isFollowing(new Post));
        $this->assertFalse($post->followedBy(new User));
        $this->assertSame(0, Post::whereFollowedBy(new User)->count());
    }

    public function test_a_key_longer_than_the_column_is_rejected(): void
    {
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
    }

    public function test_a_morph_type_longer_than_the_column_is_rejected(): void
    {
        Relation::morphMap([str_repeat('p', 192) => Post::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Register a shorter alias with Relation::morphMap()');

        User::create()->follow(Post::create());
    }
}
