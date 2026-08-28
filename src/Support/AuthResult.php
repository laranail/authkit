<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;

class AuthResult
{
    private function __construct(
        public AuthStatus $status,
        public ?Authenticatable $user = null,
        public ?int $retryAfterSeconds = null,
    ) {}

    public static function passed(Authenticatable $user): self
    {
        return new self(status: AuthStatus::Passed, user: $user);
    }

    public static function failed(): self
    {
        return new self(status: AuthStatus::Failed);
    }

    public static function throttled(int $retryAfterSeconds): self
    {
        return new self(status: AuthStatus::Throttled, retryAfterSeconds: $retryAfterSeconds);
    }

    public function isPassed(): bool
    {
        return $this->status === AuthStatus::Passed;
    }
}
