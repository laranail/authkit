<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;

final class RequireTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        abort_unless(app(TwoFactorAuthentication::class)->enabled($user), 403, 'Two-factor authentication must be enabled.');

        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token !== null) {
            $abilities = $token->abilities ?? $token->oauth_scopes ?? $token->scopes ?? [];
            abort_unless(is_array($abilities) && in_array('two-factor:verified', $abilities, true), 403, 'MFA verification is required.');
        } else {
            abort_unless($request->hasSession(), 403, 'MFA verification is required.');
            abort_unless($request->session()->get('authkit.two_factor_verified') === true, 403, 'MFA verification is required.');
        }

        return $next($request);
    }
}
