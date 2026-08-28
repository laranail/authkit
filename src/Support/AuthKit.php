<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

use Simtabi\Laranail\AuthKit\Rules\TurnstileRule;

class AuthKit
{
    public static function guard(): string
    {
        return config(key: 'laranail.authkit.guard', default: 'web');
    }

    /**
     * Resolves a redirect target ahead of this package's own config, or null to defer.
     *
     * @var (\Closure(string): ?string)|null
     */
    protected static ?\Closure $redirectResolver = null;

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
     * @param  (\Closure(string): ?string)|null  $resolver
     */
    public static function resolveRedirectsUsing(?\Closure $resolver): void
    {
        static::$redirectResolver = $resolver;
    }

    public static function redirect(string $key, string $default = '/'): string
    {
        if (static::$redirectResolver !== null) {
            $resolved = (static::$redirectResolver)($key);

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

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
        if (! config(key: 'laranail.authkit.turnstile.enabled', default: false)) {
            return [];
        }

        $route = request()->route();
        if ($route === null || ! in_array('web', (array) $route->middleware(), true)) {
            return [];
        }

        return [
            'required',
            new TurnstileRule,
        ];
    }
}
