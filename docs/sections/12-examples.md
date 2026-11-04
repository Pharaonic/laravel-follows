## Examples

### 1. A follow button

Send the state the user wants, so a double click can't undo itself, and return the count for the button.

- ===Routes

  ```php title="routes/web.php"
  Route::middleware('auth')->group(function () {
      Route::post('posts/{post}/follow', 'PostFollowController@store');
      Route::delete('posts/{post}/follow', 'PostFollowController@destroy');
  });
  ```

- ===Controller

  ```php title="app/Http/Controllers/PostFollowController.php"
  namespace App\Http\Controllers;

  use App\Post;
  use Illuminate\Http\Request;

  class PostFollowController extends Controller
  {
      public function store(Request $request, Post $post)
      {
          $request->user()->follow($post);

          return ['followed' => true, 'followers' => $post->followers()->count()];
      }

      public function destroy(Request $request, Post $post)
      {
          $request->user()->unfollow($post);

          return ['followed' => false, 'followers' => $post->followers()->count()];
      }
  }
  ```

### 2. A feed with counts and the viewer's follows

One query for the posts, their counts and whether the viewer followed each one.

```php title="app/Http/Controllers/FeedController.php"
public function index(Request $request)
{
    $posts = Post::withCount([
        'followers',
        'followers as followed_by_me' => fn ($query) => $query->whereFollower($request->user()),
    ])->latest()->paginate(20);

    return view('feed', compact('posts'));
}
```

### 3. A "followed posts" page

```php
$posts = Post::whereFollowedBy($request->user())->latest()->paginate(20);
```

Or newest follow first, with every type the user followed:

```php
$follows = $request->user()->followings()->with('followable')->latest()->paginate(20);
```

### 4. Users who follow each other

`User` uses both traits, so users can follow users.

```php title="app/User.php"
class User extends Authenticatable
{
    use CanFollow, HasFollowers;
}
```

```php
$alice->follow($bob);

$bob->followers()->with('follower')->get();      // who followed Bob
$alice->followings()->count();                   // how many follows Alice gave
User::whereFollowedBy($alice)->get();            // the users Alice follows
```

### 5. Notifying the author

```php title="app/Listeners/NotifyAuthor.php"
public function handle(FollowCreated $event)
{
    if ($event->followable instanceof Post && ! $event->followable->author->is($event->follower)) {
        $event->followable->author->notify(new PostFollowed($event->follower));
    }
}
```

Register it for `Pharaonic\Laravel\Follows\Events\FollowCreated` in your `EventServiceProvider`.
