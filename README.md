<p align="center"><a href="https://pharaonic.dev/packages/laravel/follows" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-follows/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.0%20|%208.1%20|%208.2&color=blue&style=flat-square" alt="PHP Version : 8.0 | 8.1 | 8.2"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=9.x&color=F05340&style=flat-square" alt="Laravel Version : 9.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-follows/actions/workflows/build.yml/badge.svg?branch=9.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-follows" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-follows&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-follows" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-follows/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-follows" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-follows/downloads" alt="Packagist Downloads"></a>
</p>

<h3 align="center">Polymorphic follows for Eloquent models, kept unique by the database.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-follows
php artisan migrate
```

The service provider is registered automatically, and `migrate` creates the `follows` table. Publish the migration only to change it (`--tag=follows-migrations`), and the configuration only to rename the table or turn off cleanup on delete:

```bash
php artisan vendor:publish --tag=follows-config
```

## Usage

Add `CanFollow` to the model that follows (it does not have to be a user) and `HasFollowers` to the model that is followed. A model can use both.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Follows\Concerns\CanFollow;

class User extends Authenticatable
{
    use CanFollow;
}
```

```php
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Follows\Concerns\HasFollowers;

class Post extends Model
{
    use HasFollowers;
}
```

```php
$user->follow($post);        // true: this call created the follow
$user->follow($post);        // false: it already existed
$user->isFollowing($post);   // true
$user->unfollow($post);      // true: this call removed it, false when there was none
$user->toggleFollow($post);  // the new state: true when followed

$post->followBy($user);      // same as $user->follow($post)
$post->unfollowBy($user);    // same as $user->unfollow($post)
$post->followedBy($user);    // same as $user->isFollowing($post)

Post::withCount('followers')->with('followers.follower')->get();
Post::whereFollowedBy($user)->latest()->paginate();
$user->followings()->with('followable')->latest()->get();
```

## Documentation

Full documentation: [Laravel Follows Documentation](https://pharaonic.dev/packages/laravel/follows).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
