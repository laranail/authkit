<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\AuthKit\Support\AuthResult;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;
use Simtabi\Laranail\AuthKit\Http\Requests\AttemptEmailPasswordLoginRequest;

abstract class AbstractAttemptEmailPasswordLoginController extends AbstractAuthController
{
    public function store(
        AttemptEmailPasswordLoginRequest $request,
        AttemptEmailPasswordLoginInterface $attemptAction,
        LoginUserInterface $loginAction,
    ): mixed {
        $result = $attemptAction->execute(
            request: $request,
            guard: $this->guard(),
        );

        if ($request->expectsJson()) {
            return match ($result->status) {
                AuthStatus::Passed    => $this->jsonResponse(status: 'passed', data: ['user' => $result->user]),
                AuthStatus::Failed    => $this->jsonResponse(status: 'failed', data: ['message' => 'Invalid credentials.'], code: 422),
                AuthStatus::Throttled => $this->jsonResponse(status: 'throttled', data: ['retry_after' => $result->retryAfterSeconds], code: 429),
            };
        }

        return match ($result->status) {
            AuthStatus::Passed    => $this->handlePassed(request: $request, result: $result, loginAction: $loginAction),
            AuthStatus::Failed    => $this->failed(request: $request, result: $result),
            AuthStatus::Throttled => $this->throttled(request: $request, result: $result),
        };
    }

    protected function handlePassed(Request $request, AuthResult $result, LoginUserInterface $loginAction): mixed
    {
        $loginAction->execute(
            user: $result->user,
            guard: $this->guard(),
            remember: $request->boolean(key: 'remember'),
        );

        return $this->passed(request: $request, result: $result);
    }

    protected function passed(Request $request, AuthResult $result): mixed
    {
        return redirect()->intended(default: AuthKit::afterLoginRedirect());
    }

    protected function failed(Request $request, AuthResult $result): mixed
    {
        return redirect()->back()
            ->withInput(input: $request->only(keys: ['email', 'remember']))
            ->withErrors(provider: ['email' => 'Invalid credentials.']);
    }

    protected function throttled(Request $request, AuthResult $result): mixed
    {
        abort(code: 429, message: 'Too many attempts.');
    }
}
