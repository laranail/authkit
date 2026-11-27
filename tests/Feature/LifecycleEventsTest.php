<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Simtabi\Laranail\AuthKit\Events;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\AuthKit\Actions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

it('publishes registration only after successful persistence without the password', function (): void {
    Event::fake([Events\UserRegistered::class]);
    $password = TestCredentials::password('register');
    $user = app(Actions\CreateNewUser::class)->create([
        'name'     => 'Audit user', 'email' => 'audit@example.test',
        'password' => $password, 'password_confirmation' => $password,
    ]);

    Event::assertDispatchedTimes(Events\UserRegistered::class, 1);
    Event::assertDispatched(Events\UserRegistered::class, fn ($event) => $event->userId === (string) $user->getKey()
        && $event->userType === $user::class && ! str_contains(json_encode($event), $password));
});

it('does not publish registration when validation rejects the input', function (): void {
    Event::fake([Events\UserRegistered::class]);
    expect(fn () => app(Actions\CreateNewUser::class)->create([]))->toThrow(ValidationException::class);
    Event::assertNotDispatched(Events\UserRegistered::class);
});

it('reports failed and throttled password attempts without claiming a session', function (): void {
    Event::fake([Events\AuthenticationFailed::class, Events\SessionAuthenticated::class]);
    config()->set('laranail.authkit.rate_limit.max_attempts', 1);
    $request = Request::create('/login', 'POST', ['email' => 'absent@example.test', 'password' => TestCredentials::password('attempt')]);
    $attempt = app(Actions\AttemptEmailPasswordLogin::class);
    $attempt->execute($request, 'web');
    $attempt->execute($request, 'web');

    Event::assertDispatchedTimes(Events\AuthenticationFailed::class, 2);
    Event::assertDispatched(Events\AuthenticationFailed::class, fn ($event) => $event->reason === 'invalid_credentials' && $event->guard === 'web');
    Event::assertDispatched(Events\AuthenticationFailed::class, fn ($event) => $event->reason === 'throttled');
    Event::assertNotDispatched(Events\SessionAuthenticated::class);
});

it('publishes a single session event after login and preserves identity at logout', function (): void {
    $user = User::factory()->create();
    Event::fake([Events\SessionAuthenticated::class, Events\SessionLoggedOut::class]);
    app(Actions\LoginUser::class)->execute($user, 'web', true);
    Event::assertDispatchedTimes(Events\SessionAuthenticated::class, 1);
    Event::assertDispatched(Events\SessionAuthenticated::class, fn ($event) => $event->userId === (string) $user->getKey() && $event->guard === 'web' && $event->remember);
    app(Actions\LogoutUser::class)->execute('web');
    Event::assertDispatchedTimes(Events\SessionLoggedOut::class, 1);
    Event::assertDispatched(Events\SessionLoggedOut::class, fn ($event) => $event->userId === (string) $user->getKey() && $event->userType === $user::class);
});

it('notifies ordinary Laravel listeners after a password reset has persisted and revoked tokens', function (): void {
    $user = User::factory()->create();
    $user->createToken('before-reset');
    $seen = [];
    Event::listen(Events\PasswordUpdated::class, function ($event) use (&$seen, $user): void {
        expect($user->fresh()->tokens()->count())->toBe(0);
        $seen[] = [$event->userId, $event->operation];
    });
    $password = TestCredentials::password('reset');
    app(Actions\ResetUserPassword::class)->reset($user, ['password' => $password, 'password_confirmation' => $password]);

    expect($seen)->toBe([[(string) $user->getKey(), 'reset']]);
});

it('reports password changes only after valid current password proof', function (): void {
    $current = TestCredentials::password('current');
    $password = TestCredentials::password('replacement');
    $user = User::factory()->create(['password' => $current]);
    $this->actingAs($user, 'web');
    Event::fake([Events\PasswordUpdated::class]);
    $action = app(Actions\UpdateUserPassword::class);
    expect(fn () => $action->update($user, ['current_password' => TestCredentials::password('wrong'), 'password' => $password, 'password_confirmation' => $password]))->toThrow(ValidationException::class);
    Event::assertNotDispatched(Events\PasswordUpdated::class);
    $action->update($user, ['current_password' => $current, 'password' => $password, 'password_confirmation' => $password]);
    Event::assertDispatchedTimes(Events\PasswordUpdated::class, 1);
    Event::assertDispatched(Events\PasswordUpdated::class, fn ($event) => $event->operation === 'update' && $event->userId === (string) $user->getKey());
});

class AuditedVerifiedUser extends User implements Illuminate\Contracts\Auth\MustVerifyEmail
{
    protected $table = 'users';
}

it('reports profile updates once including the verification-required branch', function (bool $requiresVerification): void {
    $user = User::factory()->create();
    if ($requiresVerification) {
        $user = AuditedVerifiedUser::query()->findOrFail($user->getKey());
    }
    Notification::fake();
    Event::fake([Events\ProfileUpdated::class]);
    app(Actions\UpdateUserProfileInformation::class)->update($user, ['name' => 'Changed', 'email' => 'changed@example.test']);
    Event::assertDispatchedTimes(Events\ProfileUpdated::class, 1);
    Event::assertDispatched(Events\ProfileUpdated::class, fn ($event) => $event->userId === (string) $user->getKey());
})->with([false, true]);

it('reports token issuance and each revocation request without exposing the token', function (): void {
    $user = User::factory()->create();
    Event::fake([Events\TokenIssued::class, Events\TokensRevoked::class]);
    $registry = app(TokenIssuerRegistryInterface::class);
    $issued = $registry->issue($user);
    Event::assertDispatchedTimes(Events\TokenIssued::class, 1);
    Event::assertDispatched(Events\TokenIssued::class, fn ($event) => $event->driver === 'sanctum' && $event->userId === (string) $user->getKey()
        && ! str_contains(json_encode($event), $issued->token));
    $user->withAccessToken($user->tokens()->sole());
    $registry->revokeCurrent($user);
    $registry->revokeAll($user);
    Event::assertDispatchedTimes(Events\TokensRevoked::class, 2);
    Event::assertDispatched(Events\TokensRevoked::class, fn ($event) => $event->scope === 'current');
    Event::assertDispatched(Events\TokensRevoked::class, fn ($event) => $event->scope === 'all');
    config()->set('laranail.authkit.tokens.driver', 'unregistered');
    expect(fn () => $registry->issue($user))->toThrow(InvalidArgumentException::class);
    Event::assertDispatchedTimes(Events\TokenIssued::class, 1);
});

it('emits factor lifecycle metadata only after confirmation and excludes recovery values', function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->string('two_factor_method')->default('none');
    });
    $user = User::factory()->create();
    Event::fake([Events\TwoFactorEnabled::class, Events\TwoFactorDisabled::class, Events\RecoveryCodesRegenerated::class]);
    $factor = app(TwoFactorAuthentication::class);
    $setup = $factor->begin($user);
    Event::assertNotDispatched(Events\TwoFactorEnabled::class);
    expect($factor->confirm($user, 'invalid'))->toBeNull();
    Event::assertNotDispatched(Events\TwoFactorEnabled::class);
    $factor->confirm($user, (new Google2FA)->getCurrentOtp($setup['secret']));
    Event::assertDispatchedTimes(Events\TwoFactorEnabled::class, 1);
    $codes = $factor->rotateRecoveryCodes($user);
    Event::assertDispatchedTimes(Events\RecoveryCodesRegenerated::class, 1);
    Event::assertDispatched(Events\RecoveryCodesRegenerated::class, fn ($event) => ! str_contains(json_encode($event), $codes[0]) && $event->userId === (string) $user->getKey());
    $factor->disable($user);
    $factor->disable($user);
    Event::assertDispatchedTimes(Events\TwoFactorDisabled::class, 1);
});
