<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\LinkIntent;

/**
 * Adding a sign-in method to an account that is already signed in.
 *
 * Deliberately separate from signing in: nothing here authenticates anybody, and no method takes
 * an email address, so a matching address can never become authorisation. A provider package owns
 * where links are stored; this owns whether one is allowed.
 */
interface IdentityLinkingInterface
{
    /**
     * Begin a link attempt and return its single-use token.
     *
     * Refuses unless a credential was confirmed recently -- a session that has been through
     * password confirmation, or a password supplied on this request for a client that has no
     * session. The returned token is the only copy; only a hash of it is stored.
     *
     * @throws \Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException
     */
    public function issueIntent(Request $request, Authenticatable $user, string $guard, string $provider): string;

    /**
     * Spend the token, and prove the attempt finishing is the attempt that started.
     *
     * Consuming is single-use: a replayed callback finds nothing. The user, guard, provider and
     * authentication context are all compared against what was recorded, so a callback landing on
     * a different session or a different account is refused rather than applied.
     *
     * @throws \Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException
     */
    public function consumeIntent(Request $request, string $token, Authenticatable $user, string $guard, string $provider): LinkIntent;

    /**
     * Refuse an external identity that belongs to somebody else.
     *
     * `$currentOwner` is whoever already holds this identity, or null when nobody does -- the
     * provider package looks that up, because it owns the table. The same user is idempotent; a
     * different user is refused, never switched and never transferred.
     *
     * @throws \Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException
     */
    public function assertLinkable(Authenticatable $user, ?Authenticatable $currentOwner): void;
}
