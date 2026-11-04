## Deletes and Soft Deletes

When a model that uses `CanFollow` or `HasFollowers` is deleted through Eloquent, its follows are deleted with it:

| Action | Follows it gave (`CanFollow`) | Follows it received (`HasFollowers`) |
| --- | --- | --- |
| `$model->delete()` | deleted | deleted |
| `$model->delete()` on a `SoftDeletes` model | kept | kept |
| `$model->restore()` | still there | still there |
| `$model->forceDelete()` | deleted | deleted |

A model with both traits loses both directions. Keeping the follows on a soft delete means a restored model has exactly the follows it had.

```php
$post->delete();       // SoftDeletes: the follows stay
$post->restore();      // $post->followedBy($user) is still true
$post->forceDelete();  // the follows are deleted
```

The cleanup deletes the rows with one query and dispatches no `FollowRemoved` events.

### Turning cleanup off

```php title="config/pharaonic/follows.php"
'cleanup_on_delete' => false,
```

Deleting a model then leaves its follows in the table. `whereFollowedBy()` and `withCount()` ignore them for models that no longer exist, but `followings()` of a follower still lists follows of deleted posts.

### Deletes that skip the cleanup

The cleanup runs in the model's `deleted` event, so it only happens when a model instance is deleted. These skip it:

- `Post::where(...)->delete()` and other query deletes;
- `DB::table('posts')->delete()` and raw SQL;
- `truncate()`.

After such a delete, remove the follows yourself, for example in the same job:

```php
use Pharaonic\Laravel\Follows\Models\Follow;

$ids = Post::where('created_at', '<', now()->subYear())->pluck('id');

Post::whereIn('id', $ids)->delete();
Follow::where('followable_type', (new Post)->getMorphClass())
    ->whereIn('followable_id', $ids->map(fn ($id) => (string) $id))
    ->delete();
```

To clean up after the fact, delete the follows whose model is gone:

```php
Follow::where('followable_type', (new Post)->getMorphClass())
    ->whereNotIn('followable_id', Post::withTrashed()->pluck('id')->map(fn ($id) => (string) $id))
    ->delete();
```

Leave out `withTrashed()` for models without `SoftDeletes`. Run it per type, and for `follower_type` the same way.
