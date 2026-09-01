<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Laravel\Fortify\Http\Requests\VerifyEmailRequest;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractVerifyEmailController extends AbstractAuthController
{
    public function __invoke(VerifyEmailRequest $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->verifiedResponse($request, alreadyVerified: true);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return $this->verifiedResponse($request, alreadyVerified: false);
    }

    protected function verifiedResponse(VerifyEmailRequest $request, bool $alreadyVerified): mixed
    {
        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['message' => 'Email verified.']);
        }

        return redirect()->intended(AuthKit::afterEmailVerificationRedirect());
    }
}
