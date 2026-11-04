<?php

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Follows\FollowsServiceProvider;

test('the provider is registered', function () {
    $this->assertNotEmpty($this->app->getProviders(FollowsServiceProvider::class));
});

test('the configuration defaults are merged', function () {
    $this->assertSame('follows', $this->app['config']->get('pharaonic.follows.table'));
    $this->assertTrue($this->app['config']->get('pharaonic.follows.cleanup_on_delete'));
});

test('the configuration is published to config pharaonic', function () {
    $source = realpath(__DIR__.'/../../config/follows.php');

    foreach (['pharaonic', 'laravel-follows', 'pharaonic-config', 'follows-config'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(FollowsServiceProvider::class, $tag);
        $published = [];

        foreach ($paths as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        $this->assertArrayHasKey($source, $published, $tag);
        $this->assertSame($this->app->configPath('pharaonic/follows.php'), $published[$source]);
    }
});

test('the migrations are published to database migrations', function () {
    $source = realpath(__DIR__.'/../../database/migrations');

    foreach (['pharaonic', 'laravel-follows', 'pharaonic-migrations', 'follows-migrations'] as $tag) {
        $published = [];

        foreach (ServiceProvider::pathsToPublish(FollowsServiceProvider::class, $tag) as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        $this->assertArrayHasKey($source, $published, $tag);
        $this->assertSame($this->app->databasePath('migrations'), $published[$source]);
    }
});
