<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Translation\Translator;
use Simtabi\Laranail\AuthKit\Events\AccountLocked;
use Simtabi\Laranail\AuthKit\Events\AccountUnlocked;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class AccountLockout
{
    public function __construct(
        private DatabaseManager $db,
        private Repository $config,
        private Dispatcher $events,
        private Translator $translator,
    ) {}

    public function retryAfter(Authenticatable $user, string $guard): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $until = $this->query()->where('account_key', $this->keyFor($user, $guard))->value('locked_until');

        return max(0, (int) $until - now()->getTimestamp());
    }

    public function assertUnlocked(Authenticatable $user, string $guard): void
    {
        $remaining = $this->retryAfter($user, $guard);
        if ($remaining > 0) {
            throw new TooManyRequestsHttpException($remaining, $this->translator->get(
                'laranail/authkit::messages.account_locked',
                ['seconds' => $remaining],
            ));
        }
    }

    public function recordFailure(Authenticatable $user, string $guard): int
    {
        return $this->change($user, $guard, 'failure');
    }

    /** A correct password clears failures only when the account is not currently locked. */
    public function recordSuccess(Authenticatable $user, string $guard): int
    {
        return $this->change($user, $guard, 'success');
    }

    /** Privileged callers own authorization; this service exposes no public unlock endpoint. */
    public function clear(Authenticatable $user, string $guard): void
    {
        $this->change($user, $guard, 'clear');
    }

    /** Remove retained state after account deletion, rather than creating a zeroed counter. */
    public function forget(Authenticatable $user, string $guard): void
    {
        if ($this->enabled()) {
            $this->query()->where('account_key', $this->keyFor($user, $guard))->delete();
        }
    }

    /** Stable across IPs, identifier aliases, and Eloquent guards over the same physical account. */
    public function keyFor(Authenticatable $user, string $guard): string
    {
        $identity = $user instanceof Model
            ? ['model', $user->getConnection()->getName(), $user->getTable(), $user->getKeyName()]
            : ['provider', $this->config->get('auth.guards.' . $guard . '.provider', $guard), $user::class];

        return hash('sha256', json_encode([...$identity, (string) ($user instanceof Model ? $user->getKey() : $user->getAuthIdentifier())], JSON_THROW_ON_ERROR));
    }

    private function change(Authenticatable $user, string $guard, string $operation): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $key = $this->keyFor($user, $guard);
        $connection = $this->db->connection($this->config->get('laranail.authkit.lockout.connection'));
        $result = $connection->transaction(function () use ($key, $operation): array {
            // The primary key arbitrates first-use races. Keep a zeroed row after clearing so
            // concurrent success/failure updates cannot race with deletion of the counter row.
            $this->query()->insertOrIgnore(['account_key' => $key, 'failed_attempts' => 0, 'locked_until' => null]);
            $row = $this->query()->where('account_key', $key)->lockForUpdate()->first();
            $now = now()->getTimestamp();
            $remaining = max(0, (int) $row->locked_until - $now);
            if ($remaining > 0 && $operation !== 'clear') {
                return ['retry' => $remaining, 'locked' => false, 'unlocked' => null];
            }

            $expired = $row->locked_until !== null && $remaining === 0;
            $attempts = $expired ? 0 : (int) $row->failed_attempts;
            $until = null;
            if ($operation === 'failure') {
                $attempts++;
                if ($attempts >= max(1, (int) $this->config->get('laranail.authkit.lockout.max_attempts', 5))) {
                    $until = $now + max(1, (int) $this->config->get('laranail.authkit.lockout.duration_minutes', 15)) * 60;
                }
            } else {
                $attempts = 0;
            }
            $this->query()->where('account_key', $key)->update(['failed_attempts' => $attempts, 'locked_until' => $until]);

            return [
                'retry'    => $until === null ? 0 : $until - $now,
                'locked'   => $until !== null,
                'unlocked' => $expired ? 'expired' : ($remaining > 0 && $operation === 'clear' ? 'manual' : null),
            ];
        }, 3);

        if ($result['unlocked'] !== null) {
            $this->events->dispatch(new AccountUnlocked($user::class, (string) $user->getAuthIdentifier(), $guard, $result['unlocked']));
        }
        if ($result['locked']) {
            $this->events->dispatch(new AccountLocked($user::class, (string) $user->getAuthIdentifier(), $guard));
        }

        return $result['retry'];
    }

    private function query(): Builder
    {
        return $this->db->connection($this->config->get('laranail.authkit.lockout.connection'))
            ->table($this->config->get('laranail.authkit.lockout.table', 'authkit_account_lockouts'));
    }

    private function enabled(): bool
    {
        return (bool) $this->config->get('laranail.authkit.lockout.enabled', false);
    }
}
