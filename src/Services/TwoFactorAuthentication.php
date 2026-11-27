<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Throwable;
use BackedEnum;
use LogicException;
use Laravel\Fortify\RecoveryCode;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\TwoFactorMethod;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

final class TwoFactorAuthentication
{
    public function __construct(
        private TwoFactorAuthenticationProvider $provider,
        private \Illuminate\Contracts\Config\Repository $config,
        private \Illuminate\Contracts\Cache\Repository $cache,
        private \Illuminate\Contracts\Encryption\Encrypter $encrypter,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function enabled(Authenticatable $user): bool
    {
        return $this->value($user, 'two_factor_method') === TwoFactorMethod::TOTP->value
            && $this->value($user, 'two_factor_secret') !== null
            && $this->value($user, 'two_factor_confirmed_at') !== null;
    }

    /** Start or resume enrollment. The secret remains inactive until confirm() succeeds. */
    public function begin(Authenticatable $user): array
    {
        if ($this->enabled($user)) {
            throw new LogicException(__('laranail/authkit::messages.two_factor_already_enabled'));
        }

        $stored = $this->value($user, 'two_factor_secret');
        $confirmed = $this->value($user, 'two_factor_confirmed_at');

        $secret = $stored === null || $confirmed !== null
            ? null
            : $this->decrypt((string) $stored);

        if ($secret === null) {
            $secret = $this->provider->generateSecretKey(32);
            $this->fill($user, [
                'two_factor_method'         => TwoFactorMethod::NONE->value,
                'two_factor_secret'         => $this->encrypter->encryptString($secret),
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at'   => null,
            ]);
        }

        $label = (string) ($this->value($user, 'email') ?? $user->getAuthIdentifier());

        return [
            'secret'      => $secret,
            'qr_code_url' => $this->provider->qrCodeUrl((string) $this->config->get('app.name'), $label, $secret),
        ];
    }

    /** Confirm enrollment and return one-time recovery codes. */
    public function confirm(Authenticatable $user, string $code): ?array
    {
        $codes = $this->withUserLock($user, fn (): ?array => $this->confirmLocked($user, $code));

        if (is_array($codes)) {
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\TwoFactorEnabled($user::class, (string) $user->getAuthIdentifier()));
        }

        return is_array($codes) ? $codes : null;
    }

    /** Consume either a TOTP or a single-use recovery code. */
    public function verify(Authenticatable $user, string $code): bool
    {
        return $this->withUserLock($user, fn (): bool => $this->verifyLocked($user, $code));
    }

    public function disable(Authenticatable $user): void
    {
        $wasEnabled = $this->enabled($user);
        $this->fill($user, [
            'two_factor_method'         => TwoFactorMethod::NONE->value,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ]);

        if ($wasEnabled) {
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\TwoFactorDisabled($user::class, (string) $user->getAuthIdentifier()));
        }
    }

    public function recoveryCodes(Authenticatable $user): array
    {
        $stored = $this->value($user, 'two_factor_recovery_codes');

        if (! is_string($stored) || $stored === '') {
            return [];
        }

        try {
            $codes = json_decode($this->encrypter->decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($codes) ? $codes : [];
    }

    public function rotateRecoveryCodes(Authenticatable $user): array
    {
        $codes = array_map(static fn (): string => RecoveryCode::generate(), range(1, 8));
        $this->fill($user, ['two_factor_recovery_codes' => $this->encrypter->encryptString(json_encode($codes, JSON_THROW_ON_ERROR))]);
        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\RecoveryCodesRegenerated($user::class, (string) $user->getAuthIdentifier()));

        return $codes;
    }

    private function confirmLocked(Authenticatable $user, string $code): ?array
    {
        $stored = $this->value($user, 'two_factor_secret');

        if ($stored === null || $this->value($user, 'two_factor_confirmed_at') !== null) {
            return null;
        }

        $secret = $this->decrypt((string) $stored);

        if ($secret === null || ! $this->provider->verify($secret, $code)) {
            return null;
        }

        $codes = array_map(static fn (): string => RecoveryCode::generate(), range(1, 8));
        $this->fill($user, [
            'two_factor_method'         => TwoFactorMethod::TOTP->value,
            'two_factor_recovery_codes' => $this->encrypter->encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at'   => now(),
        ]);

        return $codes;
    }

    private function verifyLocked(Authenticatable $user, string $code): bool
    {
        if (! $this->enabled($user)) {
            return false;
        }

        $codes = $this->recoveryCodes($user);
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        $index = array_search($normalized, array_map(
            static fn (string $stored): string => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $stored) ?? ''),
            $codes,
        ), true);

        if ($index !== false) {
            unset($codes[$index]);
            $this->fill($user, [
                'two_factor_recovery_codes' => $codes === []
                    ? null
                    : $this->encrypter->encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR)),
            ]);

            return true;
        }

        $secret = $this->decrypt((string) $this->value($user, 'two_factor_secret'));

        return $secret !== null && $this->provider->verify($secret, $code);
    }

    /**
     * Null when the stored payload cannot be read, which in practice means a rotated or
     * mismatched APP_KEY. Every caller then fails closed, the way `recoveryCodes()` already
     * returns an empty list, instead of a DecryptException escaping as a 500 from the challenge.
     */
    private function decrypt(string $secret): ?string
    {
        try {
            return $this->encrypter->decryptString($secret);
        } catch (Throwable) {
            // Back-compat: secrets written with encrypt() rather than encryptString().
            try {
                return (string) $this->encrypter->decrypt($secret);
            } catch (Throwable) {
                return null;
            }
        }
    }

    private function value(Authenticatable $user, string $key): mixed
    {
        $value = method_exists($user, 'getAttribute') ? $user->getAttribute($key) : null;

        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private function fill(Authenticatable $user, array $attributes): void
    {
        if (method_exists($user, 'forceFill')) {
            $user->forceFill($attributes)->save();
        }
    }

    private function withUserLock(Authenticatable $user, callable $callback): mixed
    {
        $lockKey = 'authkit:two-factor:verify:' . hash(
            'sha256',
            $user::class . ':' . (string) $user->getAuthIdentifier(),
        );

        $store = $this->cache->getStore();

        // Three of the stores Laravel ships are not LockProviders -- apc, session and storage --
        // and $this->cache->lock() on one of them fails with PHP's Error: Call to undefined method,
        // surfacing as a 500 on the challenge. Not BadMethodCallException, as this comment said
        // until it was checked: none of those stores declares lock() or __call, and
        // Repository::__call forwards straight to the store, so nothing translates the call into
        // an exception type. IdentityLinking::spend() already degrades exactly this way.
        if (! $store instanceof LockProvider) {
            return $callback();
        }

        try {
            return $store->lock($lockKey, 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            return false;
        }
    }
}
