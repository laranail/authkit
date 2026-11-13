<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Workbench\App\Models\User;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Actions\AttemptEmailPasswordLogin;
use Simtabi\Laranail\AuthKit\Http\Requests\AttemptEmailPasswordLoginRequest;

function identifierRequest(array $data = [], ?string $ip = null): Request
{
    $request = Request::create(uri: '/login', method: 'POST', parameters: $data);

    if ($ip !== null) {
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set('X-Forwarded-For', $ip);
    }

    return $request;
}

function acceptUsernames(): void
{
    config()->set('laranail.authkit.login.identifiers', ['email', 'username']);
    config()->set('laranail.authkit.login.field', 'login');
}

it('ships email as the only identifier', function (): void {
    expect(AuthKit::loginIdentifiers())->toBe(['email'])
        ->and(AuthKit::loginField())->toBe('email')
        ->and(AuthKit::loginIsEmailOnly())->toBeTrue();
});

it('validates the identifier as an address while email is the only one', function (): void {
    $rules = (new AttemptEmailPasswordLoginRequest)->rules();

    expect($rules)->toHaveKey('email')
        ->and($rules['email'])->toBe(['required', 'string', 'email']);
});

it('stops validating the identifier as an address once usernames are accepted', function (): void {
    acceptUsernames();

    $rules = (new AttemptEmailPasswordLoginRequest)->rules();

    expect($rules)->toHaveKey('login')
        ->and($rules['login'])->not->toContain('email')
        ->and($rules)->not->toHaveKey('email');
});

it('refuses a username while email is the only identifier', function (): void {
    $password = Str::random(16);

    User::factory()->create([
        'username' => 'ada',
        'email'    => 'ada@example.com',
        'password' => bcrypt($password),
    ]);

    $result = app(AttemptEmailPasswordLogin::class)->execute(
        request: identifierRequest(['email' => 'ada', 'password' => $password]),
        guard: 'web',
    );

    expect($result->isPassed())->toBeFalse();
});

it('logs a user in by username once configured', function (): void {
    acceptUsernames();
    $password = Str::random(16);

    $user = User::factory()->create([
        'username' => 'ada',
        'email'    => 'ada@example.com',
        'password' => bcrypt($password),
    ]);

    $result = app(AttemptEmailPasswordLogin::class)->execute(
        request: identifierRequest(['login' => 'ada', 'password' => $password]),
        guard: 'web',
    );

    expect($result->isPassed())->toBeTrue()
        ->and($result->user?->getAuthIdentifier())->toBe($user->getAuthIdentifier());
});

it('still logs a user in by email once usernames are also accepted', function (): void {
    acceptUsernames();
    $password = Str::random(16);

    $user = User::factory()->create([
        'username' => 'ada',
        'email'    => 'ada@example.com',
        'password' => bcrypt($password),
    ]);

    $result = app(AttemptEmailPasswordLogin::class)->execute(
        request: identifierRequest(['login' => 'ada@example.com', 'password' => $password]),
        guard: 'web',
    );

    expect($result->isPassed())->toBeTrue()
        ->and($result->user?->getAuthIdentifier())->toBe($user->getAuthIdentifier());
});

it('rejects an identifier that matches no account', function (): void {
    acceptUsernames();

    User::factory()->create([
        'username' => 'ada',
        'email'    => 'ada@example.com',
        'password' => bcrypt(Str::random(16)),
    ]);

    $result = app(AttemptEmailPasswordLogin::class)->execute(
        request: identifierRequest(['login' => 'grace', 'password' => TestCredentials::password()]),
        guard: 'web',
    );

    expect($result->isPassed())->toBeFalse()
        ->and($result->status)->toBe(AuthStatus::Failed);
});

it('rejects a known username with the wrong password', function (): void {
    acceptUsernames();
    $password = Str::random(16);

    expect(TestCredentials::password('wrong'))->not->toBe($password);

    User::factory()->create([
        'username' => 'ada',
        'email'    => 'ada@example.com',
        'password' => bcrypt($password),
    ]);

    $result = app(AttemptEmailPasswordLogin::class)->execute(
        request: identifierRequest(['login' => 'ada', 'password' => TestCredentials::password('wrong')]),
        guard: 'web',
    );

    expect($result->isPassed())->toBeFalse();
});

it('throttles repeated failures against a username', function (): void {
    acceptUsernames();
    config()->set('laranail.authkit.rate_limit.max_attempts', 1);

    User::factory()->create([
        'username' => 'ada',
        'email'    => 'ada@example.com',
        'password' => bcrypt(Str::random(16)),
    ]);

    $action = app(AttemptEmailPasswordLogin::class);
    $request = identifierRequest(['login' => 'ada', 'password' => TestCredentials::password('wrong')]);

    $action->execute(request: $request, guard: 'web');
    $result = $action->execute(request: $request, guard: 'web');

    expect($result->status)->toBe(AuthStatus::Throttled);
});

/*
 * The identifier spaces can overlap: nothing stops one account's username from being another
 * account's address. Checking the password against each candidate rather than only against the
 * first row found is what keeps the username's owner able to sign in.
 */
it('signs in the username owner when a username equals another account address', function (): void {
    acceptUsernames();
    $owner = Str::random(16);
    $other = Str::random(16);

    expect($owner)->not->toBe($other);

    $addressee = User::factory()->create([
        'username' => 'ada',
        'email'    => 'grace@example.com',
        'password' => bcrypt($other),
    ]);

    $usernameOwner = User::factory()->create([
        'username' => 'grace@example.com',
        'email'    => 'hopper@example.com',
        'password' => bcrypt($owner),
    ]);

    $result = app(AttemptEmailPasswordLogin::class)->execute(
        request: identifierRequest(['login' => 'grace@example.com', 'password' => $owner]),
        guard: 'web',
    );

    expect($result->isPassed())->toBeTrue()
        ->and($result->user?->getAuthIdentifier())->toBe($usernameOwner->getAuthIdentifier())
        ->and($result->user?->getAuthIdentifier())->not->toBe($addressee->getAuthIdentifier());
});
