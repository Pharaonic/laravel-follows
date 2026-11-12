# Changelog

All notable changes to this project are documented here.

## 7.0.1 - Unreleased

### Fixed

- Inside a database transaction, `follow()`, `followBy()` and `toggleFollow()` threw the unique index violation when another request had committed the same follow after the transaction's first read (MySQL's REPEATABLE READ). They now find that follow with a locking read and return false, as they do outside a transaction.

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
