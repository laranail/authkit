<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractNewPasswordController extends AbstractAuthController
{
    public function store(Request $request): mixed
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            config(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response') => AuthKit::turnstileRules(),
        ]);

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
        if (config('fortify.lowercase_usernames')) {
            $credentials['email'] = Str::lower($credentials['email']);
        }

        $status = $this->broker()->reset(
            $credentials,
            function ($user) use ($request) {
                app(ResetsUserPasswords::class)->reset($user, $request->all());
            },
        );

        if ($request->expectsJson()) {
            return $status === Password::PASSWORD_RESET
                ? $this->jsonResponse(status: 'passed', data: ['message' => __($status)])
                : $this->jsonResponse(status: 'failed', data: ['message' => __($status)], code: 422);
        }

        return $status === Password::PASSWORD_RESET
            ? $this->reset(request: $request, status: $status)
            : $this->failed(request: $request, status: $status);
    }

    protected function reset(Request $request, string $status): mixed
    {
        return redirect()->to(path: AuthKit::afterPasswordResetRedirect())->with('status', __($status));
    }

    protected function failed(Request $request, string $status): mixed
    {
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    protected function broker(): PasswordBroker
    {
        return Password::broker(config('fortify.passwords'));
    }
}
