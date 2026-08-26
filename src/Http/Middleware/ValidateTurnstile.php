<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

class ValidateTurnstile
{
    public function handle(Request $request, Closure $next): mixed
    {
        $input = config(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response');
        Validator::make(
            data: $request->all(),
            rules: [$input => AuthKit::turnstileRules()],
        )->validate();

        return $next($request);
    }
}
