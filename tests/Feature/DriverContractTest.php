<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\StatefulGuard;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerInterface;
use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

uses(AssertsDriverContract::class);

it('builds the configured stateful credential guard through the real manager', function (): void {
    $guard = AuthKit::guard();
    $driver = config('auth.guards.' . $guard . '.driver');
    $this->assertEveryDriverIsBuildable(AuthManager::class, [$driver]);
    expect(app(AuthManager::class)->guard($guard))->toBeInstanceOf(StatefulGuard::class);
});

it('resolves every shipped token issuer to the advertised contract', function (): void {
    $files = glob(dirname(__DIR__, 2) . '/src/Actions/*TokenIssuer.php');
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $class = 'Simtabi\\Laranail\\AuthKit\\Actions\\' . basename($file, '.php');
        expect(app($class))->toBeInstanceOf(TokenIssuerInterface::class);
    }
});

it('rejects an unregistered token driver instead of falling back silently', function (): void {
    config()->set('laranail.authkit.tokens.driver', 'missing-driver');

    expect(fn () => app(TokenIssuerRegistryInterface::class)->issue(User::factory()->create()))
        ->toThrow(InvalidArgumentException::class);
});
