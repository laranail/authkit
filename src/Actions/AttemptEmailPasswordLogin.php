<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\UserProvider;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\AuthKit\Support\AuthResult;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Simtabi\Laranail\AuthKit\Services\AccountLockout;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;

class AttemptEmailPasswordLogin implements AttemptEmailPasswordLoginInterface
{
    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
        private AccountLockout $lockout,
        private \Illuminate\Contracts\Config\Repository $config,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function execute(Request $request, string $guard): AuthResult
    {
        $identifier = (string) $request->input(AuthKit::loginField());
        $password = (string) $request->input('password');
        $ip = $request->ip();

        $key = 'login:' . $guard . ':' . mb_strtolower(string: $identifier) . ':' . ($ip ?? '_');
        $maxAttempts = (int) $this->config->get(key: 'laranail.authkit.rate_limit.max_attempts', default: 5);
        $decaySeconds = (int) $this->config->get(key: 'laranail.authkit.rate_limit.decay_minutes', default: 1) * 60;

        if ($this->limiter->tooManyAttempts(key: $key, maxAttempts: $maxAttempts)) {
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\AuthenticationFailed($guard, 'throttled'));

            return AuthResult::throttled(retryAfterSeconds: $this->limiter->availableIn(key: $key));
        }

        $result = $this->retrieve(
            provider: $this->auth->guard(name: $guard)->getProvider(),
            identifier: $identifier,
            password: $password,
            guard: $guard,
        );

        if ($result->isPassed()) {
            $this->limiter->clear(key: $key);
        } else {
            $this->limiter->hit(key: $key, decaySeconds: $decaySeconds);
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\AuthenticationFailed(
                $guard,
                $result->status === AuthStatus::Locked ? 'locked' : 'invalid_credentials',
            ));
        }

        return $result;
    }

    /**
     * Resolve the identifier against each configured column, in order, and return the first
     * unlocked account whose password also matches.
     *
     * One single-column lookup per identifier rather than one `or` query. `EloquentUserProvider`
     * would accept a closure credential expressing the `or`, but `DatabaseUserProvider` and any
     * provider an application has substituted need not -- and going through the provider at all
     * is pointless if the call only works for one of them.
     *
     * The password is checked per candidate rather than only against the first account found, so
     * a username that happens to equal another account's address does not lock its owner out.
     * Lock checks precede password hashing, and failures are recorded only when no candidate
     * can authenticate.
     */
    private function retrieve(UserProvider $provider, string $identifier, string $password, string $guard): AuthResult
    {
        $candidates = [];
        $retryAfter = 0;
        foreach (AuthKit::loginIdentifiers() as $column) {
            $credentials = [$column => $identifier, 'password' => $password];
            $user = $provider->retrieveByCredentials($credentials);
            if ($user === null) {
                continue;
            }
            $key = $this->lockout->keyFor($user, $guard);
            if (isset($candidates[$key])) {
                continue;
            }
            $candidates[$key] = $user;
            $remaining = $this->lockout->retryAfter($user, $guard);
            if ($remaining > 0) {
                $retryAfter = max($retryAfter, $remaining);

                continue;
            }
            if ($provider->validateCredentials($user, $credentials)) {
                $remaining = $this->lockout->recordSuccess($user, $guard);
                if ($remaining === 0) {
                    return AuthResult::passed($user);
                }
                $retryAfter = max($retryAfter, $remaining);
            }
        }

        if ($retryAfter > 0) {
            return AuthResult::locked($retryAfter);
        }
        // Count only a fully failed lookup: a successful username/email collision must not
        // penalize the other candidate, and aliases resolving to the same account count once.
        foreach ($candidates as $user) {
            $retryAfter = max($retryAfter, $this->lockout->recordFailure($user, $guard));
        }

        return $retryAfter > 0 ? AuthResult::locked($retryAfter) : AuthResult::failed();
    }
}
