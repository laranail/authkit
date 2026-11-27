<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Providers;

use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;
use Simtabi\Laranail\AuthKit\Actions;
use Simtabi\Laranail\AuthKit\Services;
use Simtabi\Laranail\AuthKit\Contracts;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\AuthKit\Models\Passkey;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

class AuthKitServiceProvider extends PackageServiceProvider
{
    /** The vendor-scoped middleware alias that requires verified two-factor authentication. */
    public const string TWO_FACTOR_MIDDLEWARE = 'laranail-authkit-two-factor';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/authkit')
            ->publish(
                paths: ['lang' => lang_path('vendor/laranail/authkit')],
                tag: 'laranail::authkit-translations',
            )
            ->publish(
                paths: ['config/laranail/authkit.php' => config_path(path: 'laranail/authkit.php')],
                tag: 'laranail::authkit-config',
            )
            ->publish(
                paths: ['database/migrations/passkeys' => database_path(path: 'migrations')],
                tag: 'laranail::authkit-passkey-migrations',
            )
            ->publish(
                paths: ['database/migrations/two-factor' => database_path(path: 'migrations')],
                tag: 'laranail::authkit-two-factor-migrations',
            );
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(path: $this->packagePath('config/laranail/authkit.php'), key: 'laranail.authkit');

        // At register time, not boot. Fortify reads fortify.features and fortify.views in its own
        // boot() and mounts its routes there and then, so configuring them from a boot() made the
        // application's route set depend on which provider booted first -- and discovery orders
        // laranail/authkit before laravel/fortify, the opposite of what this suite used to test.
        // Every register() runs before every boot(), so this lands first either way. The preset
        // relies on the same property for Fortify::ignoreRoutes().
        $this->configureFortify();

        Passkeys::usePasskeyModel(Passkey::class);

        $this->app->singleton(Services\Turnstile::class, function (): Services\Turnstile {
            return new Services\Turnstile(
                url: (string) config(key: 'laranail.authkit.turnstile.url'),
                secretKey: (string) config(key: 'laranail.authkit.turnstile.secret_key'),
                http: $this->app->make(\Illuminate\Http\Client\Factory::class),
            );
        });
        $this->app->singleton(Services\TwoFactorAuthentication::class);

        $this->app->bind(abstract: Contracts\AttemptEmailPasswordLoginInterface::class, concrete: Actions\AttemptEmailPasswordLogin::class);
        $this->app->bind(abstract: Contracts\CheckEmailExistsInterface::class, concrete: Actions\CheckEmailExists::class);
        $this->app->bind(abstract: Contracts\FindUserByEmailInterface::class, concrete: Actions\FindUserByEmail::class);
        $this->app->bind(abstract: Contracts\LoginUserInterface::class, concrete: Actions\LoginUser::class);
        $this->app->bind(abstract: Contracts\IdentityLinkingInterface::class, concrete: Services\IdentityLinking::class);
        $this->app->bind(Contracts\DeleteUserInterface::class, Actions\DeleteUser::class);
        $this->app->bind(abstract: Contracts\LogoutUserInterface::class, concrete: Actions\LogoutUser::class);
        $this->app->bind(abstract: Contracts\IssueTokenForUserInterface::class, concrete: Actions\IssueTokenForUser::class);
        $this->app->singleton(Contracts\TokenIssuerRegistryInterface::class, function (): Services\TokenIssuerRegistry {
            $registry = $this->app->make(Services\TokenIssuerRegistry::class);
            $registry->register('sanctum', app(Actions\SanctumTokenIssuer::class));

            return $registry;
        });
        // A singleton because registrations accumulate: every sub-package that contributes a
        // provider does so against the same instance, and a fresh one per resolution would drop
        // whatever registered before it.
        $this->app->singleton(abstract: Contracts\IdentityProviderRegistryInterface::class, concrete: Services\IdentityProviderRegistry::class);
        $this->app->bind(abstract: Contracts\ListBrowserSessionsInterface::class, concrete: Actions\ListBrowserSessions::class);
        $this->app->bind(abstract: Contracts\LogoutOtherBrowserSessionsInterface::class, concrete: Actions\LogoutOtherBrowserSessions::class);
    }

    public function packageBooted(): void
    {
        $this->loadTranslationsFrom($this->packagePath('lang'), 'laranail/authkit');

        // The REST API ships with the core, so an API-only or Filament consumer that installs
        // this package alone gets it. A frontend package that would rather mount its own sets
        // laranail.authkit.api.enabled to false.
        $this->loadRoutesFrom($this->packagePath('routes/api.php'));

        $router = $this->app->make('router');

        $router->aliasMiddleware(
            self::TWO_FACTOR_MIDDLEWARE,
            \Simtabi\Laranail\AuthKit\Http\Middleware\RequireTwoFactorAuthentication::class,
        );

        // Deprecated bare alias, kept so routes written against it keep enforcing two-factor.
        // It logs once per process naming the scoped alias above.
        $router->aliasMiddleware(
            \Simtabi\Laranail\AuthKit\Http\Middleware\DeprecatedTwoFactorAlias::ALIAS,
            \Simtabi\Laranail\AuthKit\Http\Middleware\DeprecatedTwoFactorAlias::class,
        );

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
