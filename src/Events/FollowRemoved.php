<?php

namespace Pharaonic\Laravel\Follows\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Follows\Models\Follow;

/**
 * Dispatched once for each follow this package deletes through unfollow(),
 * unfollowBy() or toggleFollow(), right after the delete. $follow is the deleted row.
 */
final class FollowRemoved
{
    public function __construct(
        public Follow $follow,
        public Model $follower,
        public Model $followable,
    ) {
    }
}
