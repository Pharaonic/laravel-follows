<?php

namespace Pharaonic\Laravel\Follows\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Follows\Models\Follow;
use Pharaonic\Laravel\Follows\Support\FollowManager;

/**
 * For a model that follows other models, such as a user.
 */
trait CanFollow
{
    /**
     * Delete the follows the model gave when it is deleted (not soft deleted).
     */
    public static function bootCanFollow(): void
    {
        static::deleted(function (Model $model) {
            FollowManager::cleanUp($model, 'follower');
        });
    }

    /**
     * The follows this model gave.
     *
     * @return MorphMany<Follow, $this>
     */
    public function followings(): MorphMany
    {
        return FollowManager::relation($this, 'follower');
    }

    /**
     * Follow the model. True when this call created the follow, false when it already existed.
     */
    public function follow(Model $followable): bool
    {
        return FollowManager::follow($this, $followable);
    }

    /**
     * Remove the follow. True when this call removed it, false when there was none.
     */
    public function unfollow(Model $followable): bool
    {
        return FollowManager::unfollow($this, $followable);
    }

    /**
     * Whether this model follows the model.
     */
    public function isFollowing(Model $followable): bool
    {
        return FollowManager::has($this, $followable);
    }

    /**
     * Follow the model, or remove the follow. Returns the new state: true when followed.
     */
    public function toggleFollow(Model $followable): bool
    {
        return FollowManager::toggle($this, $followable);
    }
}
