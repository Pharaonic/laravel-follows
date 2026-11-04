<?php

namespace Pharaonic\Laravel\Follows\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Support\FollowManager;

/**
 * For a model that other models follow, such as a post.
 */
trait HasFollowers
{
    /**
     * Delete the follows the model received when it is deleted (not soft deleted).
     */
    public static function bootHasFollowers(): void
    {
        static::deleted(function (Model $model) {
            FollowManager::cleanUp($model, 'followable');
        });
    }

    /**
     * The follows this model received.
     *
     * @return MorphMany<Follow, $this>
     */
    public function followers(): MorphMany
    {
        return FollowManager::relation($this, 'followable');
    }

    /**
     * Whether the follower follows this model.
     */
    public function followedBy(Model $follower): bool
    {
        return FollowManager::has($follower, $this);
    }

    /**
     * Follow this model as the follower. True when this call created the follow.
     */
    public function followBy(Model $follower): bool
    {
        return FollowManager::follow($follower, $this);
    }

    /**
     * Remove the follower's follow. True when this call removed it.
     */
    public function unfollowBy(Model $follower): bool
    {
        return FollowManager::unfollow($follower, $this);
    }

    /**
     * Only the models the follower follows.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereFollowedBy(Builder $query, Model $follower): void
    {
        $query->whereHas('followers', function (Builder $follows) use ($follower) {
            FollowManager::constrain($follows, 'follower', $follower);
        });
    }
}
