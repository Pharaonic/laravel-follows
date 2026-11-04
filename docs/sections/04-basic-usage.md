## Basic Usage

Add `CanFollow` to the model that follows and `HasFollowers` to the model that is followed.

```php title="app/User.php"
namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Follows\Concerns\CanFollow;

class User extends Authenticatable
{
    use CanFollow;
}
```

```php title="app/Post.php"
namespace App;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Follows\Concerns\HasFollowers;

class Post extends Model
{
    use HasFollowers;
}
```

The follower doesn't have to be a user, and nothing in the package reads the authenticated user: a `Team`, a `Bot` or any other model can use `CanFollow`. A model can be followed without `HasFollowers` too; the trait adds the `followers()` relation and the `followBy()`, `unfollowBy()`, `followedBy()` and `whereFollowedBy()` helpers.

### Following and unfollowing

`follow()` and `unfollow()` return whether **this call** changed the table, not the current state:

```php
$user->follow($post);      // true: the follow was created
$user->follow($post);      // false: it already existed, nothing changed

$user->unfollow($post);    // true: the follow was removed
$user->unfollow($post);    // false: there was no follow, nothing changed

$user->isFollowing($post); // false
```

Both are idempotent: calling them again leaves the same state. Use `isFollowing()` when you need the current state.

### Toggling

`toggleFollow()` returns the **new state**, true when the pair is followed after the call:

```php
$user->toggleFollow($post); // true: now followed
$user->toggleFollow($post); // false: now not followed
```

A toggle is not idempotent: two calls cancel each other. When two toggles of the same pair arrive at the same moment, the result is not guaranteed to be what two toggles in a row would give (see [Integrity and Concurrency](#integrity-and-concurrency)). Prefer `follow()` and `unfollow()` for endpoints that a user can click twice.

### From the followed model

`HasFollowers` adds the same operations seen from the other side. They work on the same row as the `CanFollow` methods:

```php
$post->followBy($user);     // true when created, like $user->follow($post)
$post->followedBy($user);   // true, like $user->isFollowing($post)
$post->unfollowBy($user);   // true when removed, like $user->unfollow($post)

$user->follow($post);
$post->unfollowBy($user);   // true
$user->isFollowing($post);  // false
```

### A model that follows and is followed

A model can use both traits. `followings()` holds the follows it gave and `followers()` the follows it received:

```php
class User extends Authenticatable
{
    use CanFollow, HasFollowers;
}

$alice->follow($bob);

$alice->followings()->count();     // 1
$alice->followers()->count();      // 0
$bob->followers()->count();        // 1
$bob->followedBy($alice);          // true
```

### Unsaved models

The writes need both models saved, because a follow stores their keys. `follow()`, `unfollow()`, `toggleFollow()`, `followBy()` and `unfollowBy()` throw an `InvalidArgumentException` before touching the database when either model is unsaved. The reads `isFollowing()` and `followedBy()` return false, and `whereFollowedBy()` matches nothing.

```php
$user->follow(new Post); // InvalidArgumentException: The followable [App\Post] is not saved: ...
$user->isFollowing(new Post); // false
```
