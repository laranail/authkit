<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Fortify\FortifyServiceProvider::class,
            \Laravel\Sanctum\SanctumServiceProvider::class,
            AuthKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('auth.providers.users.model', \Workbench\App\Models\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/laravel/fortify/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations/passkeys');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/laravel/sanctum/database/migrations');
    }
}
