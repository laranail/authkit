<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

/**
 * One attempt to add a sign-in method to an account that is already signed in.
 *
 * Everything the attempt is bound to is recorded here and checked again when it is consumed: the
 * user, the model class they belong to, the guard, the provider, and the authentication context
 * that started it. The token itself is never stored -- only the record it is keyed by.
 *
 * There is deliberately no email address on this object. An address cannot authorise a link, and
 * the simplest way to guarantee that is for the mechanism to have nowhere to put one.
 */
final readonly class LinkIntent
{
    public function __construct(
        public string $userId,
        public string $userClass,
        public string $guard,
        public string $provider,
        public string $context,
    ) {}

    /** @param array<string, mixed> $record */
    public static function fromArray(array $record): self
    {
        return new self(
            userId: (string) ($record['user_id'] ?? ''),
            userClass: (string) ($record['user_class'] ?? ''),
            guard: (string) ($record['guard'] ?? ''),
            provider: (string) ($record['provider'] ?? ''),
            context: (string) ($record['context'] ?? ''),
        );
    }

    /** @return array{user_id: string, user_class: string, guard: string, provider: string, context: string} */
    public function toArray(): array
    {
        return [
            'user_id'    => $this->userId,
            'user_class' => $this->userClass,
            'guard'      => $this->guard,
            'provider'   => $this->provider,
            'context'    => $this->context,
        ];
    }

    public function matches(self $other): bool
    {
        return hash_equals($this->userId, $other->userId)
            && hash_equals($this->userClass, $other->userClass)
            && hash_equals($this->guard, $other->guard)
            && hash_equals($this->provider, $other->provider)
            && hash_equals($this->context, $other->context);
    }
}
