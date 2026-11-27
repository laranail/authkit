<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Events;

/** Immutable audit metadata; never contains credentials or model instances. */
final readonly class AuthenticationFailed
{
    public function __construct(
        public string $guard,
        public string $reason,
    ) {}
}
