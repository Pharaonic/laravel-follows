<?php

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Product;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

test('without a morph map the class names are stored', function () {
    User::create()->follow(Post::create());

    $follow = Follow::query()->firstOrFail();

    $this->assertSame(User::class, $follow->follower_type);
    $this->assertSame(Post::class, $follow->followable_type);
});

test('morph map aliases are stored and resolved', function () {
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
});

test('an enforced morph map refuses an unmapped model before writing', function () {
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
});
