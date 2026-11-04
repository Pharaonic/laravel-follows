## Configuration

Both settings are optional. Publish the file with `php artisan vendor:publish --tag=follows-config` to change them.

```php title="config/pharaonic/follows.php"
return [
    'table' => 'follows',
    'cleanup_on_delete' => true,
];
```

| Key | Default | Description |
| --- | --- | --- |
| `pharaonic.follows.table` | `follows` | The table the migration creates and the `Follow` model uses. |
| `pharaonic.follows.cleanup_on_delete` | `true` | Delete a model's follows when the model is deleted. See [Deletes and Soft Deletes](#deletion-and-soft-deletes). |

### Changing the table name

The migration reads `pharaonic.follows.table` when it runs, so set the name **before** the first `php artisan migrate`:

```php title="config/pharaonic/follows.php"
'table' => 'post_follows',
```

Index names follow the table name (`post_follows_unique_pair`, `post_follows_follower_index`).

:::warning
Changing the setting after the migration ran does not rename the table: the package then reads and writes a table that doesn't exist. Rename the table in a migration of your own (`Schema::rename('follows', 'post_follows')`) at the same time as you change the setting. Rolling back the package migration drops the table the setting names at that moment.
:::
