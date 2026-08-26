<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

interface LogoutUserInterface
{
    public function execute(string $guard): void;
}
