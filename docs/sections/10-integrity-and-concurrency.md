## Integrity and Concurrency

### The unique index

The `follows_unique_pair` index on (`followable_type`, `followable_id`, `follower_type`, `follower_id`) is what keeps a pair to one row. The package never reads first and writes second: `follow()` inserts the row, and when the index refuses it because the pair already has one, `follow()` returns false. Two requests that follow the same post at the same moment therefore get `true` and `false`, and the table holds one row.

Only a refusal by a unique index counts as "already followed", and only when the pair's row can then be read. Every other database error is thrown: a missing table, a lost connection, a `NOT NULL` failure, or a unique index of your own that refused the row.

Inside a transaction the insert runs in a savepoint, so a refused insert doesn't abort your transaction on PostgreSQL.

### Deadlocks

On MySQL, a follow and an unfollow (or two toggles) of the same pair at the same moment can make InnoDB roll back one of the statements to break a deadlock. Outside a transaction, the package runs that statement again, up to three times, so the call still returns its normal result. Inside your own transaction, the database has already rolled the whole transaction back, so the deadlock reaches you; run the transaction with retries:

```php
DB::transaction(function () use ($user, $post) {
    $user->follow($post);
    // ...
}, 3);
```

### Removing

`unfollow()` reads the pair's follow and deletes it by id. The call that deleted the row returns true and dispatches `FollowRemoved`; a simultaneous `unfollow()` deletes nothing and returns false.

### Toggling under concurrency

`toggleFollow()` inserts first: when the insert works, the pair is now followed and the call returns true. When the pair already has a follow, the call deletes it and returns false. No lock is held between the two steps.

What is guaranteed:

- the table never holds two rows for a pair;
- every call returns the state it left the pair in;
- events are dispatched only for changes the call made.

What is not: two toggles of the same pair at the same moment are not serialized. Starting from "followed", both may delete the same follow, so the pair ends up not followed instead of followed again. When a user's intent matters, as in a follow button, send the state the user wants and call `follow()` or `unfollow()`.

### Tested databases

The test suite runs on SQLite, MySQL 8.4 and PostgreSQL 16, including tests that start several PHP processes, each with its own connection, following, unfollowing or toggling the same pair at the same moment. On SQLite the database file serializes those writes; MySQL and PostgreSQL run them in parallel.
