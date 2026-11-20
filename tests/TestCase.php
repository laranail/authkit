<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Tests;

use Workbench\App\Models\User;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FortifyServiceProvider::class,
            SanctumServiceProvider::class,
            AuthKitServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'testing');

        $driver = getenv('DB_CONNECTION') ?: 'sqlite';
        $connection = $app['config']->get("database.connections.{$driver}", []);
        $connection['driver'] = $driver;

        if ($driver === 'sqlite') {
            $connection['database'] = getenv('DB_DATABASE') ?: ':memory:';
            $connection['prefix'] = '';
            $connection['foreign_key_constraints'] = true;
        }

        $app['config']->set('database.connections.testing', $connection);

        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/orchestra/testbench-core/laravel/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/laravel/fortify/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations/passkeys');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations/two-factor');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/vendor/laravel/sanctum/database/migrations');
        $this->loadMigrationsFrom(dirname(__DIR__) . '/workbench/database/migrations');
    }
}
