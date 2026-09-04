<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Simtabi\Laranail\AuthKit\Support\TokenResult;
use Simtabi\Laranail\AuthKit\Actions\IssueTokenForUser;

it('creates a sanctum token and returns a token result', function (): void {
    $user = User::factory()->create();

    $result = app(IssueTokenForUser::class)->execute(user: $user);

    expect($result)->toBeInstanceOf(TokenResult::class)
        ->and($result->user->getAuthIdentifier())->toBe($user->getAuthIdentifier())
        ->and($result->token)->toBeString()->not->toBeEmpty();
});

it('uses the default token name when none is provided', function (): void {
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user);

    expect($user->tokens)->toHaveCount(1)
        ->and($user->tokens->first()->name)->toBe('api-token');
});

it('uses a custom token name when provided', function (): void {
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user, name: 'mobile-app');

    expect($user->tokens->first()->name)->toBe('mobile-app');
});

it('scopes the token to the given abilities', function (): void {
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user, abilities: ['read', 'write']);

    expect($user->tokens->first()->abilities)->toBe(['read', 'write']);
});

it('defaults to the configured scope rather than a wildcard', function (): void {
    // A wildcard token can do anything its owner can, so a leaked one is a full account
    // compromise rather than a bounded one. The default is a real scope, not '*'.
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user);

    expect($user->tokens->first()->abilities)
        ->toBe(config('laranail.authkit.tokens.abilities'))
        ->not->toBe(['*']);
});

it('gives an issued token an expiry, so a leaked one stops working', function (): void {
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user);

    expect($user->tokens->first()->expires_at)->not->toBeNull();
});

it('defers to sanctum when the configured lifetime is null', function (): void {
    config()->set('laranail.authkit.tokens.expires_after_minutes', null);
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user);

    // Null here means "use sanctum.expiration", which is itself unset in the test app -- the
    // point is that this package stops imposing a lifetime, not that it forces none.
    expect($user->tokens->first()->expires_at)->toBeNull();
});

it('lets a caller narrow a token further than the default', function (): void {
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user, abilities: ['user:read']);

    expect($user->tokens->first()->abilities)->toBe(['user:read']);
});
