<?php

namespace Pharaonic\Laravel\Follows\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Follows\FollowsServiceProvider;
use Pharaonic\Laravel\Follows\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_provider_is_registered(): void
    {
        $this->assertNotEmpty($this->app->getProviders(FollowsServiceProvider::class));
    }

    public function test_the_configuration_defaults_are_merged(): void
    {
        $this->assertSame('follows', $this->app['config']->get('pharaonic.follows.table'));
        $this->assertTrue($this->app['config']->get('pharaonic.follows.cleanup_on_delete'));
    }

    public function test_the_configuration_is_published_to_config_pharaonic(): void
    {
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
    }

    public function test_the_migrations_are_published_to_database_migrations(): void
    {
        $source = realpath(__DIR__.'/../../database/migrations');

        foreach (['pharaonic', 'laravel-follows', 'pharaonic-migrations', 'follows-migrations'] as $tag) {
            $published = [];

            foreach (ServiceProvider::pathsToPublish(FollowsServiceProvider::class, $tag) as $from => $to) {
                $published[realpath($from) ?: $from] = $to;
            }

            $this->assertArrayHasKey($source, $published, $tag);
            $this->assertSame($this->app->databasePath('migrations'), $published[$source]);
        }
    }
}
