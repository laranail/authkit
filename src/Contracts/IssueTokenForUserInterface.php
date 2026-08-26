<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;

interface IssueTokenForUserInterface
{
    /** @param array<int, string> $abilities */
    public function execute(Authenticatable $user, ?string $name = null, array $abilities = ['*']): TokenResult;
}
