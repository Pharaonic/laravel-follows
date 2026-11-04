## Querying and Counts

Counting and filtering run in SQL. None of them loads the follows into PHP.

### Counting

```php
$post->followers()->count();               // select count(*) ...

$posts = Post::withCount('followers')->get();
$posts->first()->followers_count;          // 12

$post->loadCount('followers');
```

:::warning
`$post->followers->count()` (property, not method) loads every follow of the post first. Use `followers()->count()` or `withCount('followers')`. The package has no `followers_count` accessor, so it never runs a hidden query.
:::

### Filtering by follower

`whereFollowedBy()` keeps the models the follower follows, through `whereHas()`:

```php
Post::whereFollowedBy($user)->latest()->paginate();
```

### "Did I follow this?" in a list

Calling `$user->isFollowing($post)` for every post of a page runs one query per post. Count the viewer's follow in the same query instead:

```php
$posts = Post::withCount([
    'followers',
    'followers as followed_by_me' => fn ($query) => $query->whereFollower($request->user()),
])->latest()->paginate();

$post->followers_count;        // 12
(bool) $post->followed_by_me;  // true when the viewer followed it
```

### Eager loading

Load follows and both ends with a fixed number of queries:

```php
Post::with('followers.follower')->get();                 // posts, follows, then one query per follower type
$user->followings()->with('followable')->latest()->get();
```

### Indexes

The migration creates:

| Index | Columns | Serves |
| --- | --- | --- |
| `follows_unique_pair` (unique) | `followable_type`, `followable_id`, `follower_type`, `follower_id` | Uniqueness, `followers()`, `withCount('followers')`, `followedBy()` |
| `follows_follower_index` | `follower_type`, `follower_id` | `followings()`, `whereFollowedBy()` |

The ids are strings, so the relations compare your model's key as a string too. That keeps both indexes usable, and lets PostgreSQL compare a `bigint` or `uuid` key with the `varchar` column at all.
