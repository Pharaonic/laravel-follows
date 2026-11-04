<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Product;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\TestCase;

class MorphMapTest extends TestCase
{
    public function test_without_a_morph_map_the_class_names_are_stored(): void
    {
        User::create()->follow(Post::create());

        $follow = Follow::query()->firstOrFail();

        $this->assertSame(User::class, $follow->follower_type);
        $this->assertSame(Post::class, $follow->followable_type);
    }

    public function test_morph_map_aliases_are_stored_and_resolved(): void
    {
        Relation::morphMap(['user' => User::class, 'post' => Post::class]);

        $user = User::create();
        $post = Post::create();
        $user->follow($post);

        $follow = Follow::query()->firstOrFail();

        $this->assertSame('user', $follow->follower_type);
        $this->assertSame('post', $follow->followable_type);
        $this->assertTrue($follow->follower->is($user));
        $this->assertTrue($follow->followable->is($post));
        $this->assertTrue($post->followedBy($user));
        $this->assertSame(1, Post::whereFollowedBy($user)->count());
        $this->assertSame(1, (int) Post::withCount('followers')->firstOrFail()->getAttribute('followers_count'));
    }

    public function test_an_enforced_morph_map_refuses_an_unmapped_model_before_writing(): void
    {
        if (! method_exists(Relation::class, 'enforceMorphMap')) {
            $this->markTestSkipped('Relation::enforceMorphMap() needs Laravel 8.');
        }

        Relation::enforceMorphMap(['user' => User::class, 'post' => Post::class]);

        $user = User::create();
        $this->assertTrue($user->follow(Post::create()));

        try {
            $user->follow(Product::create());
            $this->fail('An unmapped model was followed.');
        } catch (ClassMorphViolationException $exception) {
            $this->assertStringContainsString(Product::class, $exception->getMessage());
        }

        $this->assertSame(1, Follow::query()->count());
    }
}
