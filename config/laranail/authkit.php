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

];
