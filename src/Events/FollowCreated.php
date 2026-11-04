<?php

namespace Pharaonic\Laravel\Follows\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Follows\Models\Follow;

/**
 * Dispatched once for each follow this package inserts, right after the insert.
 */
final class FollowCreated
{
    public function __construct(
        public Follow $follow,
        public Model $follower,
        public Model $followable,
    ) {}
}
