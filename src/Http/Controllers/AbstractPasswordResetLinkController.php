<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractPasswordResetLinkController extends AbstractAuthController
{
    public function store(Request $request): mixed
    {
        $request->validate([
            'email' => 'required|email',
            config(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response') => AuthKit::turnstileRules(),
        ]);

        $email = $request->input('email');
        if (config('fortify.lowercase_usernames')) {
            $email = Str::lower($email);
        }

        $this->broker()->sendResetLink(['email' => $email]);

        if ($request->expectsJson()) {
            return $this->jsonResponse(
                status: 'passed',
                data: ['message' => __(Password::RESET_LINK_SENT)],
            );
        }

        return $this->sent(request: $request, status: Password::RESET_LINK_SENT);
    }

    protected function sent(Request $request, string $status): mixed
    {
        return back()->with('status', __($status));
    }

    protected function broker(): PasswordBroker
    {
        return Password::broker(config('fortify.passwords'));
    }
}
