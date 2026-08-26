<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\AuthKit\Models\Social;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\SocialProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;
use Simtabi\Laranail\AuthKit\Contracts\ResolveSocialIdentityInterface;
use Simtabi\Laranail\AuthKit\Contracts\CreateSocialAccountActionInterface;

class ResolveSocialIdentity implements ResolveSocialIdentityInterface
{
    public function __construct(
        private CreateSocialAccountActionInterface $createSocialAccount,
    ) {
    }

    public function execute(SocialProvider $provider, SocialiteUser $socialUser, string $guard): ?Authenticatable
    {
        $social = Social::query()
            ->where('provider', $provider->value)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($social !== null) {
            $social->update([
                'token'         => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
                'expires_at'    => $socialUser->expiresIn
                    ? now()->addSeconds($socialUser->expiresIn)
                    : null,
            ]);

            return $social->socialable;
        }

        $userModel = UserModelResolver::resolve(guard: $guard);

        if (auth()->check()) {
            $this->createSocialAccount->execute(
                authenticatable: auth()->user(),
                provider: $provider,
                socialUser: $socialUser,
            );

            return auth()->user();
        }

        $email = $this->normalizedVerifiedEmail($provider, $socialUser);

        if ($email !== null && ($existingUser = $this->findUserByEmail($userModel, $email)) !== null) {
            $this->createSocialAccount->execute(
                authenticatable: $existingUser,
                provider: $provider,
                socialUser: $socialUser,
            );

            return $existingUser;
        }

        if ($email === null || $this->findUserByEmail($userModel, $email) !== null) {
            return null;
        }

        $user = $this->createUser($userModel, $socialUser, $email);

        $this->createSocialAccount->execute(
            authenticatable: $user,
            provider: $provider,
            socialUser: $socialUser,
        );

        return $user;
    }

    private function normalizedVerifiedEmail(SocialProvider $provider, SocialiteUser $socialUser): ?string
    {
        $email = $socialUser->getEmail();

        if ($email === null || ! $this->emailIsVerified($provider, $socialUser)) {
            return null;
        }

        return Str::lower($email);
    }

    private function emailIsVerified(SocialProvider $provider, SocialiteUser $socialUser): bool
    {
        if (! in_array($provider, [SocialProvider::GOOGLE, SocialProvider::LINKEDIN, SocialProvider::PAYPAL], true)) {
            return false;
        }

        $rawUser = $socialUser instanceof \Laravel\Socialite\AbstractUser
            ? $socialUser->getRaw()
            : [];

        if (! is_array($rawUser)) {
            return false;
        }

        return filter_var($rawUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function findUserByEmail(string $userModel, ?string $email): ?Authenticatable
    {
        if ($email === null) {
            return null;
        }

        return $userModel::query()->where('email', $email)->first();
    }

    private function createUser(string $userModel, SocialiteUser $socialUser, string $email): Authenticatable
    {
        /** @var Model&Authenticatable $user */
        $user = new $userModel();
        $user->forceFill([
            'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? '',
            'email'             => $email,
            'email_verified_at' => now(),
            'password'          => Hash::make(Str::random(32)),
        ]);
        $user->save();

        return $user;
    }
}
