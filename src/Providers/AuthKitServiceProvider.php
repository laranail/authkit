<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Providers;

use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\AuthKit\Actions;
use Simtabi\Laranail\AuthKit\Services;
use Simtabi\Laranail\AuthKit\Contracts;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\AuthKit\Models\Passkey;
use SocialiteProviders\Manager\SocialiteWasCalled;
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
                paths: ['database/migrations/social' => database_path(path: 'migrations')],
                tag: 'laranail::authkit-social-migrations',
            )
            ->publish(
                paths: ['database/migrations/passkeys' => database_path(path: 'migrations')],
                tag: 'laranail::authkit-passkey-migrations',
            );
    }

    public function packageRegistered(): void
    {
        $this->mergeConfigFrom(path: __DIR__.'/../../config/laranail/authkit.php', key: 'laranail.authkit');

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

        $this->app->bind(abstract: Contracts\SocialRedirectActionInterface::class, concrete: Actions\SocialRedirectAction::class);
        $this->app->bind(abstract: Contracts\SocialCallbackActionInterface::class, concrete: Actions\SocialCallbackAction::class);
        $this->app->bind(abstract: Contracts\CreateSocialAccountActionInterface::class, concrete: Actions\CreateSocialAccountAction::class);
        $this->app->bind(abstract: Contracts\ResolveSocialIdentityInterface::class, concrete: Actions\ResolveSocialIdentity::class);
    }

    public function packageBooted(): void
    {
        $this->configureFortify();
        $this->registerConfig();
        $this->registerPayPalProvider();
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

    private function registerConfig(): void
    {
        foreach (config(key: 'laranail.authkit.social', default: []) as $provider => $providerConfig) {
            if (is_array(value: $providerConfig)) {
                config()->set(key: "services.{$provider}", value: $providerConfig);
            }
        }
    }

    private function registerPayPalProvider(): void
    {
        Event::listen(
            events: SocialiteWasCalled::class,
            listener: function (SocialiteWasCalled $event): void {
                $event->extendSocialite(
                    providerName: 'paypal',
                    providerClass: Services\PayPalSocialProvider::class,
                );
            },
        );
    }
}
