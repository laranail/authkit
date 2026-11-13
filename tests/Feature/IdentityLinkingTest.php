<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Workbench\App\Models\User;
use Illuminate\Session\ArraySessionHandler;
use Simtabi\Laranail\AuthKit\Services\IdentityLinking;
use Simtabi\Laranail\AuthKit\Enums\IdentityLinkFailure;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException;
use Simtabi\Laranail\AuthKit\Contracts\IdentityLinkingInterface;

function linkSession(?string $id = null, bool $confirmed = true): Store
{
    $store = new Store('authkit_link_test', new ArraySessionHandler(120), $id ?? Str::random(40));
    $store->start();

    if ($confirmed) {
        $store->put('auth.password_confirmed_at', time());
    }

    return $store;
}

function linkRequest(?Store $session = null, array $params = []): Request
{
    $request = Request::create(uri: '/account/connections', method: 'POST', parameters: $params);

    if ($session !== null) {
        $request->setLaravelSession($session);
    }

    return $request;
}

function linking(): IdentityLinking
{
    return app(IdentityLinking::class);
}

function failure(callable $callback): IdentityLinkFailure
{
    try {
        $callback();
    } catch (IdentityLinkException $exception) {
        return $exception->failure;
    }

    throw new RuntimeException('Expected the attempt to be refused, and it was not.');
}

it('binds the contract to the service', function (): void {
    expect(app(IdentityLinkingInterface::class))->toBeInstanceOf(IdentityLinking::class);
});

/* ---------------------------------------------------------------- reauthentication */

it('refuses to start a link without a recently confirmed credential', function (): void {
    $user = User::factory()->create();

    $reason = failure(fn () => linking()->issueIntent(
        request: linkRequest(linkSession(confirmed: false)),
        user: $user,
        guard: 'web',
        provider: 'github',
    ));

    expect($reason)->toBe(IdentityLinkFailure::RecentAuthenticationRequired);
});

it('refuses to start a link once the confirmation has aged out', function (): void {
    config()->set('auth.password_timeout', 60);
    $user = User::factory()->create();

    $session = linkSession(confirmed: false);
    $session->put('auth.password_confirmed_at', time() - 120);

    $reason = failure(fn () => linking()->issueIntent(
        request: linkRequest($session),
        user: $user,
        guard: 'web',
        provider: 'github',
    ));

    expect($reason)->toBe(IdentityLinkFailure::RecentAuthenticationRequired);
});

it('starts a link for a session that has confirmed a password', function (): void {
    $user = User::factory()->create();

    $token = linking()->issueIntent(
        request: linkRequest(linkSession()),
        user: $user,
        guard: 'web',
        provider: 'github',
    );

    expect($token)->toBeString()->and(strlen($token))->toBe(64);
});

it('lets a client with no session prove its password on the request', function (): void {
    $password = Str::random(16);
    $user = User::factory()->create(['password' => bcrypt($password)]);

    $token = linking()->issueIntent(
        request: linkRequest(params: ['password' => $password]),
        user: $user,
        guard: 'web',
        provider: 'github',
    );

    expect($token)->toBeString()->and(strlen($token))->toBe(64);
});

it('refuses a client with no session and the wrong password', function (): void {
    $password = Str::random(16);
    expect(TestCredentials::password('wrong'))->not->toBe($password);

    $user = User::factory()->create(['password' => bcrypt($password)]);

    $reason = failure(fn () => linking()->issueIntent(
        request: linkRequest(params: ['password' => TestCredentials::password('wrong')]),
        user: $user,
        guard: 'web',
        provider: 'github',
    ));

    expect($reason)->toBe(IdentityLinkFailure::RecentAuthenticationRequired);
});

/* ------------------------------------------------------------------- the intent */

it('completes a link for the user and context that started it', function (): void {
    $user = User::factory()->create();
    $session = linkSession();

    $token = linking()->issueIntent(linkRequest($session), $user, 'web', 'github');

    $intent = linking()->consumeIntent(linkRequest($session), $token, $user, 'web', 'github');

    expect($intent->userId)->toBe((string) $user->getAuthIdentifier())
        ->and($intent->provider)->toBe('github')
        ->and($intent->guard)->toBe('web');
});

it('never signs anyone in while completing a link', function (): void {
    $user = User::factory()->create();
    $session = linkSession();

    $token = linking()->issueIntent(linkRequest($session), $user, 'web', 'github');
    linking()->consumeIntent(linkRequest($session), $token, $user, 'web', 'github');

    expect(auth()->guard('web')->check())->toBeFalse();
});

it('spends the intent exactly once', function (): void {
    $user = User::factory()->create();
    $session = linkSession();

    $token = linking()->issueIntent(linkRequest($session), $user, 'web', 'github');
    linking()->consumeIntent(linkRequest($session), $token, $user, 'web', 'github');

    $reason = failure(fn () => linking()->consumeIntent(linkRequest($session), $token, $user, 'web', 'github'));

    expect($reason)->toBe(IdentityLinkFailure::IntentInvalid);
});

it('rejects a token that was never issued', function (): void {
    $user = User::factory()->create();

    $reason = failure(fn () => linking()->consumeIntent(
        linkRequest(linkSession()),
        bin2hex(random_bytes(32)),
        $user,
        'web',
        'github',
    ));

    expect($reason)->toBe(IdentityLinkFailure::IntentInvalid);
});

it('refuses an intent presented by a different user', function (): void {
    $initiator = User::factory()->create();
    $attacker = User::factory()->create();
    $session = linkSession();

    $token = linking()->issueIntent(linkRequest($session), $initiator, 'web', 'github');

    $reason = failure(fn () => linking()->consumeIntent(linkRequest($session), $token, $attacker, 'web', 'github'));

    expect($reason)->toBe(IdentityLinkFailure::IntentMismatch);
});

it('refuses a callback that lands on a different session', function (): void {
    $user = User::factory()->create();

    $token = linking()->issueIntent(linkRequest(linkSession()), $user, 'web', 'github');

    $reason = failure(fn () => linking()->consumeIntent(linkRequest(linkSession()), $token, $user, 'web', 'github'));

    expect($reason)->toBe(IdentityLinkFailure::IntentMismatch);
});

it('refuses an intent completed against a different provider', function (): void {
    $user = User::factory()->create();
    $session = linkSession();

    $token = linking()->issueIntent(linkRequest($session), $user, 'web', 'github');

    $reason = failure(fn () => linking()->consumeIntent(linkRequest($session), $token, $user, 'web', 'gitlab'));

    expect($reason)->toBe(IdentityLinkFailure::IntentMismatch);
});

/* ------------------------------------------------------------------- ownership */

it('allows an identity nobody owns', function (): void {
    $user = User::factory()->create();

    linking()->assertLinkable($user, null);
})->throwsNoExceptions();

it('is idempotent for an identity this user already owns', function (): void {
    $user = User::factory()->create();

    linking()->assertLinkable($user, $user);
})->throwsNoExceptions();

it('refuses an identity owned by another user, and transfers nothing', function (): void {
    $user = User::factory()->create();
    $owner = User::factory()->create();

    $reason = failure(fn () => linking()->assertLinkable($user, $owner));

    expect($reason)->toBe(IdentityLinkFailure::IdentityOwnedByAnotherUser)
        ->and($owner->fresh()->getAuthIdentifier())->toBe($owner->getAuthIdentifier());
});

/* -------------------------------------------------------------------- structure */

/*
 * "Do not treat a matching email as authorization to link accounts" is a property of the shape,
 * not of a branch somebody has to remember. The surface has nowhere to put an address, so this
 * asserts the shape rather than the behaviour -- a parameter named for one would fail here before
 * any logic could use it.
 */
it('offers no way to pass an email address into a link', function (): void {
    $reflection = new ReflectionClass(IdentityLinking::class);
    $checked = 0;

    foreach ($reflection->getMethods() as $method) {
        foreach ($method->getParameters() as $parameter) {
            $checked++;
            expect(Str::lower($parameter->getName()))->not->toContain('email');
        }
    }

    foreach ((new ReflectionClass(Simtabi\Laranail\AuthKit\Support\LinkIntent::class))->getProperties() as $property) {
        $checked++;
        expect(Str::lower($property->getName()))->not->toContain('email');
    }

    expect($checked)->toBeGreaterThan(15);
});
