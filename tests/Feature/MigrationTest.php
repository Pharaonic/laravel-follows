<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\TestCase;
use RuntimeException;

class MigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_04_000001_create_pharaonic_follows_table';

    public function test_migrate_creates_the_follows_table(): void
    {
        $this->assertTrue(Schema::hasTable('follows'));
        $this->assertTrue(Schema::hasColumns('follows', [
            'id', 'follower_type', 'follower_id', 'followable_type', 'followable_id', 'created_at', 'updated_at',
        ]));
        $this->assertSame(1, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    public function test_the_unique_index_refuses_a_second_row_for_a_pair(): void
    {
        $row = ['follower_type' => 'user', 'follower_id' => '1', 'followable_type' => 'post', 'followable_id' => '1'];
        DB::table('follows')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('follows')->insert($row);
    }

    public function test_the_unique_index_allows_the_same_ids_with_other_types(): void
    {
        DB::table('follows')->insert(['follower_type' => 'user', 'follower_id' => '1', 'followable_type' => 'post', 'followable_id' => '1']);
        DB::table('follows')->insert(['follower_type' => 'user', 'follower_id' => '1', 'followable_type' => 'comment', 'followable_id' => '1']);
        DB::table('follows')->insert(['follower_type' => 'bot', 'follower_id' => '1', 'followable_type' => 'post', 'followable_id' => '1']);

        $this->assertSame(3, DB::table('follows')->count());
    }

    public function test_rollback_drops_the_table_and_migrate_creates_it_again(): void
    {
        $this->artisanCall('migrate:rollback');
        $this->assertFalse(Schema::hasTable('follows'));

        $this->artisanCall('migrate');
        $this->assertTrue(Schema::hasTable('follows'));
        $this->assertTrue(User::create()->follow(Post::create()));
    }

    public function test_the_configured_table_name_is_used_by_the_migration_and_the_model(): void
    {
        $this->artisanCall('migrate:rollback');
        $this->app['config']->set('pharaonic.follows.table', 'post_follows');
        $this->artisanCall('migrate');

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
    }

    public function test_an_existing_follows_table_stops_the_migration_with_an_explanation(): void
    {
        $this->artisanCall('migrate:rollback');

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
            $this->artisanCall('migrate');
            $this->fail('The migration ran over an existing table.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('The [follows] table already exists', $exception->getMessage());
            $this->assertStringContainsString('Set pharaonic.follows.table to another name', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCall(string $command, array $parameters = []): void
    {
        $this->assertSame(0, $this->app->make(Kernel::class)->call($command, $parameters));
    }
}
