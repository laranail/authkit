<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Providers;

use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;
use Simtabi\Laranail\AuthKit\Actions;
use Simtabi\Laranail\AuthKit\Contracts;
use Simtabi\Laranail\AuthKit\Models\Passkey;
use Simtabi\Laranail\AuthKit\Services;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

class AuthKitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/authkit')
            ->publish(
                paths: ['config/laranail/authkit.php' => config_path(path: 'laranail/authkit.php')],
                tag: 'laranail::authkit-config',
            )
            ->publish(
                paths: ['database/migrations/passkeys' => database_path(path: 'migrations')],
                tag: 'laranail::authkit-passkey-migrations',
            );
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(path: $this->packagePath('config/laranail/authkit.php'), key: 'laranail.authkit');

        Passkeys::usePasskeyModel(Passkey::class);

        $this->app->singleton(Services\Turnstile::class, function (): Services\Turnstile {
            return new Services\Turnstile(
                url: (string) config(key: 'laranail.authkit.turnstile.url'),
                secretKey: (string) config(key: 'laranail.authkit.turnstile.secret_key'),
            );
        });

        $this->app->bind(abstract: Contracts\AttemptEmailPasswordLoginInterface::class, concrete: Actions\AttemptEmailPasswordLogin::class);
        $this->app->bind(abstract: Contracts\CheckEmailExistsInterface::class, concrete: Actions\CheckEmailExists::class);
        $this->app->bind(abstract: Contracts\FindUserByEmailInterface::class, concrete: Actions\FindUserByEmail::class);
        $this->app->bind(abstract: Contracts\LoginUserInterface::class, concrete: Actions\LoginUser::class);
        $this->app->bind(abstract: Contracts\LogoutUserInterface::class, concrete: Actions\LogoutUser::class);
        $this->app->bind(abstract: Contracts\IssueTokenForUserInterface::class, concrete: Actions\IssueTokenForUser::class);
        // A singleton because registrations accumulate: every sub-package that contributes a
        // provider does so against the same instance, and a fresh one per resolution would drop
        // whatever registered before it.
        $this->app->singleton(abstract: Contracts\IdentityProviderRegistryInterface::class, concrete: Services\IdentityProviderRegistry::class);
        $this->app->bind(abstract: Contracts\ListBrowserSessionsInterface::class, concrete: Actions\ListBrowserSessions::class);
        $this->app->bind(abstract: Contracts\LogoutOtherBrowserSessionsInterface::class, concrete: Actions\LogoutOtherBrowserSessions::class);
    }

    public function packageBooted(): void
    {
        // The REST API ships with the core, so an API-only or Filament consumer that installs
        // this package alone gets it. A frontend package that would rather mount its own sets
        // laranail.authkit.api.enabled to false.
        $this->loadRoutesFrom($this->packagePath('routes/api.php'));

        $this->configureFortify();
    }

    private function configureFortify(): void
    {
        config()->set(key: 'fortify.guard', value: config(key: 'laranail.authkit.guard', default: 'web'));
        config()->set(key: 'fortify.views', value: config(key: 'laranail.authkit.fortify.views', default: false));
        config()->set(key: 'fortify.features', value: config(key: 'laranail.authkit.fortify.features', default: []));

        Fortify::createUsersUsing(callback: Actions\CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(callback: Actions\UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(callback: Actions\UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(callback: Actions\ResetUserPassword::class);
    }
}
