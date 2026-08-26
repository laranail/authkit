<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\AuthKit\Support\AuthResult;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Simtabi\Laranail\AuthKit\Contracts\SocialCallbackActionInterface;

abstract class AbstractSocialCallbackController extends AbstractAuthController
{
    public function __invoke(
        Request $request,
        SocialCallbackActionInterface $socialAction,
        LoginUserInterface $loginAction,
    ): mixed {
        $result = $socialAction->execute(
            request: $request,
            guard: $this->guard(),
        );

        if ($request->expectsJson()) {
            return match ($result->status) {
                AuthStatus::Passed => $this->jsonResponse(status: 'passed', data: ['user' => $result->user]),
                AuthStatus::Failed => $this->jsonResponse(status: 'failed', data: ['message' => 'Social authentication failed.'], code: 422),
                default            => $this->jsonResponse(status: 'failed', data: ['message' => 'Social authentication failed.'], code: 422),
            };
        }

        return match ($result->status) {
            AuthStatus::Passed => $this->handlePassed(request: $request, result: $result, loginAction: $loginAction),
            AuthStatus::Failed => $this->failed(request: $request, result: $result),
            default            => $this->failed(request: $request, result: $result),
        };
    }

    protected function handlePassed(Request $request, AuthResult $result, LoginUserInterface $loginAction): mixed
    {
        $loginAction->execute(
            user: $result->user,
            guard: $this->guard(),
        );

        return $this->passed(request: $request, result: $result);
    }

    protected function passed(Request $request, AuthResult $result): mixed
    {
        return redirect()->intended(default: AuthKit::afterLoginRedirect());
    }

    protected function failed(Request $request, AuthResult $result): mixed
    {
        return redirect()->to(path: route('login'))
            ->withErrors(provider: ['email' => 'Social authentication failed.']);
    }
}
