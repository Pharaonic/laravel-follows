## Installation

Install the package with Composer and create its table.

### Requirements

- PHP 8.3, 8.4 or 8.5
- Laravel 13
- MySQL, MariaDB, PostgreSQL or SQLite

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-follows
```

The service provider is registered automatically.

### Run Migrations

The package loads its migration automatically, so the `follows` table is created with your other migrations:

```bash title="Terminal" no-line-numbers
php artisan migrate
```

To change the migration before it runs, publish it into `database/migrations`:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=follows-migrations
```

The published file keeps the package's file name, `2026_11_04_000001_create_pharaonic_follows_table.php`, so Laravel runs your copy instead of the package's, never both.

:::warning Existing follows table
The migration stops with an error when a `follows` table already exists, for example one created by `pharaonic/laravel-users`. Set `pharaonic.follows.table` to another name before migrating, or move that table out of the way first.
:::

### Publish Configuration

Publish the configuration only when you need another table name or want to turn off cleanup on delete:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=follows-config
```

The file is published to `config/pharaonic/follows.php`.

:::success Installation Complete
You're all set! Add `CanFollow` and `HasFollowers` to your models, as [Basic Usage](#basic-usage) shows.
:::
