<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable;

interface DeleteUserInterface
{
    public function execute(Model&Authenticatable $user, string $password): void;
}
