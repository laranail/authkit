<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Throwable;
use BackedEnum;
use LogicException;
use Laravel\Fortify\RecoveryCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Cache\LockTimeoutException;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\TwoFactorMethod;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

final class TwoFactorAuthentication
{
    public function __construct(private TwoFactorAuthenticationProvider $provider) {}

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
            throw new LogicException('Two-factor authentication is already enabled.');
        }

        $stored = $this->value($user, 'two_factor_secret');
        $confirmed = $this->value($user, 'two_factor_confirmed_at');

        if ($stored === null || $confirmed !== null) {
            $secret = $this->provider->generateSecretKey(32);
            $this->fill($user, [
                'two_factor_method'         => TwoFactorMethod::NONE->value,
                'two_factor_secret'         => Crypt::encryptString($secret),
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at'   => null,
            ]);
        } else {
            $secret = $this->decrypt((string) $stored);
        }

        $label = (string) ($this->value($user, 'email') ?? $user->getAuthIdentifier());

        return [
            'secret'      => $secret,
            'qr_code_url' => $this->provider->qrCodeUrl((string) config('app.name'), $label, $secret),
        ];
    }

    /** Confirm enrollment and return one-time recovery codes. */
    public function confirm(Authenticatable $user, string $code): ?array
    {
        $codes = $this->withUserLock($user, fn (): ?array => $this->confirmLocked($user, $code));

        return is_array($codes) ? $codes : null;
    }

    /** Consume either a TOTP or a single-use recovery code. */
    public function verify(Authenticatable $user, string $code): bool
    {
        return $this->withUserLock($user, fn (): bool => $this->verifyLocked($user, $code));
    }

    public function disable(Authenticatable $user): void
    {
        $this->fill($user, [
            'two_factor_method'         => TwoFactorMethod::NONE->value,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ]);
    }

    public function recoveryCodes(Authenticatable $user): array
    {
        $stored = $this->value($user, 'two_factor_recovery_codes');

        if (! is_string($stored) || $stored === '') {
            return [];
        }

        try {
            $codes = json_decode(Crypt::decryptString($stored), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($codes) ? $codes : [];
    }

    public function rotateRecoveryCodes(Authenticatable $user): array
    {
        $codes = array_map(static fn (): string => RecoveryCode::generate(), range(1, 8));
        $this->fill($user, ['two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR))]);

        return $codes;
    }

    private function confirmLocked(Authenticatable $user, string $code): ?array
    {
        $stored = $this->value($user, 'two_factor_secret');

        if ($stored === null || $this->value($user, 'two_factor_confirmed_at') !== null) {
            return null;
        }

        if (! $this->provider->verify($this->decrypt((string) $stored), $code)) {
            return null;
        }

        $codes = array_map(static fn (): string => RecoveryCode::generate(), range(1, 8));
        $this->fill($user, [
            'two_factor_method'         => TwoFactorMethod::TOTP->value,
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($codes, JSON_THROW_ON_ERROR)),
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
                    : Crypt::encryptString(json_encode(array_values($codes), JSON_THROW_ON_ERROR)),
            ]);

            return true;
        }

        return $this->provider->verify(
            $this->decrypt((string) $this->value($user, 'two_factor_secret')),
            $code,
        );
    }

    private function decrypt(string $secret): string
    {
        try {
            return Crypt::decryptString($secret);
        } catch (Throwable) {
            return (string) decrypt($secret);
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

        try {
            return Cache::lock($lockKey, 10)->block(5, $callback);
        } catch (LockTimeoutException) {
            return false;
        }
    }
}
