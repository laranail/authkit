<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Simtabi\Laranail\AuthKit\Actions\UpdateUserProfileInformation;
use Workbench\App\Models\User;

class ProfileVerifiableUser extends User implements MustVerifyEmail
{
    public bool $verificationNotificationSent = false;

    protected $table = 'users';

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return (bool) $this->forceFill(['email_verified_at' => now()])->save();
    }

    public function markEmailAsUnverified(): bool
    {
        return (bool) $this->forceFill(['email_verified_at' => null])->save();
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->verificationNotificationSent = true;
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }
}

function expectProfileUpdateValidationFailure(array $input, string $field, User $user): void
{
    try {
        app(UpdateUserProfileInformation::class)->update($user, $input);
    } catch (ValidationException $e) {
        expect($e->errorBag)->toBe('updateProfileInformation')
            ->and($e->errors())->toHaveKey($field);

        return;
    }

    throw new RuntimeException('Expected profile information update validation to fail.');
}

it('binds the Fortify profile updater to Auth Kit', function (): void {
    expect(app(UpdatesUserProfileInformation::class))->toBeInstanceOf(UpdateUserProfileInformation::class);
});

it('updates the user profile information', function (): void {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    app(UpdateUserProfileInformation::class)->update($user, [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    expect($user->fresh()->only(['name', 'email']))->toBe([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);
});

it('rejects invalid profile information', function (): void {
    $user = User::factory()->create();

    expectProfileUpdateValidationFailure(
        input: [
            'name' => '',
            'email' => 'not-an-email',
        ],
        field: 'name',
        user: $user,
    );
});

it('rejects an email address already used by another user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create(['email' => 'taken@example.com']);

    expectProfileUpdateValidationFailure(
        input: [
            'name' => 'Updated User',
            'email' => $otherUser->email,
        ],
        field: 'email',
        user: $user,
    );
});

it('resets email verification when a verified user changes their email', function (): void {
    $user = new ProfileVerifiableUser([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
    $user->save();

    app(UpdateUserProfileInformation::class)->update($user, [
        'name' => 'Ada Lovelace',
        'email' => 'ada@lovelace.example',
    ]);

    expect($user->fresh()->email_verified_at)->toBeNull()
        ->and($user->verificationNotificationSent)->toBeTrue();
});
