:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Laravel Follows

Laravel Follows lets any Eloquent model follow any other. Add `CanFollow` to the model that follows, such as a user, and `HasFollowers` to the model that is followed, such as a post. Each follow is one row in a `follows` table, and a unique index on that table keeps every follower and followable pair to one row, even when two requests try to create it at the same moment. `follow()` and `unfollow()` tell you whether they changed anything, and the follows are native Eloquent relations, so counts, filters and eager loading run in SQL.

:::features
### Results That Say What Changed {icon="check-circle"}
follow and unfollow return true only when the call created or removed the follow.

### Unique by the Database {icon="lock"}
A unique index, not a read before the write, keeps each pair to one row.

### Any Model, Both Roles {icon="users"}
Users, bots or teams follow posts, comments, products or each other, and one model can follow and be followed.

### Native Relations {icon="chart"}
Count, filter and eager load follows with withCount, whereHas and with.

### Mixed Key Types {icon="tag"}
Integer, UUID and ULID models share one table, and morph map aliases are respected.

### Clean Deletes {icon="shield-check"}
Deleting a model deletes its follows, while a soft delete keeps them for a restore.
:::

:::info Quick Tip
Send the state the user wants (`follow()` or `unfollow()`) from your UI instead of `toggleFollow()`: a double click then can't undo itself.
:::
