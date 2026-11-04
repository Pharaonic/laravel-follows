<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;
use Pharaonic\Laravel\Follows\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * Separate PHP processes, each with its own connection, act on one pair at
 * the same moment. On SQLite the database file serializes the writes; on
 * MySQL and PostgreSQL (FOLLOWS_DB) they really run in parallel.
 */
class ConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    public function test_simultaneous_follows_create_one_row_and_one_true(): void
    {
        [$user, $post, $results] = $this->race('follow');

        $this->assertSame(1, array_sum($results));
        $this->assertSame(1, Follow::query()->count());
        $this->assertTrue($user->isFollowing($post));
    }

    public function test_simultaneous_unfollows_delete_the_row_once(): void
    {
        [$user, $post, $results] = $this->race('unfollow', true);

        $this->assertSame(1, array_sum($results));
        $this->assertSame(0, Follow::query()->count());
        $this->assertFalse($user->isFollowing($post));
    }

    public function test_simultaneous_toggles_never_duplicate_the_follow(): void
    {
        [$user, $post] = $this->race('toggle');

        $this->assertLessThanOrEqual(1, Follow::query()->count());
        $this->assertSame(Follow::query()->count() === 1, $user->isFollowing($post));
    }

    /**
     * @return array{0: User, 1: Post, 2: array<int, int>}
     */
    private function race(string $action, bool $followed = false): array
    {
        $env = $this->useSharedDatabase();
        $user = User::create();
        $post = Post::create();

        if ($followed) {
            $user->follow($post);
        }

        // Leave the workers time to boot, then let them all start together.
        $start = (string) (microtime(true) + 3);
        $processes = [];

        for ($i = 0; $i < self::WORKERS; $i++) {
            $process = new Process(
                [PHP_BINARY, __DIR__.'/../Concurrency/worker.php', $action, (string) $user->getKey(), (string) $post->getKey(), $start],
                null,
                $env,
                null,
                60
            );
            $process->start();
            $processes[] = $process;
        }

        $results = [];

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $this->assertContains($process->getOutput(), ['0', '1']);
            $results[] = (int) $process->getOutput();
        }

        return [$user, $post, $results];
    }
}
