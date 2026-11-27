<?php

declare(strict_types=1);

trait EmailChangeFeatureDisabledTestEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laranail.authkit.fortify.features', ['reset-passwords']);
    }
}

uses(EmailChangeFeatureDisabledTestEnvironment::class);

it('omits email change routes when profile updates are disabled', function (): void {
    foreach (['show', 'store', 'verify', 'destroy'] as $operation) {
        expect(app('router')->has('laranail-auth-api.user-email-change.' . $operation))->toBeFalse();
    }
});
