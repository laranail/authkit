<?php

declare(strict_types=1);

trait AccountDeletionFeatureDisabledEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('laranail.authkit.fortify.features', ['reset-passwords']);
    }
}

uses(AccountDeletionFeatureDisabledEnvironment::class);

it('omits deletion when its feature is disabled while retaining the API', function (): void {
    expect(app('router')->has('laranail-auth-api.login'))->toBeTrue()
        ->and(app('router')->has('laranail-auth-api.user.destroy'))->toBeFalse();
    $this->deleteJson('/api/auth/user')->assertNotFound();
});
