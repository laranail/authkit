<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\AuthResult;

interface AttemptEmailPasswordLoginInterface
{
    public function execute(Request $request, string $guard): AuthResult;
}
