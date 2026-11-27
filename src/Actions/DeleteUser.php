<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use RuntimeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Translation\Translator;
use Simtabi\Laranail\AuthKit\Services\EmailChange;
use Simtabi\Laranail\AuthKit\Events\AccountDeleted;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Simtabi\Laranail\AuthKit\Events\AccountDeleting;
use Simtabi\Laranail\AuthKit\Contracts\DeleteUserInterface;
use Simtabi\Laranail\AuthKit\Services\BrowserSessionService;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

final class DeleteUser implements DeleteUserInterface
{
    public function __construct(
        private Hasher $hasher,
        private Repository $config,
        private Dispatcher $events,
        private Translator $translator,
        private PasswordBrokerFactory $passwords,
        private TokenIssuerRegistryInterface $issuers,
        private BrowserSessionService $sessions,
        private EmailChange $emailChanges,
        private \Simtabi\Laranail\AuthKit\Services\AccountLockout $lockout,
    ) {}

    public function execute(Model&Authenticatable $user, string $password): void
    {
        $user->getConnection()->transaction(function () use ($user, $password): void {
            // Re-read and lock the authenticated row: a password changed since authentication
            // must not allow an old proof to authorize this irreversible operation.
            $current = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $hash = $current->getAuthPassword();
            if (! is_string($hash) || $hash === '' || ! $this->hasher->check($password, $hash)) {
                throw ValidationException::withMessages([
                    'password' => $this->translator->get('laranail/authkit::messages.current_password_mismatch'),
                ]);
            }

            // Applications can remove their own dependent records or veto deletion here. These
            // listeners run while the account exists and inside its database transaction.
            $this->events->dispatch(new AccountDeleting($current::class, (string) $current->getAuthIdentifier()));

            // Registered issuers, including Passport, must see the live account to find its tokens.
            $this->issuers->revokeAll($current);
            if ($current instanceof PasskeyUser) {
                $current->passkeys()->delete();
            }
            if ($current instanceof CanResetPassword) {
                $this->deleteResetTokens($current);
            }
            $this->sessions->deleteOthersFor($current);
            $this->emailChanges->cancel($current);

            // Respect application retention policy, but do not leave recoverable credentials on a
            // soft-deleted row that an application might later restore.
            if (in_array(SoftDeletes::class, class_uses_recursive($current), true)) {
                $attributes = [$current->getAuthPasswordName() => $this->hasher->make(bin2hex(random_bytes(32)))];
                foreach (['remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $column) {
                    if (array_key_exists($column, $current->getAttributes())) {
                        $attributes[$column] = null;
                    }
                }
                if (array_key_exists('two_factor_method', $current->getAttributes())) {
                    $attributes['two_factor_method'] = 'none';
                }
                if (! $current->forceFill($attributes)->save()) {
                    throw new RuntimeException('Account credentials could not be removed.');
                }
            }
            if (! $current->delete()) {
                throw new RuntimeException('Account deletion was refused.');
            }
            $this->lockout->forget($current, (string) $this->config->get('laranail.authkit.guard', 'web'));
        });

        $this->events->dispatch(new AccountDeleted($user::class, (string) $user->getAuthIdentifier()));
    }

    private function deleteResetTokens(Model&Authenticatable&CanResetPassword $user): void
    {
        foreach ((array) $this->config->get('auth.passwords', []) as $name => $broker) {
            $provider = $broker['provider'] ?? null;
            $model = is_string($provider) ? $this->config->get('auth.providers.' . $provider . '.model') : null;
            if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
                continue;
            }
            $brokerUser = new $model;
            if ($brokerUser instanceof Authenticatable
                && $brokerUser->getTable() === $user->getTable()
                && $brokerUser->getConnection()->getName() === $user->getConnection()->getName()
                && $brokerUser->getAuthIdentifierName() === $user->getAuthIdentifierName()) {
                $this->passwords->broker($name)->deleteToken($user);
            }
        }
    }
}
