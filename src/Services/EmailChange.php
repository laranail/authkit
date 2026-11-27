<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Notifications\AnonymousNotifiable;
use Simtabi\Laranail\AuthKit\Events\EmailChanged;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Simtabi\Laranail\AuthKit\Events\EmailChangeCancelled;
use Simtabi\Laranail\AuthKit\Events\EmailChangeRequested;
use Illuminate\Database\UniqueConstraintViolationException;
use Simtabi\Laranail\AuthKit\Notifications\VerifyEmailChange;
use Illuminate\Contracts\Notifications\Dispatcher as Notifications;

final class EmailChange
{
    public function __construct(
        private Cache $cache,
        private Config $config,
        private Encrypter $encrypter,
        private Notifications $notifications,
        private UrlGenerator $urls,
        private UserValidationService $validation,
        private Translator $translator,
        private Events $events,
    ) {}

    /** Keep the active address unchanged until the new address proves ownership. */
    public function request(Model $user, string $email): void
    {
        $email = mb_strtolower(trim($email));
        $this->validation->validate(['email' => $email], ['email' => ['required', 'string', 'email', 'max:255']]);
        $proof = bin2hex(random_bytes(32));
        $minutes = max(1, (int) $this->config->get('laranail.authkit.email_change.expires_after_minutes', 60));
        $expires = now()->addMinutes($minutes);
        $url = $this->verificationUrl($proof);

        $user->getConnection()->transaction(function () use ($user, $email, $proof, $expires): void {
            $current = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if (mb_strtolower($current->email) === $email) {
                $this->fail('email_change_same_address');
            }
            $this->assertAvailable($current, $email);
            $this->cache->put($this->key($current), $this->encrypter->encrypt([
                'original'   => $current->email,
                'email'      => $email,
                'proof'      => hash('sha256', $proof),
                'expires_at' => $expires->getTimestamp(),
            ]), $expires);
        });

        $this->notifications->send(
            (new AnonymousNotifiable)->route('mail', $email),
            new VerifyEmailChange($url, $minutes),
        );
        $this->events->dispatch(new EmailChangeRequested($user::class, (string) $user->getKey()));
    }

    /** @return array{email: string, expires_at: int}|null */
    public function pending(Model $user): ?array
    {
        $record = $this->record($user);
        $current = $user->fresh();
        if ($record === null || $current === null || $record['original'] !== $current->email) {
            return null;
        }

        return ['email' => $record['email'], 'expires_at' => $record['expires_at']];
    }

    public function confirm(Model $user, string $proof): void
    {
        try {
            $user->getConnection()->transaction(function () use ($user, $proof): void {
                $current = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $record = $this->record($current);
                if ($record === null || $record['original'] !== $current->email
                    || ! hash_equals($record['proof'], hash('sha256', $proof))) {
                    $this->fail('email_change_invalid');
                }
                $this->assertAvailable($current, $record['email']);
                $current->forceFill(['email' => $record['email']]);
                if ($current instanceof MustVerifyEmail || array_key_exists('email_verified_at', $current->getAttributes())) {
                    $current->forceFill(['email_verified_at' => now()]);
                }
                $current->save();
                $this->cache->forget($this->key($current));
            });
        } catch (UniqueConstraintViolationException $exception) {
            $record = $this->record($user);
            if ($record !== null) {
                $this->assertAvailable($user, $record['email']);
            }
            throw $exception;
        }

        $user->refresh();
        $this->events->dispatch(new EmailChanged($user::class, (string) $user->getKey()));
    }

    public function cancel(Model $user): void
    {
        $removed = $user->getConnection()->transaction(function () use ($user): bool {
            $current = $user->newQuery()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            return $this->cache->forget($this->key($current));
        });
        if ($removed) {
            $this->events->dispatch(new EmailChangeCancelled($user::class, (string) $user->getKey()));
        }
    }

    private function assertAvailable(Model $user, string $email): void
    {
        $column = $user->getConnection()->getQueryGrammar()->wrap('email');
        if ($user->newQueryWithoutScopes()->whereRaw('LOWER(' . $column . ') = ?', [$email])->where($user->getKeyName(), '!=', $user->getKey())->exists()) {
            throw ValidationException::withMessages(['email' => $this->translator->get('validation.unique', ['attribute' => 'email'])]);
        }
    }

    private function record(Model $user): ?array
    {
        $encrypted = $this->cache->get($this->key($user));
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $record = $this->encrypter->decrypt($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }

        return $record['expires_at'] > now()->getTimestamp() ? $record : null;
    }

    private function key(Model $user): string
    {
        return 'authkit:email-change:' . hash('sha256', json_encode([
            $user->getConnection()->getName(), $user->getTable(), $user->getKeyName(), (string) $user->getKey(),
        ], JSON_THROW_ON_ERROR));
    }

    private function verificationUrl(string $proof): string
    {
        $template = $this->config->get('laranail.authkit.email_change.verification_url');
        if (is_string($template) && str_contains($template, '{token}')) {
            return str_replace('{token}', $proof, $template);
        }

        return $this->urls->route(
            $this->config->get('laranail.authkit.api.name_prefix', 'laranail-auth-api.') . 'user-email-change.verify',
            ['token' => $proof],
        );
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['email' => $this->translator->get('laranail/authkit::messages.' . $message)]);
    }
}
