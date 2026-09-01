<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;
use Simtabi\Laranail\AuthKit\Contracts\LoginUserInterface;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractAttemptEmailPasswordLoginController;
use Simtabi\Laranail\AuthKit\Http\Requests\AttemptEmailPasswordLoginRequest;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Support\AuthResult;

class LoginController extends AbstractAttemptEmailPasswordLoginController
{
    public function __construct(
        private IssueTokenForUserInterface $issuer,
    ) {}

    public function store(
        AttemptEmailPasswordLoginRequest $request,
        AttemptEmailPasswordLoginInterface $attemptAction,
        LoginUserInterface $loginAction,
    ): JsonResponse {
        $result = $attemptAction->execute(
            request: $request,
            guard: $this->guard(),
        );

        return match ($result->status) {
            AuthStatus::Passed => $this->apiPassed(result: $result),
            AuthStatus::Failed => $this->jsonResponse(status: 'failed', data: ['message' => 'Invalid credentials.'], code: 422),
            AuthStatus::Throttled => $this->jsonResponse(status: 'throttled', data: ['retry_after' => $result->retryAfterSeconds], code: 429),
        };
    }

    protected function guard(): string
    {
        return AuthKit::guard();
    }

    protected function apiPassed(AuthResult $result): JsonResponse
    {
        $tokenResult = $this->issuer->execute(
            user: $result->user,
            name: 'api-login',
        );

        return $this->jsonResponse(status: 'success', data: [
            'token' => $tokenResult->token,
            'user' => $tokenResult->user,
        ]);
    }

    protected function passed(Request $request, AuthResult $result): JsonResponse
    {
        $tokenResult = $this->issuer->execute(
            user: $result->user,
            name: 'api-login',
        );

        return response()->json([
            'token' => $tokenResult->token,
            'user' => $tokenResult->user,
        ]);
    }

    protected function failed(Request $request, AuthResult $result): JsonResponse
    {
        return response()->json([
            'status' => 'failed',
            'message' => 'Invalid credentials.',
        ], 422);
    }

    protected function throttled(Request $request, AuthResult $result): JsonResponse
    {
        return response()->json([
            'status' => 'throttled',
            'message' => 'Too many attempts.',
            'retry_after_seconds' => $result->retryAfterSeconds,
        ], 429);
    }
}
