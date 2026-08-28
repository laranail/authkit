<?php

declare(strict_types=1);

return [
    'guard' => env(key: 'AUTHKIT_GUARD', default: 'web'),

    'user_model' => env(key: 'AUTHKIT_USER_MODEL'),

    'rate_limit' => [
        'max_attempts'  => (int) env(key: 'AUTHKIT_RATE_LIMIT_MAX_ATTEMPTS', default: 5),
        'decay_minutes' => (int) env(key: 'AUTHKIT_RATE_LIMIT_DECAY_MINUTES', default: 1),
    ],

    'turnstile' => [
        'enabled'    => (bool) env(key: 'AUTHKIT_TURNSTILE_ENABLED', default: false),
        'site_key'   => env(key: 'TURNSTILE_SITE_KEY'),
        'secret_key' => env(key: 'TURNSTILE_SECRET_KEY'),
        'url'        => env(key: 'TURNSTILE_URL', default: 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'input'      => 'cf-turnstile-response',
    ],

    'fortify' => [
        'views' => false,

        'features' => [
            'reset-passwords',
            'update-profile-information',
            'update-passwords',
            'email-verification',
            'passkeys',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API tokens
    |--------------------------------------------------------------------------
    |
    | Abilities are the scope an issued token carries. The default is deliberately not ['*']:
    | a wildcard token can do anything its owner can, so a leaked one is a full account
    | compromise rather than a bounded one. Pass explicit abilities to IssueTokenForUser to
    | narrow a token further -- a mobile client that only reads a profile has no business
    | holding a token that can change the password.
    |
    | `expires_after_minutes` gives a token a lifetime of its own. Sanctum's own
    | sanctum.expiration is null by default, which means issued tokens never expire; a token
    | leaked from a log or a backup then stays valid forever. Set null here to defer to
    | Sanctum's setting, which is the only way to genuinely opt out.
    |
    */

    'tokens' => [
        'abilities' => [
            'user:read',
            'user:update-profile',
            'user:update-password',
        ],

        'expires_after_minutes' => (int) env(key: 'AUTHKIT_TOKEN_EXPIRES_AFTER_MINUTES', default: 60 * 24 * 30),
    ],

];
