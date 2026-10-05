<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;
use Simtabi\Laranail\AuthKit\Http\Middleware\DeprecatedTwoFactorAlias;
use Simtabi\Laranail\AuthKit\Http\Middleware\RequireTwoFactorAuthentication;

/**
 * The core registers no views, translations or routes — but it does own publish tags and a
 * configuration key, both of which are flat global registries. Asserted against the live
 * registry rather than the provider source, so the guard survives a refactor.
 */
it('never registers a bare publish tag', function (): void {
    // Testbench does not always populate publishableGroups(), so this asserts the invariant that
    // matters — nothing unscoped — rather than requiring the registry to be non-empty here. The
    // positive case is proved end-to-end in the demo application's acceptance suite.
    $bare = array_filter(
        array_keys(ServiceProvider::publishableGroups()),
        fn (string $tag): bool => str_contains($tag, 'authkit') && ! str_starts_with($tag, 'laranail::'),
    );

    expect(array_values($bare))->toBe([]);
});

it('keeps its configuration under the laranail namespace', function (): void {
    expect(config('laranail.authkit'))->toBeArray()
        ->and(config('auth-kit'))->toBeNull();
});

it('registers no bare top-level configuration key', function (): void {
    foreach (['auth-kit', 'authkit'] as $bare) {
        expect(config($bare))->toBeNull();
    }
});

it('registers the two-factor middleware under the vendor-scoped alias', function (): void {
    $aliases = app('router')->getMiddleware();

    expect($aliases)->toHaveKey(AuthKitServiceProvider::TWO_FACTOR_MIDDLEWARE)
        ->and($aliases[AuthKitServiceProvider::TWO_FACTOR_MIDDLEWARE])->toBe(RequireTwoFactorAuthentication::class)
        ->and(AuthKitServiceProvider::TWO_FACTOR_MIDDLEWARE)->toBe('laranail-authkit-two-factor');
});

it('registers no bare middleware alias other than the deprecated two-factor one', function (): void {
    $ours = array_filter(
        app('router')->getMiddleware(),
        fn (string $class): bool => str_starts_with($class, 'Simtabi\\Laranail\\AuthKit\\'),
    );

    // Non-vacuity: the scan must see this package's aliases at all.
    expect($ours)->not->toBeEmpty();

    $bare = array_filter(
        array_keys($ours),
        fn (string $alias): bool => ! str_starts_with($alias, 'laranail-authkit') && $alias !== DeprecatedTwoFactorAlias::ALIAS,
    );

    expect(array_values($bare))->toBe([]);
});

it('keeps the deprecated bare two-factor alias enforcing, and warns once', function (): void {
    expect(app('router')->getMiddleware()[DeprecatedTwoFactorAlias::ALIAS] ?? null)
        ->toBe(DeprecatedTwoFactorAlias::class);

    DeprecatedTwoFactorAlias::resetWarning();
    Log::spy();

    Route::middleware(DeprecatedTwoFactorAlias::ALIAS)->get('/__two-factor-bare', fn () => 'ok');
    Route::middleware(AuthKitServiceProvider::TWO_FACTOR_MIDDLEWARE)->get('/__two-factor-scoped', fn () => 'ok');

    // A guest is refused through both spellings: the bare alias is not a bypass.
    $this->get('/__two-factor-bare')->assertStatus(401);
    $this->get('/__two-factor-bare')->assertStatus(401);
    $this->get('/__two-factor-scoped')->assertStatus(401);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message): bool => str_contains($message, 'laranail-authkit-two-factor'));
});
