<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractEmailVerificationPromptController extends AbstractAuthController
{
    public function __invoke(Request $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->verifiedResponse($request);
        }

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'failed', data: ['message' => 'Email not verified.'], code: 403);
        }

        return $this->prompt($request);
    }

    abstract protected function prompt(Request $request): mixed;

    protected function verifiedResponse(Request $request): mixed
    {
        return redirect()->intended(AuthKit::afterLoginRedirect());
    }
}
