<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;
use Laravel\Passkeys\Passkeys;
use Simtabi\Laranail\AuthKit\Models\Passkey;
use Simtabi\Laranail\AuthKit\Providers\AuthKitServiceProvider;
use Workbench\App\Models\User;

it('enables Fortify passkeys and registers the canonical routes', function (): void {
    expect(config('fortify.features'))->toContain(Features::passkeys())
        ->and(Features::enabled(Features::passkeys()))->toBeTrue();

    foreach ([
        'passkey.login-options',
        'passkey.login',
        'passkey.confirm-options',
        'passkey.confirm',
        'passkey.registration-options',
        'passkey.store',
        'passkey.destroy',
    ] as $routeName) {
        expect(Route::has($routeName))->toBeTrue();
    }
});

it('publishes the Auth Kit passkeys migration', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        provider: AuthKitServiceProvider::class,
        group: 'laranail::authkit-passkey-migrations',
    );

    expect(array_map('realpath', array_keys($paths)))
        ->toContain(realpath(dirname(__DIR__, 2).'/database/migrations/passkeys'));
});

it('delegates passkey routes to the laravel passkeys controllers', function (): void {
    expect(Route::getRoutes()->getByName('passkey.login')->getActionName())
        ->toBe(PasskeyLoginController::class.'@store')
        ->and(Route::getRoutes()->getByName('passkey.confirm')->getActionName())
        ->toBe(PasskeyConfirmationController::class.'@store')
        ->and(Route::getRoutes()->getByName('passkey.store')->getActionName())
        ->toBe(PasskeyRegistrationController::class.'@store');
});

it('applies Fortify passkey authentication and confirmation middleware', function (): void {
    $loginRoute = Route::getRoutes()->getByName('passkey.login');
    $registrationRoute = Route::getRoutes()->getByName('passkey.registration-options');

    expect($loginRoute->middleware())->toContain('guest:web')
        ->and($registrationRoute->middleware())->toContain('auth:web')
        ->and($registrationRoute->middleware())->toContain('password.confirm');
});

it('provides the passkey user contract and polymorphic relationship', function (): void {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(PasskeyUser::class)
        ->and(Passkeys::passkeyModel())->toBe(Passkey::class)
        ->and($user->hasPasskeysEnabled())->toBeFalse();

    $passkey = $user->passkeys()->create([
        'name' => 'MacBook Pro',
        'credential_id' => 'credential-id',
        'credential' => ['public-key' => 'credential'],
    ]);

    expect($user->fresh()->passkeys)->toHaveCount(1)
        ->and($user->fresh()->hasPasskeysEnabled())->toBeTrue()
        ->and($passkey->fresh()->credential)->toBe(['public-key' => 'credential'])
        ->and($passkey->passkeyable_type)->toBe(User::class)
        ->and($passkey->passkeyable_id)->toBe($user->getKey())
        ->and($passkey->passkeyable)->toBeInstanceOf(User::class)
        ->and($passkey->user_id)->toBe($user->getKey());
});
