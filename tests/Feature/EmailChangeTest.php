<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Events\EmailChanged;
use Simtabi\Laranail\AuthKit\Services\EmailChange;
use Simtabi\Laranail\AuthKit\Events\EmailChangeCancelled;
use Simtabi\Laranail\AuthKit\Events\EmailChangeRequested;
use Simtabi\Laranail\AuthKit\Notifications\VerifyEmailChange;

beforeEach(function (): void {
    Notification::fake();
});

function emailChangeProof(): string
{
    $notification = Notification::sent(new Illuminate\Notifications\AnonymousNotifiable, VerifyEmailChange::class)->last();
    parse_str(parse_url($notification->url, PHP_URL_QUERY), $query);

    return $query['token'];
}

it('keeps the active verified email until the new address is proved', function (): void {
    Event::fake([EmailChangeRequested::class, EmailChanged::class]);
    $user = User::factory()->create(['email' => 'old@example.test', 'email_verified_at' => now()->subDay()]);
    $verifiedAt = $user->email_verified_at;
    $changes = app(EmailChange::class);
    $changes->request($user, ' NEW@example.test ');

    expect($user->fresh()->email)->toBe('old@example.test')
        ->and($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue()
        ->and($changes->pending($user)['email'])->toBe('new@example.test');
    Notification::assertSentOnDemand(VerifyEmailChange::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.test');
    Event::assertDispatchedTimes(EmailChangeRequested::class, 1);
    Event::assertNotDispatched(EmailChanged::class);

    $changes->confirm($user, emailChangeProof());

    expect($user->fresh()->email)->toBe('new@example.test')
        ->and($user->fresh()->email_verified_at->greaterThan($verifiedAt))->toBeTrue()
        ->and($changes->pending($user))->toBeNull();
    Event::assertDispatchedTimes(EmailChanged::class, 1);
    expect(fn () => $changes->confirm($user, emailChangeProof()))->toThrow(ValidationException::class);
});

it('rejects invalid expired superseded cancelled and foreign-account proofs', function (string $reason): void {
    $user = User::factory()->create(['email' => 'old@example.test']);
    $changes = app(EmailChange::class);
    $changes->request($user, 'new@example.test');
    $proof = emailChangeProof();
    match ($reason) {
        'invalid'    => $proof = bin2hex(random_bytes(32)),
        'expired'    => $this->travel(61)->minutes(),
        'superseded' => $changes->request($user, 'later@example.test'),
        'cancelled'  => $changes->cancel($user),
        'foreign'    => $user = User::factory()->create(),
        'changed'    => $user->forceFill(['email' => 'elsewhere@example.test'])->save(),
        'flushed'    => Cache::flush(),
    };
    $before = $user->fresh()->email;
    Event::fake([EmailChanged::class]);

    expect(fn () => $changes->confirm($user, $proof))->toThrow(ValidationException::class)
        ->and($user->fresh()->email)->toBe($before);
    Event::assertNotDispatched(EmailChanged::class);
})->with(['invalid', 'expired', 'superseded', 'cancelled', 'foreign', 'changed', 'flushed']);

it('checks address uniqueness case insensitively both at request and confirmation', function (bool $takenAfterRequest): void {
    $user = User::factory()->create();
    $changes = app(EmailChange::class);
    if ($takenAfterRequest) {
        $changes->request($user, 'taken@example.test');
    }
    User::factory()->create(['email' => 'TAKEN@example.test']);
    $before = $user->email;

    expect(fn () => $takenAfterRequest
        ? $changes->confirm($user, emailChangeProof())
        : $changes->request($user, 'taken@example.test'))->toThrow(ValidationException::class)
        ->and($user->fresh()->email)->toBe($before);
})->with([false, true]);

it('only accepts the most recently requested address', function (): void {
    $user = User::factory()->create();
    $changes = app(EmailChange::class);
    $changes->request($user, 'first@example.test');
    $changes->request($user, 'second@example.test');
    $changes->confirm($user, emailChangeProof());
    expect($user->fresh()->email)->toBe('second@example.test');
});

it('rejects invalid and unchanged target addresses', function (string $email): void {
    $user = User::factory()->create(['email' => 'old@example.test']);
    expect(fn () => app(EmailChange::class)->request($user, $email))->toThrow(ValidationException::class);
    Notification::assertNothingSent();
})->with(['not-an-address', '', 'OLD@example.test']);

it('renders a translated notification and supports an application verification URL', function (): void {
    config(['laranail.authkit.email_change.verification_url' => 'https://client.example.test/change?token={token}', 'laranail.authkit.email_change.expires_after_minutes' => 15]);
    $changes = app(EmailChange::class);
    $changes->request(User::factory()->create(), 'new@example.test');
    $notification = Notification::sent(new Illuminate\Notifications\AnonymousNotifiable, VerifyEmailChange::class)->last();
    expect($notification->url)->toStartWith('https://client.example.test/change?token=')
        ->and($notification->minutes)->toBe(15)
        ->and($notification->toMail(new Illuminate\Notifications\AnonymousNotifiable)->subject)->toBe('Verify your new email address');
});

it('exposes pending request verification and cancellation to authenticated API clients', function (): void {
    $user = User::factory()->create(['email' => 'old@example.test']);
    $token = $user->createToken('email-change')->plainTextToken;
    $this->withToken($token)->postJson('/api/auth/user/email-change', ['email' => 'new@example.test'])
        ->assertOk()->assertJsonPath('data.pending.email', 'new@example.test')->assertHeader('Cache-Control', 'no-store, private');
    $this->withToken($token)->getJson('/api/auth/user/email-change')->assertOk()->assertJsonPath('data.pending.email', 'new@example.test');
    $this->withToken($token)->postJson('/api/auth/user/email-change/verify', ['token' => emailChangeProof()])->assertOk()->assertJsonPath('data.pending', null);
    expect($user->fresh()->email)->toBe('new@example.test');
    $this->withToken($token)->postJson('/api/auth/user/email-change', ['email' => 'cancel@example.test'])->assertOk();
    Event::fake([EmailChangeCancelled::class]);
    $this->withToken($token)->deleteJson('/api/auth/user/email-change')->assertOk()->assertJsonPath('data.pending', null);
    Event::assertDispatchedTimes(EmailChangeCancelled::class, 1);
});

it('requires API authentication', function (string $method, string $path): void {
    $this->{$method}('/api/auth/user/email-change' . $path)->assertUnauthorized();
})->with([['getJson', ''], ['postJson', ''], ['deleteJson', ''], ['getJson', '/verify'], ['postJson', '/verify']]);

it('preserves the active address when the profile API requests a change', function (): void {
    $user = User::factory()->create(['email' => 'old@example.test']);
    $this->withToken($user->createToken('profile')->plainTextToken)
        ->putJson('/api/auth/user/profile-information', ['name' => 'Updated', 'email' => 'new@example.test'])->assertSuccessful();
    expect($user->fresh()->name)->toBe('Updated')->and($user->fresh()->email)->toBe('old@example.test')
        ->and(app(EmailChange::class)->pending($user)['email'])->toBe('new@example.test');
});

it('isolates pending records for accounts on different tables with the same identifier', function (): void {
    $user = User::factory()->create();
    $other = new User;
    $other->setTable('email_change_other_users');
    $other->forceFill(['id' => $user->getKey(), 'email' => 'other@example.test'])->save();
    $changes = app(EmailChange::class);
    $changes->request($user, 'new@example.test');
    expect($changes->pending($other))->toBeNull();
    expect(fn () => $changes->confirm($other, emailChangeProof()))->toThrow(ValidationException::class);
    expect($other->fresh()->email)->toBe('other@example.test');
});

it('invalidates pending requests when the encryption key changes', function (): void {
    $user = User::factory()->create();
    $changes = app(EmailChange::class);
    $changes->request($user, 'new@example.test');
    $proof = emailChangeProof();
    $cipher = config('app.cipher');
    app()->instance(Illuminate\Contracts\Encryption\Encrypter::class, new Illuminate\Encryption\Encrypter(
        Illuminate\Encryption\Encrypter::generateKey($cipher),
        $cipher,
    ));
    $changes = app(EmailChange::class);
    expect($changes->pending($user))->toBeNull();
    expect(fn () => $changes->confirm($user, $proof))->toThrow(ValidationException::class);
});

it('accepts a default emailed verification URL with the requesting account bearer token', function (): void {
    $user = User::factory()->create();
    app(EmailChange::class)->request($user, 'new@example.test');
    $url = Notification::sent(new Illuminate\Notifications\AnonymousNotifiable, VerifyEmailChange::class)->last()->url;
    $this->withToken($user->createToken('verification')->plainTextToken)->getJson($url)->assertOk();
    expect($user->fresh()->email)->toBe('new@example.test');
});

it('throttles email change requests independently of the API group counter', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('throttling')->plainTextToken;
    for ($i = 0; $i < 6; $i++) {
        $this->withToken($token)->getJson('/api/auth/user/email-change')->assertOk();
    }
    $this->withToken($token)->postJson('/api/auth/user/email-change', ['email' => 'new@example.test'])->assertTooManyRequests();
    $this->withToken($token)->putJson('/api/auth/user/profile-information', ['name' => 'Changed', 'email' => 'new@example.test'])->assertTooManyRequests();
    Notification::assertNothingSent();
});
