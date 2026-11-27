<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Events;

final readonly class EmailChanged
{
    public function __construct(public string $userType, public string $userId) {}
}
