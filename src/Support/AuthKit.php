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

    public static function redirect(string $key, string $default = '/'): string
    {
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
            new TurnstileRule(),
        ];
    }
}
