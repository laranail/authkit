<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\CheckEmailExistsInterface;

class CheckEmailExists implements CheckEmailExistsInterface
{
    public function __construct(
        private AuthFactory $auth,
    ) {}

    public function execute(Request $request, string $guard): bool
    {
        $email = $request->input('email');
        $provider = $this->auth->guard($guard)->getProvider();

        return $provider->retrieveByCredentials(['email' => $email]) !== null;
    }
}
