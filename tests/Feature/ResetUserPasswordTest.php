<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Actions\ResetUserPassword;

it('hashes and persists the new password', function (): void {
    $user = User::factory()->create([
        'password'       => Hash::make('old-password'),
        'remember_token' => 'stolen-remember-token',
    ]);
    $user->createToken('stolen-token');

    app(ResetUserPassword::class)->reset($user, [
        'password'              => 'new-secret',
        'password_confirmation' => 'new-secret',
    ]);

    expect(Hash::check('new-secret', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse()
        ->and($user->fresh()->remember_token)->toBeNull()
        ->and($user->fresh()->tokens()->count())->toBe(0);
});

it('fails validation when password confirmation does not match', function (): void {
    $user = User::factory()->create();

    try {
        app(ResetUserPassword::class)->reset($user, [
            'password'              => 'new-secret',
            'password_confirmation' => 'different',
        ]);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('password');
    }
});

it('fails validation when password is missing', function (): void {
    $user = User::factory()->create();

    try {
        app(ResetUserPassword::class)->reset($user, []);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('password');
    }
});

it('integrates with laravel password broker for a full reset flow', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $token = \Illuminate\Support\Facades\Password::broker()->createToken($user);

    $status = \Illuminate\Support\Facades\Password::broker()->reset(
        [
            'email'                 => $user->email,
            'password'              => 'broker-secret',
            'password_confirmation' => 'broker-secret',
            'token'                 => $token,
        ],
        function ($user, $password) {
            app(ResetUserPassword::class)->reset($user, [
                'password'              => $password,
                'password_confirmation' => $password,
            ]);
        }
    );

    expect($status)->toBe(\Illuminate\Support\Facades\Password::PASSWORD_RESET)
        ->and(Hash::check('broker-secret', $user->fresh()->password))->toBeTrue();
});
