<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;
use Simtabi\Laranail\AuthKit\Actions\UpdateUserPassword;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

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
    expect(TestCredentials::password('new'))->not->toBe(TestCredentials::password('old'));

    $user = User::factory()->create([
        'password'       => Hash::make(TestCredentials::password('old')),
        'remember_token' => TestCredentials::secret('remember'),
    ]);
    $user->createToken('stolen-token');

    $this->actingAs($user);

    app(UpdateUserPassword::class)->update($user, [
        'current_password'      => TestCredentials::password('old'),
        'password'              => TestCredentials::password('new'),
        'password_confirmation' => TestCredentials::password('new'),
    ]);

    expect(Hash::check(TestCredentials::password('new'), $user->fresh()->password))->toBeTrue()
        ->and(Hash::check(TestCredentials::password('old'), $user->fresh()->password))->toBeFalse()
        ->and($user->fresh()->remember_token)->toBeNull()
        ->and($user->fresh()->tokens()->count())->toBe(0);
});

it('rejects an invalid current password', function (): void {
    expect(TestCredentials::password('invalid'))->not->toBe(TestCredentials::password('old'));

    $user = User::factory()->create(['password' => Hash::make(TestCredentials::password('old'))]);

    $this->actingAs($user);

    expectPasswordUpdateValidationFailure(
        input: [
            'current_password'      => TestCredentials::password('invalid'),
            'password'              => TestCredentials::password('new'),
            'password_confirmation' => TestCredentials::password('new'),
        ],
        field: 'current_password',
        user: $user,
    );
});

it('rejects an unconfirmed password', function (): void {
    expect(TestCredentials::password('different'))->not->toBe(TestCredentials::password('new'));

    $user = User::factory()->create(['password' => Hash::make(TestCredentials::password('old'))]);

    $this->actingAs($user);

    expectPasswordUpdateValidationFailure(
        input: [
            'current_password'      => TestCredentials::password('old'),
            'password'              => TestCredentials::password('new'),
            'password_confirmation' => TestCredentials::password('different'),
        ],
        field: 'password',
        user: $user,
    );
});
