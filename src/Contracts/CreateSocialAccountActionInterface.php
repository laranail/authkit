<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Simtabi\Laranail\AuthKit\Models\Social;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\SocialProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;

interface CreateSocialAccountActionInterface
{
    public function execute(Authenticatable $authenticatable, SocialProvider $provider, SocialiteUser $socialUser): Social;
}
