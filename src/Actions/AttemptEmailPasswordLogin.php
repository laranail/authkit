<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\AttemptEmailPasswordLoginInterface;
use Simtabi\Laranail\AuthKit\Support\AuthResult;

class AttemptEmailPasswordLogin implements AttemptEmailPasswordLoginInterface
{
    public function __construct(
        private AuthFactory $auth,
        private RateLimiter $limiter,
    ) {}

    public function execute(Request $request, string $guard): AuthResult
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $ip = $request->ip();

        $key = 'login:'.$guard.':'.mb_strtolower(string: $email).':'.($ip ?? '_');
        $maxAttempts = (int) config(key: 'laranail.authkit.rate_limit.max_attempts', default: 5);
        $decaySeconds = (int) config(key: 'laranail.authkit.rate_limit.decay_minutes', default: 1) * 60;

        if ($this->limiter->tooManyAttempts(key: $key, maxAttempts: $maxAttempts)) {
            return AuthResult::throttled(retryAfterSeconds: $this->limiter->availableIn(key: $key));
        }

        $guardInstance = $this->auth->guard(name: $guard);
        $provider = $guardInstance->getProvider();
        $credentials = ['email' => $email, 'password' => $password];
        $user = $provider->retrieveByCredentials($credentials);
        $ok = $user !== null && $provider->validateCredentials($user, $credentials);

        if (! $ok) {
            $this->limiter->hit(key: $key, decaySeconds: $decaySeconds);

            return AuthResult::failed();
        }

        $this->limiter->clear(key: $key);

        return AuthResult::passed(user: $user);
    }
}
