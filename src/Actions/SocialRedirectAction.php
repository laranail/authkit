<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Enums\SocialProvider;
use Simtabi\Laranail\AuthKit\Support\SocialRedirectResult;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Simtabi\Laranail\AuthKit\Contracts\SocialRedirectActionInterface;

class SocialRedirectAction implements SocialRedirectActionInterface
{
    public function __construct(
        private SocialiteFactory $socialite,
    ) {
    }

    public function execute(Request $request): SocialRedirectResult
    {
        $provider = SocialProvider::from(value: $request->route('provider'));

        return new SocialRedirectResult(
            url: $this->socialite->driver($provider->value)->redirect()->getTargetUrl(),
        );
    }
}
