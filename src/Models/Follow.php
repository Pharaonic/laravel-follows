<?php

namespace Pharaonic\Laravel\Follows\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Follows\Support\FollowManager;

/**
 * A follow given by a follower model to a followable model.
 *
 * @property int $id
 * @property string $follower_type
 * @property string $follower_id
 * @property string $followable_type
 * @property string $followable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Follow extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'follower_type',
        'follower_id',
        'followable_type',
        'followable_id',
    ];

    /**
     * The table from the pharaonic.follows.table setting, unless a subclass sets $table.
     *
     * @return string
     */
    public function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        $table = Config::get('pharaonic.follows.table', 'follows');

        return is_string($table) && $table !== '' ? $table : 'follows';
    }

    /**
     * The model that gave the follow.
     *
     * @return MorphTo<Model, $this>
     */
    public function follower(): MorphTo
    {
        return $this->morphTo('follower', 'follower_type', 'follower_id');
    }

    /**
     * The model that received the follow.
     *
     * @return MorphTo<Model, $this>
     */
    public function followable(): MorphTo
    {
        return $this->morphTo('followable', 'followable_type', 'followable_id');
    }

    /**
     * Only the follows given by the follower.
     *
     * @param  Builder<Follow>  $query
     */
    public function scopeWhereFollower(Builder $query, Model $follower): void
    {
        FollowManager::constrain($query, 'follower', $follower);
    }

    /**
     * Only the follows received by the followable.
     *
     * @param  Builder<Follow>  $query
     */
    public function scopeWhereFollowable(Builder $query, Model $followable): void
    {
        FollowManager::constrain($query, 'followable', $followable);
    }
}
