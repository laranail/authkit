<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Events\Registered;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
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
        // A user who must verify their address is sent to the notice rather than to the
        // application. Sending them onward instead lands them on a page the verified middleware
        // bounces them off, or -- worse, where that middleware is not applied -- lets them use
        // the application as though the verification requirement did not exist. The route is
        // resolved by name so a host that has moved or renamed it still works, and the
        // registration redirect remains the answer when nothing needs verifying.
        if ($user instanceof MustVerifyEmail && $user->hasVerifiedEmail() === false) {
            // Resolved through the URL generator rather than Route::has(), because a frontend
            // package may register this route under a vendor-scoped name. route() consults the
            // missing-named-route resolver and finds it either way; Route::has() asks the route
            // collection directly and would answer false, silently sending an unverified user
            // into the application instead of to the notice.
            try {
                return redirect()->to(path: route(name: 'verification.notice'));
            } catch (RouteNotFoundException) {
                // No verification notice is mounted; fall through to the registration redirect.
            }
        }

        return redirect()->to(path: AuthKit::afterRegistrationRedirect());
    }
}
