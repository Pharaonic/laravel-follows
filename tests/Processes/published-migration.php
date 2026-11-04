<?php

/*
 * Publishes the migration and runs it, in a process of its own: a PHP
 * process can declare the migration class from one file only, and an
 * application only ever loads one file per migration name. Prints what
 * each step returned, as JSON.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Follows\Tests\Processes\WorkerApplication;

require __DIR__.'/../../vendor/autoload.php';

$migration = '2026_11_04_000001_create_pharaonic_follows_table';

$app = (new WorkerApplication('published-migration'))->createApplication();
$kernel = $app->make(Kernel::class);
$published = $app->databasePath('migrations/'.$migration.'.php');
$results = [];

@unlink($published);

try {
    $results['publish'] = $kernel->call('vendor:publish', ['--tag' => 'follows-migrations']);
    $results['copied'] = is_file($published)
        && sha1_file($published) === sha1_file(__DIR__.'/../../database/migrations/'.$migration.'.php');

    $results['migrate'] = $kernel->call('migrate');
    $kernel->call('migrate:status');
    $results['listed'] = substr_count($kernel->output(), $migration);

    $results['rollback'] = $kernel->call('migrate:rollback');
    $results['dropped'] = ! Schema::hasTable('follows');

    $results['migrate_again'] = $kernel->call('migrate');
    $results['fresh'] = $kernel->call('migrate:fresh');
    $results['recorded'] = DB::table('migrations')->where('migration', $migration)->count();
    $results['created'] = Schema::hasTable('follows');
} finally {
    @unlink($published);
}

echo json_encode($results);
