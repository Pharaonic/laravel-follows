<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\TestCase;

class RelationshipsTest extends TestCase
{
    public function test_followings_and_followers_are_morph_many_relations(): void
    {
        $user = User::create();

        $this->assertInstanceOf(MorphMany::class, $user->followings());
        $this->assertSame('follower_type', $user->followings()->getMorphType());
        $this->assertSame('follows.follower_id', $user->followings()->getQualifiedForeignKeyName());

        $this->assertInstanceOf(MorphMany::class, $user->followers());
        $this->assertSame('followable_type', $user->followers()->getMorphType());
        $this->assertSame('follows.followable_id', $user->followers()->getQualifiedForeignKeyName());
    }

    public function test_a_model_with_both_traits_keeps_given_and_received_follows_apart(): void
    {
        $alice = User::create();
        $bob = User::create();

        $alice->follow($bob);

        $this->assertSame(1, $alice->followings()->count());
        $this->assertSame(0, $alice->followers()->count());
        $this->assertSame(0, $bob->followings()->count());
        $this->assertSame(1, $bob->followers()->count());
        $this->assertTrue($bob->followedBy($alice));
        $this->assertFalse($alice->followedBy($bob));
    }

    public function test_the_follow_resolves_both_ends_through_morph_to(): void
    {
        $bot = Bot::create();
        $comment = Comment::create();
        $bot->follow($comment);

        $follow = Follow::query()->firstOrFail();

        $this->assertInstanceOf(MorphTo::class, $follow->follower());
        $this->assertInstanceOf(MorphTo::class, $follow->followable());
        $this->assertTrue($follow->follower->is($bot));
        $this->assertTrue($follow->followable->is($comment));
    }

    public function test_follows_count_runs_a_count_query(): void
    {
        $post = Post::create();
        User::create()->follow($post);
        User::create()->follow($post);

        DB::enableQueryLog();
        $count = $post->followers()->count();
        $queries = DB::getQueryLog();

        $this->assertSame(2, $count);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(*)', $queries[0]['query']);
        $this->assertFalse($post->relationLoaded('followers'));
    }

    public function test_with_count_and_load_count_count_the_follows(): void
    {
        [$first, $second] = [Post::create(), Post::create()];
        User::create()->follow($first);
        User::create()->follow($first);
        User::create()->follow($second);
        Post::create();

        $counts = Post::withCount('followers')->orderBy('id')->get()->pluck('followers_count')->map(function ($value) {
            return (int) $value;
        })->all();
        $this->assertSame([2, 1, 0], $counts);

        $second->loadCount('followers');
        $this->assertSame(1, (int) $second->getAttribute('followers_count'));
    }

    public function test_eager_loading_follows_and_followers_takes_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 3) as $i) {
            $post = Post::create();
            User::create()->follow($post);
            Bot::create()->follow($post);
        }

        DB::enableQueryLog();
        $posts = Post::with('followers.follower')->get();
        $names = $posts->flatMap(function (Post $post) {
            return $post->followers->map(function (Follow $follow) {
                return get_class($follow->follower);
            });
        });

        // posts, follows, users, bots.
        $this->assertCount(4, DB::getQueryLog());
        $this->assertCount(6, $names);
    }

    public function test_followings_eager_load_their_followables(): void
    {
        $user = User::create();
        $user->follow(Post::create());
        $user->follow(Comment::create());

        $follows = $user->followings()->with('followable')->latest()->get();

        $this->assertCount(2, $follows);
        $this->assertEqualsCanonicalizing(
            [Post::class, Comment::class],
            $follows->map(function (Follow $follow) {
                return get_class($follow->followable);
            })->all()
        );
    }

    public function test_where_followed_by_keeps_only_the_models_the_follower_follows(): void
    {
        $alice = User::create();
        $bob = User::create();
        [$first, $second, $third] = [Post::create(), Post::create(), Post::create()];

        $alice->follow($first);
        $alice->follow($third);
        $bob->follow($second);
        Bot::create()->follow($second);

        $this->assertSame([$first->id, $third->id], Post::whereFollowedBy($alice)->orderBy('id')->pluck('id')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame([$second->id], Post::whereFollowedBy($bob)->pluck('id')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame(0, Comment::whereFollowedBy($alice)->count());
    }

    public function test_a_feed_can_flag_what_the_viewer_followed_in_the_same_query(): void
    {
        $viewer = User::create();
        [$first, $second] = [Post::create(), Post::create()];
        $viewer->follow($second);
        User::create()->follow($first);

        $posts = Post::withCount([
            'followers',
            'followers as followed_by_viewer' => function ($query) use ($viewer) {
                $query->whereFollower($viewer);
            },
        ])->orderBy('id')->get();

        $this->assertSame([0, 1], $posts->pluck('followed_by_viewer')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame([1, 1], $posts->pluck('followers_count')->map(function ($value) {
            return (int) $value;
        })->all());
    }

    public function test_follow_scopes_filter_by_follower_and_followable(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->follow($post);
        Bot::create()->follow($post);

        $this->assertSame(1, Follow::whereFollower($user)->count());
        $this->assertSame(2, Follow::whereFollowable($post)->count());
        $this->assertSame(1, User::whereHas('followings', function ($query) use ($post) {
            $query->whereFollowable($post);
        })->count());
    }
}
