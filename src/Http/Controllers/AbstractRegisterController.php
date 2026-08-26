<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Laravel\Fortify\Contracts\CreatesNewUsers as FortifyCreateNewUser;

abstract class AbstractRegisterController extends AbstractAuthController
{
    public function store(
        Request $request,
        FortifyCreateNewUser $creator,
        LoginUserInterface $loginAction,
    ): mixed {
        event(new Registered($user = $creator->create($request->all())));

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['user' => $user], code: 201);
        }

        return $this->handleRegistered($request, $user, $loginAction);
    }

    protected function handleRegistered(Request $request, Authenticatable $user, LoginUserInterface $loginAction): mixed
    {
        $loginAction->execute(user: $user, guard: $this->guard());

        return $this->registered($request, $user);
    }

    protected function registered(Request $request, Authenticatable $user): mixed
    {
        return redirect()->to(path: AuthKit::afterRegistrationRedirect());
    }
}
