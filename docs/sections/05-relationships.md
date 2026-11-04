## Relationships

All relations are native Eloquent relations, so everything Eloquent offers works on them.

| Relation | On | Type | Holds |
| --- | --- | --- | --- |
| `followings()` | `CanFollow` models | `MorphMany` of `Follow` | The follows the model gave. |
| `followers()` | `HasFollowers` models | `MorphMany` of `Follow` | The follows the model received. |
| `follower()` | `Follow` | `MorphTo` | The model that gave the follow. |
| `followable()` | `Follow` | `MorphTo` | The model that received the follow. |

### Why `followings()` and not `followers()`

The follower side's relation is called `followings()` so it never clashes with `followers()` on the followed side. A `User` can use both traits without a trait conflict, and `$user->followers` always means the follows the user received.

### Reading through the relations

```php
use Pharaonic\Laravel\Follows\Models\Follow;

$follow = $post->followers()->latest()->first();

$follow->follower;    // App\User
$follow->followable;  // App\Post
$follow->created_at;

$user->followings()->with('followable')->latest()->get()
    ->map(fn (Follow $follow) => $follow->followable); // the posts, comments, ... the user followed
```

### The `Follow` model

`Pharaonic\Laravel\Follows\Models\Follow` reads the table from `pharaonic.follows.table` and has standard timestamps. It has two scopes for filtering follows by one end:

```php
Follow::whereFollower($user)->count();      // follows the user gave
Follow::whereFollowable($post)->count();   // follows the post received

User::whereHas('followings', fn ($query) => $query->whereFollowable($post))->get(); // who followed the post
```

The follows table lives on the default database connection. Counts, filters and eager loading need it in the same database as your models.
