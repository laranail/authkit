<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Events;

/** Immutable audit metadata; never contains credentials or model instances. */
final readonly class TwoFactorEnabled
{
    public function __construct(
        public string $userType,
        public string $userId,
    ) {}
}
