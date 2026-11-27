<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

class ValidateTurnstile
{
    public function __construct(
        private \Illuminate\Contracts\Config\Repository $config,
        private \Illuminate\Contracts\Validation\Factory $validator,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $input = $this->config->get(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response');
        $this->validator->make(
            data: $request->all(),
            rules: [$input => AuthKit::turnstileRules()],
        )->validate();

        return $next($request);
    }
}
