<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\PasswordBroker;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractPasswordResetLinkController extends AbstractAuthController
{
    public function __construct(
        private \Illuminate\Contracts\Config\Repository $config,
        private \Illuminate\Contracts\Auth\PasswordBrokerFactory $passwords,
    ) {}

    public function store(Request $request): mixed
    {
        $request->validate([
            'email'                                                                                       => 'required|email',
            $this->config->get(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response') => AuthKit::turnstileRules(),
        ]);

        $email = $request->input('email');
        if ($this->config->get('fortify.lowercase_usernames')) {
            $email = Str::lower($email);
        }

        $this->broker()->sendResetLink(['email' => $email]);

        if ($request->expectsJson()) {
            return $this->jsonResponse(
                status: 'passed',
                data: ['message' => __(PasswordBroker::RESET_LINK_SENT)],
            );
        }

        return $this->sent(request: $request, status: PasswordBroker::RESET_LINK_SENT);
    }

    protected function sent(Request $request, string $status): mixed
    {
        return back()->with('status', __($status));
    }

    protected function broker(): PasswordBroker
    {
        return $this->passwords->broker($this->config->get('fortify.passwords'));
    }
}
