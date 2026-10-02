<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;

final class TwoFactorChallengeController
{
    public function __invoke(
        Request $request,
        TwoFactorAuthentication $twoFactor,
        IssueTokenForUserInterface $issuer,
    ): JsonResponse {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'code'            => ['required', 'string', 'min:6', 'max:20'],
        ]);

        $key = 'authkit:two-factor:challenge:' . hash('sha256', $validated['challenge_token']);

        try {
            return Cache::lock($key . ':lock', 10)->block(5, function () use ($key, $validated, $twoFactor, $issuer): JsonResponse {
                $challenge = Cache::get($key);

                if (! is_array($challenge) || ! isset($challenge['user_id'], $challenge['guard'])) {
                    throw ValidationException::withMessages(['code' => 'The challenge is invalid or expired.']);
                }

                $model = UserModelResolver::resolve($challenge['guard']);
                $user = $model::query()->find($challenge['user_id']);

                if ($user === null || ! $twoFactor->verify($user, $validated['code'])) {
                    throw ValidationException::withMessages(['code' => 'The verification code is invalid.']);
                }

                Cache::forget($key);
                $token = $issuer->execute(user: $user, name: 'api-login', twoFactorVerified: true);

                return response()->json([
                    'status' => 'success',
                    'data'   => ['token' => $token->token, 'user' => $token->user],
                ])->header('Cache-Control', 'no-store');
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['code' => 'The challenge is already being processed.']);
        }
    }
}
