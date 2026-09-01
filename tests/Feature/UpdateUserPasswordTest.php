<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Simtabi\Laranail\AuthKit\Actions\UpdateUserPassword;
use Workbench\App\Models\User;

function expectPasswordUpdateValidationFailure(array $input, string $field, User $user): void
{
    try {
        app(UpdateUserPassword::class)->update($user, $input);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);

        return;
    }

    throw new RuntimeException('Expected password update validation to fail.');
}

it('binds the Fortify password updater to Auth Kit', function (): void {
    expect(app(UpdatesUserPasswords::class))->toBeInstanceOf(UpdateUserPassword::class);
});

it('updates the password when the current password is valid', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
        'remember_token' => 'stolen-remember-token',
    ]);
    $user->createToken('stolen-token');

    $this->actingAs($user);

    app(UpdateUserPassword::class)->update($user, [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->fresh()->password))->toBeFalse()
        ->and($user->fresh()->remember_token)->toBeNull()
        ->and($user->fresh()->tokens()->count())->toBe(0);
});

it('rejects an invalid current password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user);

    expectPasswordUpdateValidationFailure(
        input: [
            'current_password' => 'invalid-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ],
        field: 'current_password',
        user: $user,
    );
});

it('rejects an unconfirmed password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user);

    expectPasswordUpdateValidationFailure(
        input: [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ],
        field: 'password',
        user: $user,
    );
});
