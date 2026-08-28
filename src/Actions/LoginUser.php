<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;

class LoginUser implements LoginUserInterface
{
    public function __construct(
        private AuthFactory $auth,
        private Session $session,
    ) {}

    public function execute(Authenticatable $user, string $guard, bool $remember = false): void
    {
        $this->auth->guard($guard)->login($user, $remember);
        $this->session->regenerate();
    }
}
