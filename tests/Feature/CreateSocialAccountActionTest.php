<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Simtabi\Laranail\AuthKit\Models\Social;
use Simtabi\Laranail\AuthKit\Enums\SocialProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Actions\CreateSocialAccountAction;

it(description: 'creates a social account with morph', closure: function (): void {
    $user = User::factory()->create();

    $socialiteUser = new SocialiteUser();
    $socialiteUser->map(attributes: [
        'id'       => 'google-123',
        'name'     => 'John Doe',
        'nickname' => 'johndoe',
        'email'    => 'john@example.com',
        'avatar'   => 'https://example.com/avatar.jpg',
    ]);
    $socialiteUser->token = 'mock-token';
    $socialiteUser->refreshToken = 'mock-refresh-token';
    $socialiteUser->expiresIn = 3600;

    $action = app(abstract: CreateSocialAccountAction::class);

    $social = $action->execute(
        authenticatable: $user,
        provider: SocialProvider::GOOGLE,
        socialUser: $socialiteUser,
    );

    expect(value: $social)->toBeInstanceOf(class: Social::class)
        ->and(value: $social->socialable_type)->toBe(get_class($user))
        ->and(value: $social->socialable_id)->toBe($user->id)
        ->and(value: $social->provider)->toBe(SocialProvider::GOOGLE)
        ->and(value: $social->provider_id)->toBe('google-123')
        ->and(value: $social->name)->toBe('John Doe')
        ->and(value: $social->nickname)->toBe('johndoe')
        ->and(value: $social->email)->toBe('john@example.com')
        ->and(value: $social->avatar_path)->toBe('https://example.com/avatar.jpg')
        ->and(value: $social->expires_at)->not->toBeNull();
});

it(description: 'morph relationship returns parent model', closure: function (): void {
    $user = User::factory()->create();

    $socialiteUser = new SocialiteUser();
    $socialiteUser->map(attributes: [
        'id'    => 'google-456',
        'email' => 'jane@example.com',
    ]);

    $action = app(abstract: CreateSocialAccountAction::class);

    $social = $action->execute(
        authenticatable: $user,
        provider: SocialProvider::GOOGLE,
        socialUser: $socialiteUser,
    );

    expect(value: $social->socialable->id)->toBe($user->id);
});

it(description: 'stores encrypted tokens', closure: function (): void {
    $user = User::factory()->create();

    $socialiteUser = new SocialiteUser();
    $socialiteUser->map(attributes: [
        'id'    => 'google-789',
        'email' => 'token@example.com',
    ]);
    $socialiteUser->token = 'my-secret-token';
    $socialiteUser->refreshToken = 'my-secret-refresh-token';

    $action = app(abstract: CreateSocialAccountAction::class);

    $social = $action->execute(
        authenticatable: $user,
        provider: SocialProvider::GOOGLE,
        socialUser: $socialiteUser,
    );

    // Reload from DB to check encrypted values
    $fresh = Social::find($social->id);

    expect(value: $fresh->token)->toBe('my-secret-token')
        ->and(value: $fresh->refresh_token)->toBe('my-secret-refresh-token');
});

it(description: 'handles null expires_at', closure: function (): void {
    $user = User::factory()->create();

    $socialiteUser = new SocialiteUser();
    $socialiteUser->map(attributes: [
        'id'    => 'google-no-exp',
        'email' => 'noexpire@example.com',
    ]);
    $socialiteUser->expiresIn = null;

    $action = app(abstract: CreateSocialAccountAction::class);

    $social = $action->execute(
        authenticatable: $user,
        provider: SocialProvider::GOOGLE,
        socialUser: $socialiteUser,
    );

    expect(value: $social->expires_at)->toBeNull();
});

it(description: 'creates social account via factory', closure: function (): void {
    $social = Social::factory()->create();

    expect(value: $social)->toBeInstanceOf(class: Social::class)
        ->and(value: $social->provider)->toBeInstanceOf(class: SocialProvider::class)
        ->and(value: $social->socialable)->not->toBeNull();
});
