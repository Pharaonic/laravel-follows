## Troubleshooting

### `The [follows] table already exists` when migrating

Another package, usually `pharaonic/laravel-users`, created a `follows` table. Set `pharaonic.follows.table` to another name before migrating, or move that table out of the way first.

### `follow()` returns false the first time

The pair was already followed: `follow()` returns whether this call created the follow. Use `isFollowing()` for the current state. A `creating` listener on the `Follow` model that returns false also makes `follow()` return false.

### `The follower [App\User] is not saved`

A write was called with a model that isn't saved. Save it first, or check `$model->exists`.

### `Register a shorter alias with Relation::morphMap()`

The class name is longer than the 191 characters the type columns hold. Add a morph map alias for the model.

### `Illegal mix of collations` on MySQL

Your model's table and the follows table use different collations, and the model has a string key (UUID, ULID). Create both with the same collation, the `collation` of your database connection.

### `operator does not exist: bigint = character varying` on PostgreSQL

A query compares a model key with the string `followable_id` column directly, for example a hand-written join. Go through the package relations (`followers()`, `followings()`, `whereFollowedBy()`, `withCount('followers')`), which compare the key as a string, or cast it yourself: `cast(posts.id as varchar) = follows.followable_id`.

### Follows stay after deleting models

The models were deleted with a query (`Post::where(...)->delete()`), which skips model events, or `cleanup_on_delete` is false. See [Deletes and Soft Deletes](#deletion-and-soft-deletes) for the manual cleanup.

### Follows are missing after adding a morph map

Rows stored before the map hold class names, and the package now looks for the alias. Update those rows deliberately, type by type, for example `UPDATE follows SET followable_type = 'post' WHERE followable_type = 'App\\Post';`.
