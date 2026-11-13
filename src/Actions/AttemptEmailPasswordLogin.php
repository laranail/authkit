<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\UserProvider;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\AuthResult;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;

class AttemptEmailPasswordLogin implements AttemptEmailPasswordLoginInterface
{
    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
    ) {}

    public function execute(Request $request, string $guard): AuthResult
    {
        $identifier = (string) $request->input(AuthKit::loginField());
        $password = (string) $request->input('password');
        $ip = $request->ip();

        $key = 'login:' . $guard . ':' . mb_strtolower(string: $identifier) . ':' . ($ip ?? '_');
        $maxAttempts = (int) config(key: 'laranail.authkit.rate_limit.max_attempts', default: 5);
        $decaySeconds = (int) config(key: 'laranail.authkit.rate_limit.decay_minutes', default: 1) * 60;

        if ($this->limiter->tooManyAttempts(key: $key, maxAttempts: $maxAttempts)) {
            return AuthResult::throttled(retryAfterSeconds: $this->limiter->availableIn(key: $key));
        }

        $user = $this->retrieve(
            provider: $this->auth->guard(name: $guard)->getProvider(),
            identifier: $identifier,
            password: $password,
        );

        if ($user === null) {
            $this->limiter->hit(key: $key, decaySeconds: $decaySeconds);

            return AuthResult::failed();
        }

        $this->limiter->clear(key: $key);

        return AuthResult::passed(user: $user);
    }

    /**
     * Resolve the identifier against each configured column, in order, and return the first
     * account whose password also matches.
     *
     * One single-column lookup per identifier rather than one `or` query. `EloquentUserProvider`
     * would accept a closure credential expressing the `or`, but `DatabaseUserProvider` and any
     * provider an application has substituted need not -- and going through the provider at all
     * is pointless if the call only works for one of them.
     *
     * The password is checked per candidate rather than only against the first account found, so
     * a username that happens to equal another account's address does not lock its owner out. It
     * also means a failed attempt costs the same number of hash comparisons whichever column the
     * identifier belongs to.
     */
    private function retrieve(UserProvider $provider, string $identifier, string $password): ?Authenticatable
    {
        foreach (AuthKit::loginIdentifiers() as $column) {
            $credentials = [$column => $identifier, 'password' => $password];
            $user = $provider->retrieveByCredentials($credentials);

            if ($user !== null && $provider->validateCredentials($user, $credentials)) {
                return $user;
            }
        }

        return null;
    }
}
