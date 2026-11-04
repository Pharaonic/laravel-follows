<?php

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

test('followings and followers are morph many relations', function () {
    $user = User::create();

    $this->assertInstanceOf(MorphMany::class, $user->followings());
    $this->assertSame('follower_type', $user->followings()->getMorphType());
    $this->assertSame('follows.follower_id', $user->followings()->getQualifiedForeignKeyName());

    $this->assertInstanceOf(MorphMany::class, $user->followers());
    $this->assertSame('followable_type', $user->followers()->getMorphType());
    $this->assertSame('follows.followable_id', $user->followers()->getQualifiedForeignKeyName());
});

test('a model with both traits keeps given and received follows apart', function () {
    $alice = User::create();
    $bob = User::create();

    $alice->follow($bob);

    $this->assertSame(1, $alice->followings()->count());
    $this->assertSame(0, $alice->followers()->count());
    $this->assertSame(0, $bob->followings()->count());
    $this->assertSame(1, $bob->followers()->count());
    $this->assertTrue($bob->followedBy($alice));
    $this->assertFalse($alice->followedBy($bob));
});

test('the follow resolves both ends through morph to', function () {
    $bot = Bot::create();
    $comment = Comment::create();
    $bot->follow($comment);

    $follow = Follow::query()->firstOrFail();

    $this->assertInstanceOf(MorphTo::class, $follow->follower());
    $this->assertInstanceOf(MorphTo::class, $follow->followable());
    $this->assertTrue($follow->follower->is($bot));
    $this->assertTrue($follow->followable->is($comment));
});

test('followers count runs a count query', function () {
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
});

test('with count and load count count the follows', function () {
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
});

test('eager loading follows and followers takes a fixed number of queries', function () {
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
});

test('followings eager load their followables', function () {
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
});

test('where followed by keeps only the models the follower follows', function () {
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
});

test('a feed can flag what the viewer followed in the same query', function () {
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
});

test('follow scopes filter by follower and followable', function () {
    $user = User::create();
    $post = Post::create();
    $user->follow($post);
    Bot::create()->follow($post);

    $this->assertSame(1, Follow::whereFollower($user)->count());
    $this->assertSame(2, Follow::whereFollowable($post)->count());
    $this->assertSame(1, User::whereHas('followings', function ($query) use ($post) {
        $query->whereFollowable($post);
    })->count());
});
