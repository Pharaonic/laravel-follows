<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

test('migrate creates the follows table', function () {
    $this->assertTrue(Schema::hasTable('follows'));
    $this->assertTrue(Schema::hasColumns('follows', [
        'id', 'follower_type', 'follower_id', 'followable_type', 'followable_id', 'created_at', 'updated_at',
    ]));
    $this->assertSame(1, DB::table('migrations')->where('migration', FOLLOWS_MIGRATION)->count());
});

test('the unique index refuses a second row for a pair', function () {
    $row = ['follower_type' => 'user', 'follower_id' => '1', 'followable_type' => 'post', 'followable_id' => '1'];
    DB::table('follows')->insert($row);

    $this->expectException(QueryException::class);

    DB::table('follows')->insert($row);
});

test('the unique index allows the same ids with other types', function () {
    DB::table('follows')->insert(['follower_type' => 'user', 'follower_id' => '1', 'followable_type' => 'post', 'followable_id' => '1']);
    DB::table('follows')->insert(['follower_type' => 'user', 'follower_id' => '1', 'followable_type' => 'comment', 'followable_id' => '1']);
    DB::table('follows')->insert(['follower_type' => 'bot', 'follower_id' => '1', 'followable_type' => 'post', 'followable_id' => '1']);

    $this->assertSame(3, DB::table('follows')->count());
});

test('rollback drops the table and migrate creates it again', function () {
    runArtisan('migrate:rollback');
    $this->assertFalse(Schema::hasTable('follows'));

    runArtisan('migrate');
    $this->assertTrue(Schema::hasTable('follows'));
    $this->assertTrue(User::create()->follow(Post::create()));
});

test('the configured table name is used by the migration and the model', function () {
    runArtisan('migrate:rollback');
    $this->app['config']->set('pharaonic.follows.table', 'post_follows');
    runArtisan('migrate');

    $this->assertTrue(Schema::hasTable('post_follows'));
    $this->assertFalse(Schema::hasTable('follows'));
    $this->assertSame('post_follows', (new Follow)->getTable());

    $user = User::create();
    $post = Post::create();
    $this->assertTrue($user->follow($post));
    $this->assertFalse($user->follow($post));
    $this->assertSame(1, DB::table('post_follows')->count());
    $this->assertSame(1, Post::whereFollowedBy($user)->count());
    $this->assertSame(1, (int) Post::withCount('followers')->firstOrFail()->getAttribute('followers_count'));
});

test('an existing follows table stops the migration with an explanation', function () {
    runArtisan('migrate:rollback');

    // The pharaonic/laravel-users table: no unique index, 255 character columns.
    Schema::create('follows', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('followable_id');
        $table->string('followable_type');
        $table->string('follower_id');
        $table->string('follower_type');
        $table->timestamps();
    });

    try {
        runArtisan('migrate');
        $this->fail('The migration ran over an existing table.');
    } catch (RuntimeException $exception) {
        $this->assertStringContainsString('The [follows] table already exists', $exception->getMessage());
        $this->assertStringContainsString('Set pharaonic.follows.table to another name', $exception->getMessage());
    }

    $this->assertSame(0, DB::table('migrations')->where('migration', FOLLOWS_MIGRATION)->count());
});
