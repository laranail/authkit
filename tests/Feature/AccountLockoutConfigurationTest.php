<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\AuthKit\Services\AccountLockout;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;

trait CustomLockoutConfigurationEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laranail.authkit.lockout', [
            'enabled' => true, 'max_attempts' => 1, 'duration_minutes' => 3,
            'table'   => 'custom_authkit_lockouts', 'connection' => 'testing',
        ]);
    }
}

uses(CustomLockoutConfigurationEnvironment::class);

it('publishes and migrates the configured lockout table and uses its policy', function (): void {
    $paths = ServiceProvider::pathsToPublish(AuthKitServiceProvider::class, 'laranail::authkit-lockout-migrations');
    expect($paths)->toBe([dirname(__DIR__, 2) . '/database/migrations/lockout' => database_path('migrations')]);
    $user = User::factory()->create();
    $lockout = app(AccountLockout::class);
    expect($lockout->recordFailure($user, 'web'))->toBe(180)
        ->and($lockout->retryAfter($user, 'web'))->toBe(180);
    $lockout->clear($user, 'web');
    expect($lockout->retryAfter($user, 'web'))->toBe(0);
});
