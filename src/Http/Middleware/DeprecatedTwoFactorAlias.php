<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * What the bare `two-factor` middleware alias resolves to.
 *
 * Laravel keeps middleware aliases in one flat map, so a bare `two-factor` registered by this
 * package silently replaces an application's or another package's alias of the same name (or is
 * replaced by it). The scoped alias is `laranail-authkit-two-factor`. This class keeps routes that
 * still name the bare alias working: it logs one warning per process naming the replacement and
 * then enforces exactly what the scoped alias enforces.
 *
 * @deprecated Use the `laranail-authkit-two-factor` middleware alias. The bare `two-factor` alias
 *             may be removed in the next minor after 0.1.
 */
final class DeprecatedTwoFactorAlias
{
    public const string ALIAS = 'two-factor';

    private static bool $warned = false;

    public function __construct(private readonly RequireTwoFactorAuthentication $middleware) {}

    /** @internal Lets a test observe the once-per-process warning again. */
    public static function resetWarning(): void
    {
        self::$warned = false;
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::$warned) {
            self::$warned = true;

            Log::warning(
                'The "two-factor" middleware alias is deprecated; use "laranail-authkit-two-factor" instead. '
                . 'The bare alias may be removed in the next minor after 0.1.',
            );
        }

        return $this->middleware->handle($request, $next);
    }
}
