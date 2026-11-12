# Changelog

All notable changes to this project are documented here.

## 9.0.1 - Unreleased

### Fixed

- Inside a database transaction, `follow()`, `followBy()` and `toggleFollow()` threw the unique index violation when another request had committed the same follow after the transaction's first read (MySQL's REPEATABLE READ). They now find that follow with a locking read and return false, as they do outside a transaction.

## 9.0.0 - 2026-11-05

First release of the Laravel 9 line, built from 8.0.0.

### Compatibility

- Laravel 9.33+ on PHP 8.0, 8.1 or 8.2. Laravel 9.33 is the first 9.x release that requires a Carbon release (2.62.1+) working on PHP 8.2; with older ones, every date method throws a `TypeError` on PHP 8.2.
- The same API and behavior as 8.0.0.

## 8.0.0 - 2026-11-05

First release of the Laravel 8 line, built from 7.0.0.

### Compatibility

- Laravel 8.75+ on PHP 8.0 or 8.1.
- The same API and behavior as 7.0.0. With `Relation::enforceMorphMap()`, following with an unmapped model throws Laravel's `ClassMorphViolationException` before anything is written.

## 7.0.0 - 2026-11-05

First release of the Laravel 7 line, built from 6.0.0.

### Compatibility

- Laravel 7.30+ on PHP 8.0.
- The same API and behavior as 6.0.0.

## 6.0.0 - 2026-11-05

### Added

- `CanFollow` for the model that follows: `follow()`, `unfollow()`, `isFollowing()`, `toggleFollow()`, and the `followings()` relation.
- `HasFollowers` for the model that is followed: `followBy()`, `unfollowBy()`, `followedBy()`, the `followers()` relation, and the `whereFollowedBy()` scope. A model can use both traits.
- The `Follow` model with `follower()` and `followable()` relations and `whereFollower()` and `whereFollowable()` scopes.
- A `follows` table whose unique index keeps each follower and followable pair to one row, with string ids so integer, UUID, and ULID keys share it.
- `FollowCreated` and `FollowRemoved` events, dispatched only when a call inserts or deletes a follow.
- Follows are deleted with the model that gave or received them, and kept when it is only soft deleted.
- `pharaonic.follows.table` and `pharaonic.follows.cleanup_on_delete` settings.

### Compatibility

- Laravel 6 on PHP 8.0.
- Replaces the follows of `pharaonic/laravel-users`, whose methods return different values and whose table needs converting first.
