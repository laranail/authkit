<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\LinkIntent;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Simtabi\Laranail\AuthKit\Enums\IdentityLinkFailure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Simtabi\Laranail\AuthKit\Exceptions\IdentityLinkException;
use Simtabi\Laranail\AuthKit\Contracts\IdentityLinkingInterface;

/**
 * Whether a sign-in method may be added to an account that is already signed in.
 *
 * Linking is not signing in, and this class cannot sign anybody in: it has no access to a guard's
 * login, and every method returns either nothing or a value object. That is what keeps an
 * implicit "you are logged in, so this identity must be yours" from being possible at all.
 *
 * It also never sees an email address. A provider asserting that an address is verified is enough
 * to *find* an account; it is not enough to *attach a new credential* to one, and the way to keep
 * those apart is for this mechanism to have nowhere to put an address.
 */
final class IdentityLinking implements IdentityLinkingInterface
{
    private const PREFIX = 'authkit:link-intent:';

    public function __construct(
        private CacheRepository $cache,
        private AuthFactory $auth,
    ) {}

    public function issueIntent(Request $request, Authenticatable $user, string $guard, string $provider): string
    {
        if (! $this->recentlyAuthenticated(request: $request, user: $user, guard: $guard)) {
            throw IdentityLinkException::because(IdentityLinkFailure::RecentAuthenticationRequired);
        }

        $token = bin2hex(random_bytes(32));

        $this->cache->put(
            key: $this->key($token),
            value: $this->describe(request: $request, user: $user, guard: $guard, provider: $provider)->toArray(),
            ttl: now()->addMinutes($this->ttlMinutes()),
        );

        return $token;
    }

    public function consumeIntent(Request $request, string $token, Authenticatable $user, string $guard, string $provider): LinkIntent
    {
        $record = $this->spend($token);

        if (! is_array($record)) {
            throw IdentityLinkException::because(IdentityLinkFailure::IntentInvalid);
        }

        $stored = LinkIntent::fromArray($record);

        if (! $stored->matches($this->describe(request: $request, user: $user, guard: $guard, provider: $provider))) {
            throw IdentityLinkException::because(IdentityLinkFailure::IntentMismatch);
        }

        return $stored;
    }

    public function assertLinkable(Authenticatable $user, ?Authenticatable $currentOwner): void
    {
        if ($currentOwner === null) {
            return;
        }

        $sameAccount = $currentOwner::class === $user::class
            && hash_equals((string) $currentOwner->getAuthIdentifier(), (string) $user->getAuthIdentifier());

        if ($sameAccount) {
            return;
        }

        throw IdentityLinkException::because(IdentityLinkFailure::IdentityOwnedByAnotherUser);
    }

    /**
     * Read the record and delete it in the same breath, so a token spends exactly once.
     *
     * `Repository::pull()` is a get followed by a forget, which leaves a window two simultaneous
     * callbacks could both read. Where the store offers atomic locks the spend is taken under one;
     * where it does not, the window stands and is smaller than the round trip that would exploit
     * it. Both are single-use for any sequential replay, which is the attack this defends.
     *
     * @return array<string, mixed>|null
     */
    private function spend(string $token): ?array
    {
        $key = $this->key($token);
        $store = $this->cache->getStore();

        if (! $store instanceof LockProvider) {
            $record = $this->cache->pull($key);

            return is_array($record) ? $record : null;
        }

        $lock = $store->lock($key . ':spend', 5);

        return $lock->block(3, function () use ($key): ?array {
            $record = $this->cache->pull($key);

            return is_array($record) ? $record : null;
        });
    }

    /**
     * Has this user proved an account credential recently enough to add another one?
     *
     * A browser session answers through Laravel's own `auth.password_confirmed_at` against
     * `auth.password_timeout`, which is exactly what the framework's `password.confirm`
     * middleware maintains -- so an application that already confirms passwords needs nothing new.
     *
     * A token-authenticated client has no session to carry that, so it proves the credential on
     * the request itself, checked through the guard's own provider.
     */
    private function recentlyAuthenticated(Request $request, Authenticatable $user, string $guard): bool
    {
        $timeout = (int) config(key: 'auth.password_timeout', default: 10800);

        if ($request->hasSession()) {
            $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);

            if ($confirmedAt > 0 && (time() - $confirmedAt) < $timeout) {
                return true;
            }
        }

        $password = (string) $request->input('password', '');

        if ($password === '') {
            return false;
        }

        if (! $this->auth->guard($guard)->getProvider()->validateCredentials($user, ['password' => $password])) {
            return false;
        }

        if ($request->hasSession()) {
            $request->session()->put('auth.password_confirmed_at', time());
        }

        return true;
    }

    /**
     * Everything the attempt is bound to, recomputed on the way in and on the way out.
     *
     * The authentication context is the session or the access token the attempt was started from,
     * hashed. It is what stops a callback landing on a different session from completing somebody
     * else's link. A client presenting neither binds on the user alone, which is weaker but cannot
     * cross accounts, since the user is compared too.
     */
    private function describe(Request $request, Authenticatable $user, string $guard, string $provider): LinkIntent
    {
        return new LinkIntent(
            userId: (string) $user->getAuthIdentifier(),
            userClass: $user::class,
            guard: $guard,
            provider: $provider,
            context: $this->context(request: $request, user: $user),
        );
    }

    private function context(Request $request, Authenticatable $user): string
    {
        if ($request->hasSession()) {
            return 'session:' . hash('sha256', $request->session()->getId());
        }

        $token = method_exists($user, 'currentAccessToken') ? $user->currentAccessToken() : null;

        if ($token !== null && method_exists($token, 'getKey')) {
            return 'token:' . hash('sha256', (string) $token->getKey());
        }

        return 'none';
    }

    private function key(string $token): string
    {
        return self::PREFIX . hash('sha256', $token);
    }

    private function ttlMinutes(): int
    {
        $minutes = (int) config(key: 'laranail.authkit.identity_linking.intent_ttl_minutes', default: 10);

        return $minutes > 0 ? $minutes : 10;
    }
}
