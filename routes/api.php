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

        Route::post('/logout', Api\LogoutController::class)
            ->middleware('auth:sanctum')
            ->name('logout');

        // CheckEmailExistsController ships with no route, exactly as it did before this move.
        // Exposing an endpoint that answers whether an address is registered is a user-enumeration
        // decision, not a refactor, so it stays unrouted until someone makes it deliberately.

        if (AuthKit::hasFeature('email-verification')) {
            Route::post('/email/verification-notification', [Api\EmailVerificationNotificationController::class, 'store'])
                ->middleware(['auth:sanctum', 'throttle:6,1'])
                ->name('verification.send');

            Route::get('/email/verify/{id}/{hash}', Api\VerifyEmailController::class)
                ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
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
                ->middleware('auth:sanctum')
                ->name('user-password.update');
        }

        if (AuthKit::hasFeature('update-profile-information')) {
            Route::put('/user/profile-information', [Api\UpdateProfileInformationController::class, 'update'])
                ->middleware('auth:sanctum')
                ->name('user-profile-information.update');
        }
    });
