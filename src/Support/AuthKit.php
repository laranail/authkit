<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

use Closure;
use Simtabi\Laranail\AuthKit\Rules\TurnstileRule;

class AuthKit
{
    /**
     * Social login, as a feature an application declares rather than a config flag it flips.
     *
     * It lives here rather than in `laranail/authkit-social-login` because the packages that need
     * to agree on the name cannot see each other: the preset only *suggests* the social package,
     * and the social package cannot reach into the preset's enum. This is the common base both
     * require, which is the whole reason `hasFeature()` exists.
     *
     * Fortify receives this list verbatim and ignores a feature it does not know, so naming a
     * non-Fortify concern here costs nothing at runtime.
     */
    public const SOCIAL_LOGIN = 'social-login';

    /**
     * Resolves a redirect target ahead of this package's own config, or null to defer.
     *
     * @var (Closure(string): ?string)|null
     */
    protected static ?Closure $redirectResolver = null;

    public static function guard(): string
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return config(key: 'laranail.authkit.guard', default: 'web');
    }

    /**
     * The request input a password login reads its identifier from.
     *
     * Defaults to `email`, so a form or API client written against this package before usernames
     * existed keeps working untouched.
     */
    public static function loginField(): string
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        $field = config(key: 'laranail.authkit.login.field', default: 'email');

        return is_string($field) && $field !== '' ? $field : 'email';
    }

    /**
     * The user columns a login identifier is looked up against, in the order they are tried.
     *
     * Email only by default. The list is the single source for both the lookup and the validation
     * rules, so a rule cannot describe a mode the lookup does not implement.
     *
     * @return list<string>
     */
    public static function loginIdentifiers(): array
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        $identifiers = config(key: 'laranail.authkit.login.identifiers', default: ['email']);

        if (! is_array($identifiers)) {
            return ['email'];
        }

        $identifiers = array_values(array_filter(
            $identifiers,
            static fn (mixed $column): bool => is_string($column) && $column !== '',
        ));

        return $identifiers === [] ? ['email'] : $identifiers;
    }

    /**
     * Whether the only identifier configured is an email address.
     *
     * The discriminator for validation: an address can be validated as one, and anything wider
     * cannot, because a username is not an address.
     */
    public static function loginIsEmailOnly(): bool
    {
        return self::loginIdentifiers() === ['email'];
    }

    /**
     * Let a frontend package own the redirect targets.
     *
     * A package that ships the routes and views -- laranail/authkit-preset, or an application's
     * own layer -- has its own configuration key, and a user setting it reasonably expects it to
     * take effect. Without a seam the core silently reads only its own key, so the frontend's is
     * inert: configured, documented, and doing nothing.
     *
     * The resolver is consulted at read time rather than copied at boot, so a value changed after
     * the container has booted is still honoured. Returning null defers to this package's config.
     *
     * @param (Closure(string): ?string)|null $resolver
     */
    public static function resolveRedirectsUsing(?Closure $resolver): void
    {
        static::$redirectResolver = $resolver;
    }

    /**
     * Whether a Fortify-style feature is switched on in this package's own config.
     *
     * The frontend preset has its own richer feature list; this is the core's, so the API can
     * gate an endpoint without depending on a package that depends on it.
     */
    public static function hasFeature(string $feature): bool
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return in_array($feature, (array) config(key: 'laranail.authkit.fortify.features', default: []), strict: true);
    }

    /**
     * The single switch for social login's user-facing surface: its buttons and its web routes.
     *
     * `laranail/authkit-social-login` reads this instead of a `web.enabled` flag of its own, so an
     * application declares the capability once, in the same list as every other AuthKit feature,
     * rather than in a second place that could disagree with the first.
     */
    public static function socialLoginEnabled(): bool
    {
        return self::hasFeature(self::SOCIAL_LOGIN);
    }

    public static function apiEnabled(): bool
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return (bool) config(key: 'laranail.authkit.api.enabled', default: true);
    }

    /**
     * The two-factor feature switch, kept as public API with no caller inside these packages.
     *
     * It used to be half of the API login's challenge condition, which made a config flag able to
     * waive a second factor a user had already enrolled -- and since its default is `false`, a
     * core-only install waived it always. `Api\LoginController` now asks
     * `TwoFactorAuthentication::enabled()` and nothing else, the way the preset's browser login
     * always has.
     *
     * The two-factor routes have never been gated on this key, so nothing here reads it now.
     * Retained because an application may; what it ought to gate is a decision for its owner, not
     * something to settle by deleting the method.
     */
    public static function twoFactorEnabled(): bool
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return (bool) config(key: 'laranail.authkit.two_factor.enabled', default: false);
    }

    public static function apiPrefix(): string
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return (string) config(key: 'laranail.authkit.api.prefix', default: 'api/auth');
    }

    /**
     * The prefix every API route name carries.
     *
     * Bare names such as `api.login` sit in a flat global registry that the host application and
     * every other package share, so a second claimant silently wins.
     */
    public static function apiRouteNamePrefix(): string
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return (string) config(key: 'laranail.authkit.api.name_prefix', default: 'laranail-auth-api.');
    }

    /** @return array<int, string> */
    public static function apiMiddleware(): array
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return (array) config(key: 'laranail.authkit.api.middleware', default: ['api']);
    }

    /**
     * Authentication middleware for API endpoints that accept AuthKit-issued bearer tokens.
     * Multiple guards let Sanctum and Passport coexist without changing the default issuer.
     */
    public static function apiTokenMiddleware(): string
    {
        $guards = array_values(array_filter(
            // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
            (array) config(key: 'laranail.authkit.api.token_guards', default: ['sanctum']),
            is_string(...),
        ));

        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        if (config(key: 'laranail.authkit-oauth.enabled', default: false)) {
            // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
            $guards[] = (string) config(key: 'laranail.authkit-oauth.passport.guard', default: 'passport');
        }

        return 'auth:' . implode(',', array_unique($guards ?: ['sanctum']));
    }

    public static function redirect(string $key, string $default = '/'): string
    {
        if (static::$redirectResolver !== null) {
            $resolved = (static::$redirectResolver)($key);

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        return config(key: "laranail.authkit.redirects.{$key}", default: $default);
    }

    public static function afterLoginRedirect(): string
    {
        return self::redirect('after_login', '/dashboard');
    }

    public static function afterRegistrationRedirect(): string
    {
        return self::redirect('after_registration', '/dashboard');
    }

    public static function afterLogoutRedirect(): string
    {
        return self::redirect('after_logout', '/');
    }

    public static function afterPasswordResetRedirect(): string
    {
        return self::redirect('after_password_reset', '/login');
    }

    public static function afterEmailVerificationRedirect(): string
    {
        return self::redirect('after_email_verification', '/dashboard?verified=1');
    }

    public static function turnstileRules(): array
    {
        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        if (! config(key: 'laranail.authkit.turnstile.enabled', default: false)) {
            return [];
        }

        // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
        $route = request()->route();
        if ($route === null || ! in_array('web', (array) $route->middleware(), true)) {
            return [];
        }

        return [
            'required',
            // @facade-call-exempt: Public static compatibility API resolves current configuration/request at call time, never retaining request state.
            app(TurnstileRule::class),
        ];
    }
}
