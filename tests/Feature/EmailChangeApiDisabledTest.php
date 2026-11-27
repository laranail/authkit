<?php

declare(strict_types=1);

trait EmailChangeApiDisabledTestEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laranail.authkit.api.enabled', false);
    }
}

uses(EmailChangeApiDisabledTestEnvironment::class);

it('omits email change routes when the core API is disabled', function (): void {
    foreach (['show', 'store', 'verify', 'destroy'] as $operation) {
        expect(app('router')->has('laranail-auth-api.user-email-change.' . $operation))->toBeFalse();
    }
});
