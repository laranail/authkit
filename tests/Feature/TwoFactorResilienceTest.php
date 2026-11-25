<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Contracts\Cache\LockProvider;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;

trait TwoFactorEnabledEnvironment
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('laranail.authkit.two_factor.enabled', true);
    }
}

uses(TwoFactorEnabledEnvironment::class);

beforeEach(function (): void {
    Schema::table('users', function (Blueprint $table): void {
        $table->string('two_factor_method')->default('none');
    });

    $this->user = User::factory()->create();
});

/**
 * A cache store that is not a `LockProvider`. Three of the twelve stores Laravel ships are in this
 * category — `ApcStore`, `SessionStore` and `StorageStore` — and `Cache::lock()` on any of them
 * throws `BadMethodCallException`, which surfaces as a 500 on the two-factor challenge.
 */
function withoutLockSupport(): void
{
    $store = new class implements Store
    {
        /** @var array<string, mixed> */
        private array $items = [];

        public function get($key)
        {
            return $this->items[$key] ?? null;
        }

        public function many(array $keys)
        {
            $out = [];
            foreach ($keys as $k) {
                $out[$k] = $this->get($k);
            }

return $out;
        }

        public function put($key, $value, $seconds)
        {
            $this->items[$key] = $value;

            return true;
        }

        public function putMany(array $values, $seconds)
        {
            foreach ($values as $k => $v) {
                $this->put($k, $v, $seconds);
            }

return true;
        }

        public function increment($key, $value = 1)
        {
            return $this->items[$key] = (int) ($this->items[$key] ?? 0) + $value;
        }

        public function decrement($key, $value = 1)
        {
            return $this->increment($key, -$value);
        }

        public function forever($key, $value)
        {
            return $this->put($key, $value, 0);
        }

        public function touch($key, $seconds)
        {
            return true;
        }

        public function forget($key)
        {
            unset($this->items[$key]);

            return true;
        }

        public function flush()
        {
            $this->items = [];

            return true;
        }

        public function getPrefix()
        {
            return '';
        }
    };

    expect($store)->not->toBeInstanceOf(LockProvider::class);

    // Register it as a real driver rather than swapping the facade root: the facade must stay a
    // CacheManager, because code elsewhere calls manager methods such as driver().
    Cache::extend('locklessprobe', fn (): Repository => Cache::repository($store));
    config()->set('cache.stores.locklessprobe', ['driver' => 'locklessprobe']);
    config()->set('cache.default', 'locklessprobe');
    Cache::forgetDriver('locklessprobe');

    expect(Cache::getStore())->not->toBeInstanceOf(LockProvider::class);
}

// `decrypt()` tried `Crypt::decryptString()` and fell back to the global `decrypt()`; when both
// failed — a rotated or mismatched APP_KEY — the exception escaped, so the user got a 500 from the
// challenge rather than a clean failure. `recoveryCodes()` in the same class already caught
// `Throwable` and returned `[]`, so the file disagreed with itself.
it('fails a challenge cleanly when the stored secret cannot be decrypted', function (): void {
    $this->user->forceFill([
        'two_factor_method'       => 'totp',
        'two_factor_secret'       => TestCredentials::secret('unreadable'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $twoFactor = app(TwoFactorAuthentication::class);

    expect($twoFactor->verify($this->user->fresh(), '123456'))->toBeFalse();
});

it('starts a fresh enrolment when the pending secret cannot be decrypted', function (): void {
    $this->user->forceFill([
        'two_factor_method'       => 'none',
        'two_factor_secret'       => TestCredentials::secret('unreadable'),
        'two_factor_confirmed_at' => null,
    ])->save();

    $setup = app(TwoFactorAuthentication::class)->begin($this->user->fresh());

    expect($setup['secret'])->toBeString()->not->toBe('')
        ->and($setup['qr_code_url'])->toContain('otpauth://');
});

// `IdentityLinking::spend()` checks `instanceof LockProvider` and degrades; these two sites called
// `Cache::lock()` unconditionally. Same codebase, same concern, two answers.
it('verifies without a lock when the cache store cannot provide one', function (): void {
    withoutLockSupport();

    $twoFactor = app(TwoFactorAuthentication::class);
    $setup = $twoFactor->begin($this->user);
    $twoFactor->confirm($this->user, (new PragmaRX\Google2FA\Google2FA)->getCurrentOtp($setup['secret']));

    expect($twoFactor->verify($this->user->fresh(), '000000'))->toBeFalse();
});
