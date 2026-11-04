## API Reference

### `CanFollow`

`Pharaonic\Laravel\Follows\Concerns\CanFollow`, for the model that follows.

| Method | Returns | Description |
| --- | --- | --- |
| `follow(Model $followable)` | `bool` | Follows the model. True when this call created the follow, false when it already existed. |
| `unfollow(Model $followable)` | `bool` | Removes the follow. True when this call removed it, false when there was none. |
| `isFollowing(Model $followable)` | `bool` | Whether this model follows the model. False when either is unsaved. |
| `toggleFollow(Model $followable)` | `bool` | Follows the model, or removes the follow. Returns the new state: true when followed. |
| `followings()` | `MorphMany` | The follows this model gave. |

### `HasFollowers`

`Pharaonic\Laravel\Follows\Concerns\HasFollowers`, for the model that is followed.

| Method | Returns | Description |
| --- | --- | --- |
| `followers()` | `MorphMany` | The follows this model received. |
| `followBy(Model $follower)` | `bool` | Same as `$follower->follow($this)`, for any follower model. |
| `unfollowBy(Model $follower)` | `bool` | Same as `$follower->unfollow($this)`. |
| `followedBy(Model $follower)` | `bool` | Same as `$follower->isFollowing($this)`. |
| `whereFollowedBy(Model $follower)` | scope | Keeps the models the follower follows: `Post::whereFollowedBy($user)`. |

### `Follow`

`Pharaonic\Laravel\Follows\Models\Follow`.

| Member | Type | Description |
| --- | --- | --- |
| `follower()` | `MorphTo` | The model that gave the follow. |
| `followable()` | `MorphTo` | The model that received the follow. |
| `whereFollower(Model $follower)` | scope | Follows given by the model. Matches nothing for an unsaved model. |
| `whereFollowable(Model $followable)` | scope | Follows received by the model. Matches nothing for an unsaved model. |
| `id` | `int` | Primary key. |
| `follower_type`, `followable_type` | `string` | `getMorphClass()` of each end, up to 191 characters. |
| `follower_id`, `followable_id` | `string` | The key of each end as a string, up to 64 characters. |
| `created_at`, `updated_at` | `Carbon` | Timestamps. |

### Events

| Event | Properties | Dispatched |
| --- | --- | --- |
| `Pharaonic\Laravel\Follows\Events\FollowCreated` | `Follow $follow`, `Model $follower`, `Model $followable` | After a call inserted a follow. |
| `Pharaonic\Laravel\Follows\Events\FollowRemoved` | `Follow $follow`, `Model $follower`, `Model $followable` | After a call deleted a follow (not on cleanup). |

### Exceptions

| Exception | Thrown by | When |
| --- | --- | --- |
| `InvalidArgumentException` | `follow()`, `unfollow()`, `toggleFollow()`, `followBy()`, `unfollowBy()` | A model is unsaved or has no key, a key is longer than 64 characters, or a morph type is longer than 191. Nothing is written. |
| `Illuminate\Database\ClassMorphViolationException` | every method | `Relation::enforceMorphMap()` is on and a model has no alias. Nothing is written. |
| `Illuminate\Database\QueryException` | every method | Any database error other than the pair's own unique index refusing a duplicate. |

### Configuration and publish tags

| Key | Default |
| --- | --- |
| `pharaonic.follows.table` | `follows` |
| `pharaonic.follows.cleanup_on_delete` | `true` |

| Tag | Publishes |
| --- | --- |
| `follows-config`, `pharaonic-config` | `config/pharaonic/follows.php` |
| `follows-migrations`, `pharaonic-migrations` | `database/migrations/2026_11_04_000001_create_pharaonic_follows_table.php` |
| `laravel-follows`, `pharaonic` | both |
