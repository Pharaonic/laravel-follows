## Events

The package dispatches two events, only when a call actually changed the table:

| Event | Dispatched by | Not dispatched |
| --- | --- | --- |
| `Pharaonic\Laravel\Follows\Events\FollowCreated` | `follow()`, `followBy()`, `toggleFollow()` when they insert the follow | When the follow already existed |
| `Pharaonic\Laravel\Follows\Events\FollowRemoved` | `unfollow()`, `unfollowBy()`, `toggleFollow()` when they delete the follow | When there was no follow, and for the cleanup on delete |

Both have three public properties: `$follow` (the `Follow` row), `$follower`, and `$followable`. In `FollowRemoved`, `$follow` is the deleted row, with `exists` false.

```php title="app/Listeners/NotifyAuthor.php"
namespace App\Listeners;

use App\Notifications\PostFollowed;
use Pharaonic\Laravel\Follows\Events\FollowCreated;

class NotifyAuthor
{
    public function handle(FollowCreated $event)
    {
        if ($event->followable instanceof \App\Post) {
            $event->followable->author->notify(new PostFollowed($event->follower));
        }
    }
}
```

```php title="app/Providers/EventServiceProvider.php"
protected $listen = [
    \Pharaonic\Laravel\Follows\Events\FollowCreated::class => [
        \App\Listeners\NotifyAuthor::class,
    ],
];
```

### When listeners run

The events are dispatched right after the insert or delete. When you follow inside a database transaction, the listeners run before that transaction commits, and a rollback afterwards does not take the event back. Make a listener that must only see committed follows a queued listener dispatched after your transaction, or call `follow()` outside it.

### Model events

`FollowCreated` comes with the `Follow` model's own `creating` and `created` events, because the follow is saved through Eloquent; a `creating` listener that returns false cancels the follow, and `follow()` then returns false. The removal deletes the row with a query, so the `Follow` model's `deleting` and `deleted` events do not fire. Listen to `FollowRemoved` instead.
