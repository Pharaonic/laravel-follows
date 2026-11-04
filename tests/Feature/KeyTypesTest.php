<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\UlidModel;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\Follows\Tests\TestCase;

class KeyTypesTest extends TestCase
{
    public function test_integer_uuid_and_ulid_keys_share_the_table(): void
    {
        $user = User::create();
        $uuid = UuidModel::create();
        $ulid = UlidModel::create();
        $post = Post::create();

        $this->assertSame(36, strlen($uuid->getKey()));
        $this->assertSame(26, strlen($ulid->getKey()));

        $this->assertTrue($user->follow($uuid));
        $this->assertTrue($uuid->follow($post));
        $this->assertTrue($ulid->follow($uuid));
        $this->assertTrue($uuid->follow($ulid));
        $this->assertFalse($ulid->follow($uuid));

        $this->assertTrue($uuid->followedBy($user));
        $this->assertTrue($post->followedBy($uuid));
        $this->assertTrue($ulid->isFollowing($uuid));
        $this->assertSame(4, Follow::query()->count());

        $follow = Follow::whereFollower($ulid)->firstOrFail();
        $this->assertSame((string) $ulid->getKey(), $follow->follower_id);
        $this->assertTrue($follow->follower->is($ulid));
        $this->assertTrue($follow->followable->is($uuid));
    }

    public function test_integer_keys_are_stored_as_strings(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->follow($post);

        $follow = Follow::query()->firstOrFail();

        $this->assertSame((string) $user->getKey(), $follow->follower_id);
        $this->assertSame((string) $post->getKey(), $follow->followable_id);
    }

    public function test_queries_work_on_string_keyed_parents(): void
    {
        $user = User::create();
        [$first, $second] = [UuidModel::create(), UuidModel::create()];
        $ulid = UlidModel::create();

        $user->follow($first);
        $ulid->follow($first);
        $ulid->follow($second);

        $this->assertSame([$first->id], UuidModel::whereFollowedBy($user)->pluck('id')->all());
        $this->assertSame(2, UuidModel::whereFollowedBy($ulid)->count());

        $counts = UuidModel::withCount('followers')->get()->pluck('followers_count', 'id')->map(function ($value) {
            return (int) $value;
        });
        $this->assertSame(2, $counts[$first->id]);
        $this->assertSame(1, $counts[$second->id]);

        $models = UuidModel::with('followers.follower')->get()->keyBy('id');
        $this->assertCount(2, $models[$first->id]->followers);
        $this->assertCount(2, UlidModel::with('followings.followable')->firstOrFail()->followings);
    }
}
