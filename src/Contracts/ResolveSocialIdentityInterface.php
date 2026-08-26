<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\SocialProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;

interface ResolveSocialIdentityInterface
{
    public function execute(SocialProvider $provider, SocialiteUser $socialUser, string $guard): ?Authenticatable;
}
