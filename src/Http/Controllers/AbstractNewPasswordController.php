<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\PasswordBroker;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

abstract class AbstractNewPasswordController extends AbstractAuthController
{
    public function __construct(
        private \Illuminate\Contracts\Config\Repository $config,
        private \Illuminate\Contracts\Auth\PasswordBrokerFactory $passwords,
        private ResetsUserPasswords $resetter,
    ) {}

    public function store(Request $request): mixed
    {
        $request->validate([
            'token'                                                                                       => 'required',
            'email'                                                                                       => 'required|email',
            'password'                                                                                    => 'required|confirmed',
            $this->config->get(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response') => AuthKit::turnstileRules(),
        ]);

        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');
        if ($this->config->get('fortify.lowercase_usernames')) {
            $credentials['email'] = Str::lower($credentials['email']);
        }

        $status = $this->broker()->reset(
            $credentials,
            function ($user) use ($request) {
                $this->resetter->reset($user, $request->all());
            },
        );

        if ($request->expectsJson()) {
            return $status === PasswordBroker::PASSWORD_RESET
                ? $this->jsonResponse(status: 'passed', data: ['message' => __($status)])
                : $this->jsonResponse(status: 'failed', data: ['message' => __($status)], code: 422);
        }

        return $status === PasswordBroker::PASSWORD_RESET
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
        return $this->passwords->broker($this->config->get('fortify.passwords'));
    }
}
