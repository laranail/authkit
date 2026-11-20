<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractEmailVerificationNotificationController extends AbstractAuthController
{
    public function store(Request $request): mixed
    {
        if ($request->user()->hasVerifiedEmail()) {
            return $this->verifiedResponse($request);
        }

        $request->user()->sendEmailVerificationNotification();

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['message' => __('laranail/authkit::messages.verification_link_sent')]);
        }

        return $this->sent($request);
    }

    protected function sent(Request $request): mixed
    {
        return back()->with('status', 'verification-link-sent');
    }

    protected function verifiedResponse(Request $request): mixed
    {
        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['message' => __('laranail/authkit::messages.email_already_verified')]);
        }

        return redirect()->intended(AuthKit::afterLoginRedirect());
    }
}
