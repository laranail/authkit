<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;

final class TwoFactorChallengeController
{
    public function __construct(
        private \Illuminate\Contracts\Cache\Repository $cache,
        private \Simtabi\Laranail\AuthKit\Services\AccountLockout $lockout,
    ) {}

    public function __invoke(
        Request $request,
        TwoFactorAuthentication $twoFactor,
        IssueTokenForUserInterface $issuer,
    ): JsonResponse {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string', 'size:64'],
            'code'            => ['required', 'string', 'min:6', 'max:21'],
        ]);

        $key = 'authkit:two-factor:challenge:' . hash('sha256', $validated['challenge_token']);

        $consume = function () use ($key, $validated, $twoFactor, $issuer): JsonResponse {
            $challenge = $this->cache->get($key);

            if (! is_array($challenge) || ! isset($challenge['user_id'], $challenge['guard'])) {
                throw ValidationException::withMessages(['code' => __('laranail/authkit::messages.challenge_expired')]);
            }

            $model = UserModelResolver::resolve($challenge['guard']);
            $user = $model::query()->find($challenge['user_id']);
            if ($user !== null) {
                $this->lockout->assertUnlocked($user, $challenge['guard']);
            }

            if ($user === null || ! $twoFactor->verify($user, $validated['code'])) {
                throw ValidationException::withMessages(['code' => __('laranail/authkit::messages.invalid_verification_code')]);
            }

            $this->cache->forget($key);
            $token = $issuer->execute(user: $user, name: 'api-login', twoFactorVerified: true);

            return response()->json([
                'status' => 'success',
                'data'   => ['token' => $token->token, 'user' => $token->user],
            ])->header('Cache-Control', 'no-store');
        };

        $store = $this->cache->getStore();

        // Not every cache store can lock -- apc, session and storage cannot -- and $this->cache->lock()
        // on one of them fails with PHP's Error: Call to undefined method (not
        // BadMethodCallException: the store declares no lock() and Repository::__call forwards
        // straight to it), surfacing as a 500 on the challenge.
        // Unlocked, the challenge is still spent exactly once by the $this->cache->forget() above; only
        // the narrow double-submit race is unguarded, the same trade IdentityLinking::spend()
        // already makes.
        if (! $store instanceof LockProvider) {
            return $consume();
        }

        try {
            return $store->lock($key . ':lock', 10)->block(5, $consume);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['code' => __('laranail/authkit::messages.challenge_processing')]);
        }
    }
}
