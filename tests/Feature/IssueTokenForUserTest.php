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

it('defaults to wildcard abilities when none are specified', function (): void {
    $user = User::factory()->create();

    app(IssueTokenForUser::class)->execute(user: $user);

    expect($user->tokens->first()->abilities)->toBe(['*']);
});
