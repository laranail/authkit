<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * AuthKit decides which Fortify features are on, through `laranail.authkit.fortify.features`, and
 * turns Fortify's own views off. Fortify reads both in its `boot()` and immediately mounts its
 * routes, so whichever provider boots first decides the application's route set.
 *
 * Measured 2026-11-25, same code and config, provider order the only difference:
 *
 * | route            | Fortify first | AuthKit first |
 * |------------------|---------------|---------------|
 * | register         | exists        | absent        |
 * | two-factor.login | exists        | absent        |
 * | password.request | exists        | absent        |
 *
 * The suite shipped the Fortify-first order; alphabetical package discovery gives a real
 * application the other one, because `laranail/authkit` sorts before `laravel/fortify`. So the
 * tested behaviour was the one an install does not get, and in that order Fortify mounted
 * registration and two-factor routes AuthKit's own config says are off.
 *
 * Writing the config at register time fixes it: every provider's `register()` runs before any
 * provider's `boot()`, so Fortify cannot read a stale value whatever the order. The preset already
 * relies on this for `Fortify::ignoreRoutes()`.
 *
 * This file asserts the shipped order; `FortifyFeatureOrderReversedTest` asserts the other, with
 * the same expectations. They must agree.
 */
it('mounts only the features AuthKit declares, with Fortify registered first', function (): void {
    expect(config('fortify.features'))->not->toContain('registration')
        ->and(config('fortify.features'))->not->toContain('two-factor-authentication');

    expect(Route::has('register'))->toBeFalse('Fortify mounted registration, which AuthKit does not declare')
        ->and(Route::has('two-factor.login'))->toBeFalse('Fortify mounted two-factor, which AuthKit does not declare');
});
