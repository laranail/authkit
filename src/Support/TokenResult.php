<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class TokenResult
{
    public function __construct(
        public Authenticatable $user,
        public string $token,
    ) {
    }
}
