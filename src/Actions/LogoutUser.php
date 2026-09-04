<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Simtabi\Laranail\AuthKit\Contracts\LogoutUserInterface;

class LogoutUser implements LogoutUserInterface
{
    public function __construct(
        private AuthFactory $auth,
        private Session $session,
    ) {}

    public function execute(string $guard): void
    {
        $this->auth->guard($guard)->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }
}
