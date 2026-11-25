<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\FortifyServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;

/** Companion to `FortifyFeatureOrderTest`; see that file for the reasoning. */
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
