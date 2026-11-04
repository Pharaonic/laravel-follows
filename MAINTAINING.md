# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Polymorphic follows between any two Eloquent models: `CanFollow` for the model that follows, `HasFollowers` for the model that is followed, one `follows` table whose unique index keeps each pair to one row.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major. Each line's PHP range, `illuminate/*` constraint, and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit; later lines run Pest.

## Core library

None. Everything the package does is Eloquent glue.

## Public API invariants

Configuration keys (`pharaonic.follows.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Configuration: `pharaonic.follows.table` (default `follows`) and `pharaonic.follows.cleanup_on_delete` (default `true`).
- Publish tags: `pharaonic`, `laravel-follows`, `pharaonic-config`, `follows-config`, `pharaonic-migrations`, and `follows-migrations`.
- `CanFollow`: `follow()`, `unfollow()`, `isFollowing()`, `toggleFollow()`, `followings()`. `HasFollowers`: `followers()`, `followBy()`, `unfollowBy()`, `followedBy()`, `scopeWhereFollowedBy()`. Neither trait defines a method of the other, so a model can use both.
- `follow()`, `followBy()`, `unfollow()`, and `unfollowBy()` return whether the call changed the table; `toggleFollow()` returns the state it left. `isFollowing()` and `followedBy()` return false for an unsaved model; the writes throw `InvalidArgumentException` for one.
- `Models\Follow` with `follower()`, `followable()`, `scopeWhereFollower()`, `scopeWhereFollowable()`, and the columns `follower_type`, `follower_id`, `followable_type`, `followable_id`.
- `Events\FollowCreated` and `Events\FollowRemoved`, each with public `$follow`, `$follower`, and `$followable`, dispatched only when the call inserted or deleted the row.
- `Support\FollowManager` and `Relations\FollowsMorphMany` are internal.

## Package-specific constraints

- The migration file name `2026_11_04_000001_create_pharaonic_follows_table.php` and its class `CreatePharaonicFollowsTable` never change. The class is named because `6.x` supports Laravel below 8.37, and it is not `CreateFollowsTable` so it never collides with the class `pharaonic/laravel-users` ships.
- Later schema changes go in new migrations, never in the released one.
- The migration refuses to run over an existing table (it may be the `laravel-users` table without the unique index).
- Uniqueness comes from the `{table}_unique_pair` index, never from a read before the write. A unique violation counts as "already followed" only when the pair's row can be read afterwards; otherwise the insert is retried, then the exception is rethrown.
- A deadlock (SQLSTATE `40001` or `40P01`, MySQL error 1213) on the insert or delete runs the statement again, up to three times, but only outside a transaction: inside one, the database already rolled the caller's transaction back.
- Ids are stored as strings (`varchar(64)`), so the follows relations compare the parent key as a string in `has()`, `whereHas()`, and `withCount()` (`FollowsMorphMany`): PostgreSQL refuses `bigint = varchar` and `uuid = varchar`. MySQL uses `CONCAT()` rather than `CAST()`, which fails when the connection collation differs from the table's.
- Events are dispatched right after the write, inside the caller's transaction when there is one, because `ShouldDispatchAfterCommit` needs Laravel 10.30.
- `phpstan.neon.dist` also analyses `tests/Fixtures`: PHPStan analyses a trait only through a class that uses it.
- `ConcurrencyTest` runs separate PHP processes against a shared database: a SQLite file by default, MySQL or PostgreSQL through the `FOLLOWS_DB*` environment variables (`.github/workflows/databases.yml`).

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/follows

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
