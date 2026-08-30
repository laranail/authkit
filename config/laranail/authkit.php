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

    /*
    |--------------------------------------------------------------------------
    | REST API
    |--------------------------------------------------------------------------
    |
    | This package is the headless core, so the REST API ships here rather than in the frontend
    | preset: an API-only or Filament consumer that installs the core alone gets the API with it,
    | and never has to pull in Blade scaffolding to obtain a login endpoint.
    |
    | A frontend package that mounts its own API can turn this off and register its own.
    |
    */

    'api' => [
        'enabled'    => (bool) env(key: 'AUTHKIT_API_ENABLED', default: true),
        'prefix'     => env(key: 'AUTHKIT_API_PREFIX', default: 'api/auth'),

        /*
         * Route names are a flat, global registry: a second package -- or the host application
         * itself -- claiming `api.login` silently replaces whichever registered first, and the
         * damage surfaces far away as the wrong controller answering. Names are therefore
         * vendor-scoped by default.
         *
         * Set this to '' to fall back to bare names, if an application already depends on them.
         */
        'name_prefix' => env(key: 'AUTHKIT_API_ROUTE_NAME_PREFIX', default: 'laranail-auth-api.'),
        'middleware' => ['api', 'throttle:60,1'],
    ],

];
