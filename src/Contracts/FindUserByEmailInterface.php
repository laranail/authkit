<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface FindUserByEmailInterface
{
    public function execute(string $email, string $guard): ?Authenticatable;
}
