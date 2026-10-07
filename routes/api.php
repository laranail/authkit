<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Http\Controllers\Api;

/*
|--------------------------------------------------------------------------
| REST API
|--------------------------------------------------------------------------
|
| These live in the headless core rather than in the frontend preset. An API-only or Filament
| consumer installs this package alone and gets a login endpoint with it, instead of having to
| pull in Blade scaffolding to obtain one -- which is what "headless core with full REST API
| support" has to mean for it to be true.
|
| A frontend package that would rather mount its own API sets laranail.authkit.api.enabled to
| false and registers it.
|
*/

if (! AuthKit::apiEnabled()) {
    return;
}

Route::prefix(AuthKit::apiPrefix())
    ->middleware(AuthKit::apiMiddleware())
    // Positional, not named: Route::name() resolves through RouteRegistrar::__call, which reads
    // $parameters[0]; a named argument leaves that slot empty and degrades to true.
    ->name(AuthKit::apiRouteNamePrefix())
    ->group(function (): void {
        Route::post('/register', [Api\RegisterController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('register');

        Route::post('/login', [Api\LoginController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('login');

        if (AuthKit::twoFactorEnabled()) {
            Route::post('/two-factor/challenge', Api\TwoFactorChallengeController::class)
                ->middleware('throttle:5,1')
                ->name('two-factor.challenge');

            Route::middleware([AuthKit::apiTokenMiddleware(), 'throttle:10,1'])->group(function (): void {
                Route::get('/user/two-factor', [Api\TwoFactorManagementController::class, 'show'])->name('user-two-factor.show');
                Route::post('/user/two-factor', [Api\TwoFactorManagementController::class, 'begin'])->name('user-two-factor.begin');
                Route::post('/user/two-factor/confirm', [Api\TwoFactorManagementController::class, 'confirm'])->name('user-two-factor.confirm');
                Route::post('/user/two-factor/disable', [Api\TwoFactorManagementController::class, 'disable'])->name('user-two-factor.disable');
                Route::post('/user/two-factor/recovery-codes', [Api\TwoFactorManagementController::class, 'regenerateRecoveryCodes'])->name('user-two-factor.recovery-codes');
            });
        }

        Route::post('/logout', Api\LogoutController::class)
            ->middleware(AuthKit::apiTokenMiddleware())
            ->name('logout');

        // CheckEmailExistsController ships with no route, exactly as it did before this move.
        // Exposing an endpoint that answers whether an address is registered is a user-enumeration
        // decision, not a refactor, so it stays unrouted until someone makes it deliberately.

        if (AuthKit::hasFeature('email-verification')) {
            Route::post('/email/verification-notification', [Api\EmailVerificationNotificationController::class, 'store'])
                ->middleware([AuthKit::apiTokenMiddleware(), 'throttle:6,1'])
                ->name('verification.send');

            Route::get('/email/verify/{id}/{hash}', Api\VerifyEmailController::class)
                ->middleware([AuthKit::apiTokenMiddleware(), 'signed', 'throttle:6,1'])
                ->name('verification.verify');
        }

        if (AuthKit::hasFeature('reset-passwords')) {
            Route::post('/forgot-password', [Api\PasswordResetLinkController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('password.email');

            Route::post('/reset-password', [Api\NewPasswordController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('password.update');
        }

        if (AuthKit::hasFeature('update-passwords')) {
            Route::put('/user/password', [Api\UpdatePasswordController::class, 'update'])
                ->middleware(AuthKit::apiTokenMiddleware())
                ->name('user-password.update');
        }

        if (AuthKit::hasFeature('update-profile-information')) {
            Route::put('/user/profile-information', [Api\UpdateProfileInformationController::class, 'update'])
                ->middleware(AuthKit::apiTokenMiddleware())
                ->name('user-profile-information.update');
        }
    });
