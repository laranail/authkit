<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Actions\ResetUserPassword;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

it('hashes and persists the new password', function (): void {
    expect(TestCredentials::password('new'))->not->toBe(TestCredentials::password('old'));

    $user = User::factory()->create([
        'password'       => Hash::make(TestCredentials::password('old')),
        'remember_token' => TestCredentials::secret('remember'),
    ]);
    $user->createToken('stolen-token');

    app(ResetUserPassword::class)->reset($user, [
        'password'              => TestCredentials::password('new'),
        'password_confirmation' => TestCredentials::password('new'),
    ]);

    expect(Hash::check(TestCredentials::password('new'), $user->fresh()->password))->toBeTrue()
        ->and(Hash::check(TestCredentials::password('old'), $user->password))->toBeFalse()
        ->and($user->fresh()->remember_token)->toBeNull()
        ->and($user->fresh()->tokens()->count())->toBe(0);
});

it('fails validation when password confirmation does not match', function (): void {
    expect(TestCredentials::password('different'))->not->toBe(TestCredentials::password('new'));

    $user = User::factory()->create();

    try {
        app(ResetUserPassword::class)->reset($user, [
            'password'              => TestCredentials::password('new'),
            'password_confirmation' => TestCredentials::password('different'),
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
    $user = User::factory()->create(['password' => Hash::make(TestCredentials::password('old'))]);

    $token = Password::broker()->createToken($user);

    $status = Password::broker()->reset(
        [
            'email'                 => $user->email,
            'password'              => TestCredentials::password('broker'),
            'password_confirmation' => TestCredentials::password('broker'),
            'token'                 => $token,
        ],
        function ($user, $password) {
            app(ResetUserPassword::class)->reset($user, [
                'password'              => $password,
                'password_confirmation' => $password,
            ]);
        },
    );

    expect($status)->toBe(Password::PASSWORD_RESET)
        ->and(Hash::check(TestCredentials::password('broker'), $user->fresh()->password))->toBeTrue();
});
