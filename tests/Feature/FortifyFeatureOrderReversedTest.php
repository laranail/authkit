<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;

/**
 * Companion to `FortifyFeatureOrderTest`; see that file for the reasoning.
 *
 * This one cannot fail for the defect that file describes, and that is deliberate rather than an
 * oversight. Measured 2026-11-25 by moving `configureFortify()` back from `packageRegistered()` to
 * `packageBooted()` and running both files: `FortifyFeatureOrderTest` went red, this one stayed
 * green. In the AuthKit-first order AuthKit's own `boot()` still runs before Fortify's, so
 * configuring at boot time happens to work -- which is exactly why the bug survived, since this is
 * the order alphabetical package discovery gives a real application.
 *
 * So its job is the other half: that fixing the Fortify-first order did not change the order that
 * already worked. It would go red if the config were written later still -- from an
 * `$this->app->booted()` callback, say -- which no test above covers. Do not read a green run here
 * as evidence about provider ordering; that evidence is in the companion.
 */
trait AuthKitRegistersFirst
{
    protected function getPackageProviders($app): array
    {
        // What alphabetical package discovery gives: laranail/authkit before laravel/fortify.
        return [AuthKitServiceProvider::class, FortifyServiceProvider::class, SanctumServiceProvider::class];
    }
}

uses(AuthKitRegistersFirst::class);

it('mounts only the features AuthKit declares, with AuthKit registered first', function (): void {
    expect(config('fortify.features'))->not->toContain('registration')
        ->and(config('fortify.features'))->not->toContain('two-factor-authentication');

    expect(Route::has('register'))->toBeFalse()
        ->and(Route::has('two-factor.login'))->toBeFalse();
});
