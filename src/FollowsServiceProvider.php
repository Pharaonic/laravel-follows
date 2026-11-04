<?php

namespace Pharaonic\Laravel\Follows;

use Illuminate\Support\ServiceProvider;

final class FollowsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/follows.php', 'pharaonic.follows');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/follows.php' => $this->app->configPath('pharaonic/follows.php'),
            ], ['pharaonic', 'laravel-follows', 'pharaonic-config', 'follows-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], ['pharaonic', 'laravel-follows', 'pharaonic-migrations', 'follows-migrations']);
        }
    }
}
