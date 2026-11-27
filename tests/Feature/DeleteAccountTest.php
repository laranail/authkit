<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Services\EmailChange;
use Simtabi\Laranail\AuthKit\Events\AccountDeleted;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Simtabi\Laranail\AuthKit\Events\AccountDeleting;
use Simtabi\Laranail\AuthKit\Contracts\DeleteUserInterface;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerInterface;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

class SoftDeletedAccountUser extends User
{
    use SoftDeletes;

    protected $table = 'deletion_test_users';
}

class OtherDeletionPopulationUser extends User
{
    protected $table = 'deletion_test_users';
}

class RefusesAccountDeletionUser extends User
{
    protected $table = 'users';
}

function deletionSession(string $id, User $user): void
{
    DB::table('sessions')->insert([
        'id' => $id, 'user_id' => $user->getKey(), 'payload' => '', 'last_activity' => time(),
    ]);
}

beforeEach(function (): void {
    $this->password = TestCredentials::password('deletion');
    $this->user = User::factory()->create(['password' => $this->password]);
});

it('deletes only the authenticated account and cleans up its authentication data', function (): void {
    Notification::fake();
    Event::fake([AccountDeleting::class, AccountDeleted::class]);
    $other = User::factory()->create();
    $token = $this->user->createToken('deletion')->plainTextToken;
    $this->user->createToken('other-device');
    $other->createToken('other-account');
    $this->user->passkeys()->create(['name' => 'Account key', 'credential_id' => 'deletion-account', 'credential' => []]);
    $other->passkeys()->create(['name' => 'Other key', 'credential_id' => 'deletion-other', 'credential' => []]);
    $broker = app(PasswordBrokerFactory::class)->broker();
    $broker->createToken($this->user);
    $broker->createToken($other);
    app(EmailChange::class)->request($this->user, 'pending@example.test');
    config(['session.driver' => 'database']);
    deletionSession('deletion-browser', $this->user);
    deletionSession('deletion-other-browser', $other);

    $this->withToken($token)->deleteJson('/api/auth/user', ['password' => $this->password])->assertNoContent();

    expect(User::find($this->user->getKey()))->toBeNull()
        ->and($this->user->tokens()->count())->toBe(0)
        ->and($this->user->passkeys()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $this->user->email)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'deletion-browser')->exists())->toBeFalse()
        ->and(app(EmailChange::class)->pending($this->user))->toBeNull()
        ->and($other->fresh())->not->toBeNull()
        ->and($other->tokens()->count())->toBe(1)
        ->and($other->passkeys()->count())->toBe(1)
        ->and(DB::table('password_reset_tokens')->where('email', $other->email)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'deletion-other-browser')->exists())->toBeTrue();
    Event::assertDispatchedTimes(AccountDeleting::class, 1);
    Event::assertDispatchedTimes(AccountDeleted::class, 1);
    Event::assertDispatched(AccountDeleted::class, fn ($event) => $event->userType === User::class && $event->userId === (string) $this->user->getKey());
});

it('rejects old tokens passwords and reset proofs after deletion', function (): void {
    $token = $this->user->createToken('deletion')->plainTextToken;
    $reset = app(PasswordBrokerFactory::class)->broker()->createToken($this->user);
    $this->withToken($token)->deleteJson('/api/auth/user', ['password' => $this->password])->assertNoContent();
    // Represent a fresh request rather than retaining the test application's cached guard user.
    app('auth')->forgetGuards();
    $this->withToken($token)->deleteJson('/api/auth/user', ['password' => $this->password])->assertUnauthorized();
    $this->withoutHeader('Authorization')->postJson('/api/auth/login', ['email' => $this->user->email, 'password' => $this->password])->assertUnprocessable();
    $this->postJson('/api/auth/reset-password', [
        'email'                 => $this->user->email, 'token' => $reset,
        'password'              => TestCredentials::password('replacement'),
        'password_confirmation' => TestCredentials::password('replacement'),
    ])->assertUnprocessable();
});

it('requires authentication and current password confirmation', function (?string $password, bool $authenticated): void {
    Event::fake([AccountDeleting::class, AccountDeleted::class]);
    $token = $this->user->createToken('deletion')->plainTextToken;
    if ($authenticated) {
        $this->withToken($token);
    }
    $response = $this->deleteJson('/api/auth/user', $password === null ? [] : ['password' => $password]);
    if ($authenticated) {
        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    } else {
        $response->assertUnauthorized();
    }
    expect($this->user->fresh())->not->toBeNull()->and($this->user->tokens()->count())->toBe(1);
    Event::assertNotDispatched(AccountDeleting::class);
    Event::assertNotDispatched(AccountDeleted::class);
})->with([
    'unauthenticated'  => [null, false],
    'missing password' => [null, true],
    'wrong password'   => [TestCredentials::password('incorrect'), true],
]);

it('rejects attempts to target another account', function (string $target): void {
    $other = User::factory()->create();
    $this->withToken($this->user->createToken('deletion')->plainTextToken);
    if ($target === 'path') {
        $this->deleteJson('/api/auth/user/' . $other->getKey(), ['password' => $this->password])->assertNotFound();
    } else {
        $this->deleteJson('/api/auth/user', ['password' => $this->password, $target => $other->getKey()])->assertUnprocessable()->assertJsonValidationErrors($target);
    }
    expect($this->user->fresh())->not->toBeNull()->and($other->fresh())->not->toBeNull();
})->with(['path', 'id', 'user_id']);

it('rechecks the stored password instead of trusting a stale authenticated model', function (): void {
    $newPassword = TestCredentials::password('changed');
    DB::table('users')->where('id', $this->user->getKey())->update(['password' => Hash::make($newPassword)]);
    expect(fn () => app(DeleteUserInterface::class)->execute($this->user, $this->password))->toThrow(ValidationException::class);
    expect($this->user->fresh())->not->toBeNull();
    app(DeleteUserInterface::class)->execute($this->user, $newPassword);
    expect($this->user->fresh())->toBeNull();
});

it('rolls back account and local tokens when a registered issuer fails cleanup', function (): void {
    $issuer = Mockery::mock(TokenIssuerInterface::class);
    $issuer->shouldReceive('revokeAll')->once()->andThrow(new RuntimeException('Issuer unavailable.'));
    app(TokenIssuerRegistryInterface::class)->register('unavailable', $issuer);
    $this->user->createToken('retained');
    Event::fake([AccountDeleted::class]);

    expect(fn () => app(DeleteUserInterface::class)->execute($this->user, $this->password))->toThrow(RuntimeException::class, 'Issuer unavailable.');
    expect($this->user->fresh())->not->toBeNull()->and($this->user->tokens()->count())->toBe(1);
    Event::assertNotDispatched(AccountDeleted::class);
});

it('allows an application cleanup listener to veto deletion before credentials are removed', function (): void {
    $this->user->createToken('retained');
    Event::listen(AccountDeleting::class, function (): void {
        throw new DomainException('Account has retained application data.');
    });
    Event::fake([AccountDeleted::class]);
    expect(fn () => app(DeleteUserInterface::class)->execute($this->user, $this->password))->toThrow(DomainException::class);
    expect($this->user->fresh())->not->toBeNull()->and($this->user->tokens()->count())->toBe(1);
    Event::assertNotDispatched(AccountDeleted::class);
});

it('does not report success when an Eloquent observer refuses deletion', function (): void {
    $user = RefusesAccountDeletionUser::findOrFail($this->user->getKey());
    $user->createToken('retained');
    Event::listen('eloquent.deleting: ' . RefusesAccountDeletionUser::class, fn () => false);
    Event::fake([AccountDeleted::class]);
    expect(fn () => app(DeleteUserInterface::class)->execute($user, $this->password))->toThrow(RuntimeException::class, 'Account deletion was refused.');
    expect($user->fresh())->not->toBeNull()->and($user->tokens()->count())->toBe(1);
    Event::assertNotDispatched(AccountDeleted::class);
});

it('does not remove another model populations tokens when identifiers collide', function (): void {
    $other = new OtherDeletionPopulationUser;
    $other->forceFill(['id' => $this->user->getKey(), 'name' => 'Other population', 'email' => 'other@example.test', 'password' => $this->password])->save();
    $other->createToken('other-population');
    app(DeleteUserInterface::class)->execute($this->user, $this->password);
    expect($other->fresh())->not->toBeNull()->and($other->tokens()->count())->toBe(1);
});

it('respects soft deletion while destroying credentials and removing active authentication data', function (): void {
    $user = new SoftDeletedAccountUser;
    $user->forceFill([
        'name'                      => 'Retained account', 'email' => 'retained@example.test', 'password' => $this->password,
        'remember_token'            => TestCredentials::secret('remember'), 'two_factor_secret' => TestCredentials::secret('factor'),
        'two_factor_recovery_codes' => json_encode([TestCredentials::secret('recovery')]),
        'two_factor_confirmed_at'   => now(), 'two_factor_method' => 'totp',
    ])->save();
    $user->createToken('removed');
    app(DeleteUserInterface::class)->execute($user, $this->password);
    $retained = SoftDeletedAccountUser::withTrashed()->findOrFail($user->getKey());
    expect($retained->trashed())->toBeTrue()
        ->and(Hash::check($this->password, $retained->password))->toBeFalse()
        ->and($retained->remember_token)->toBeNull()
        ->and($retained->two_factor_secret)->toBeNull()
        ->and($retained->two_factor_recovery_codes)->toBeNull()
        ->and($retained->two_factor_confirmed_at)->toBeNull()
        ->and($retained->two_factor_method)->toBe('none')
        ->and($retained->tokens()->count())->toBe(0);
});

it('refuses passwordless deletion until the application supplies an alternative reauthentication policy', function (): void {
    $user = new OtherDeletionPopulationUser;
    $user->forceFill(['name' => 'Passwordless', 'email' => 'passwordless@example.test', 'password' => null])->save();
    expect(fn () => app(DeleteUserInterface::class)->execute($user, $this->password))->toThrow(ValidationException::class);
    expect($user->fresh())->not->toBeNull();
});

it('throttles failed deletion attempts without deleting the account', function (): void {
    $this->withToken($this->user->createToken('deletion')->plainTextToken);
    for ($i = 0; $i < 6; $i++) {
        $this->deleteJson('/api/auth/user', ['password' => TestCredentials::password('incorrect')])->assertUnprocessable();
    }
    $this->deleteJson('/api/auth/user', ['password' => $this->password])->assertTooManyRequests();
    expect($this->user->fresh())->not->toBeNull();
});

it('does not remove a different populations password reset with the same email', function (): void {
    $other = new OtherDeletionPopulationUser;
    $other->forceFill(['name' => 'Other population', 'email' => $this->user->email, 'password' => $this->password])->save();
    $broker = app(PasswordBrokerFactory::class)->broker();
    $reset = $broker->createToken($this->user);
    app(DeleteUserInterface::class)->execute($other, $this->password);
    expect($broker->tokenExists($this->user, $reset))->toBeTrue()->and($this->user->fresh())->not->toBeNull();
});

it('invalidates the current session when the controller is used with stateful authentication', function (): void {
    Illuminate\Support\Facades\Route::middleware(['web', 'auth:web'])
        ->delete('/stateful-account-deletion', Simtabi\Laranail\AuthKit\Http\Controllers\Api\DeleteAccountController::class);
    $csrf = bin2hex(random_bytes(20));
    $this->actingAs($this->user, 'web')->withSession(['private-marker' => 'present', '_token' => $csrf])
        ->deleteJson('/stateful-account-deletion', ['password' => $this->password])->assertNoContent()
        ->assertSessionMissing('private-marker')->assertSessionHas('_token', fn ($value) => $value !== $csrf);
});
