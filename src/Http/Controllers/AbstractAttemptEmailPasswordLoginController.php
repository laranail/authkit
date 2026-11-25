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

        // One pipeline, whatever the caller accepts. The representation is chosen in `passed()`,
        // `failed()` and `throttled()`, after the attempt has been acted on -- never before it.
        // Negotiating first is how this controller came to answer `200 passed` to a JSON caller
        // without establishing a session or reaching the subclass hook that gates two-factor.
        // Laravel's own Fortify has the same shape: `AuthenticatedSessionController::store()` runs
        // the whole login pipeline and only then returns `app(LoginResponse::class)`, whose
        // `toResponse()` is the single place that asks `wantsJson()`.
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
        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['user' => $result->user]);
        }

        return redirect()->intended(default: AuthKit::afterLoginRedirect());
    }

    /**
     * Repopulate and report the error against the field the identifier was posted in, so a form
     * configured for usernames does not lose its input and show its error on an absent `email`.
     */
    protected function failed(Request $request, AuthResult $result): mixed
    {
        $field = AuthKit::loginField();

        if ($request->expectsJson()) {
            return $this->jsonResponse(
                status: 'failed',
                data: ['message' => __('laranail/authkit::messages.invalid_credentials')],
                code: 422,
            );
        }

        return redirect()->back()
            ->withInput(input: $request->only(keys: [$field, 'remember']))
            ->withErrors(provider: [$field => __('laranail/authkit::messages.invalid_credentials')]);
    }

    protected function throttled(Request $request, AuthResult $result): mixed
    {
        if ($request->expectsJson()) {
            return $this->jsonResponse(
                status: 'throttled',
                data: ['retry_after' => $result->retryAfterSeconds],
                code: 429,
            );
        }

        abort(code: 429, message: __('laranail/authkit::messages.too_many_attempts'));
    }
}
