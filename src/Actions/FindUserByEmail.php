<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Simtabi\Laranail\AuthKit\Contracts\FindUserByEmailInterface;

class FindUserByEmail implements FindUserByEmailInterface
{
    public function __construct(
        private AuthFactory $auth,
    ) {}

    public function execute(string $email, string $guard): ?Authenticatable
    {
        $provider = $this->auth->guard($guard)->getProvider();

        return $provider->retrieveByCredentials(['email' => $email]);
    }
}
