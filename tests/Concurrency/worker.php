<?php

/*
 * One worker of ConcurrencyTest: boots the package, waits for the shared
 * start time, then follows, unfollows or toggles one pair and prints 1 or 0.
 *
 * Usage: php worker.php <follow|unfollow|toggle> <user id> <post id> <start time>
 */

use Pharaonic\Laravel\Follows\Tests\Concurrency\WorkerApplication;
use Pharaonic\Laravel\Follows\Tests\Fixtures\Post;
use Pharaonic\Laravel\Follows\Tests\Fixtures\User;

require __DIR__.'/../../vendor/autoload.php';

[, $action, $userId, $postId, $start] = $argv;

(new WorkerApplication('worker'))->createApplication();

$user = User::query()->findOrFail($userId);
$post = Post::query()->findOrFail($postId);

if ((float) $start > microtime(true)) {
    time_sleep_until((float) $start);
}

switch ($action) {
    case 'follow':
        $result = $user->follow($post);

        break;
    case 'unfollow':
        $result = $user->unfollow($post);

        break;
    default:
        $result = $user->toggleFollow($post);
}

echo $result ? '1' : '0';
