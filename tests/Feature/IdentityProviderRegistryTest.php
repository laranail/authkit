<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Support\IdentityProvider;
use Simtabi\Laranail\AuthKit\Contracts\IdentityProviderRegistryInterface;

it('lets a sub-package contribute a provider without editing this package', function (): void {
    $registry = app(IdentityProviderRegistryInterface::class);

    $registry->register(new IdentityProvider(
        slug: 'okta',
        label: 'Okta',
        assertsEmailVerified: true,
    ));

    expect($registry->has('okta'))->toBeTrue()
        ->and($registry->get('okta')?->label)->toBe('Okta')
        ->and($registry->slugs())->toContain('okta');
});

it('keeps registrations across resolutions', function (): void {
    // A fresh instance per resolution would drop whatever registered before it, so every
    // sub-package after the first would silently vanish.
    app(IdentityProviderRegistryInterface::class)->register(new IdentityProvider(
        slug: 'okta',
        label: 'Okta',
        assertsEmailVerified: true,
    ));

    expect(app(IdentityProviderRegistryInterface::class)->has('okta'))->toBeTrue();
});

it('never treats a provider that asserts nothing as verified, whatever the payload claims', function (): void {
    $provider = new IdentityProvider(slug: 'legacy', label: 'Legacy', assertsEmailVerified: false);

    expect($provider->hasVerifiedEmail(['email_verified' => true]))->toBeFalse();
});

it('reads a provider’s own claim shape when it supplies one', function (): void {
    $provider = new IdentityProvider(
        slug: 'acme',
        label: 'Acme',
        assertsEmailVerified: true,
        verifiedEmailResolver: fn (array $raw): bool => ($raw['mail_confirmed'] ?? null) === 'yes',
    );

    expect($provider->hasVerifiedEmail(['mail_confirmed' => 'yes']))->toBeTrue()
        ->and($provider->hasVerifiedEmail(['email_verified' => true]))->toBeFalse();
});

it('defaults to the OpenID-style boolean claim when no resolver is given', function (): void {
    $provider = new IdentityProvider(slug: 'oidc', label: 'OIDC', assertsEmailVerified: true);

    expect($provider->hasVerifiedEmail(['email_verified' => 'true']))->toBeTrue()
        ->and($provider->hasVerifiedEmail(['email_verified' => 'false']))->toBeFalse()
        ->and($provider->hasVerifiedEmail([]))->toBeFalse();
});
