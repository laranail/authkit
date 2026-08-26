<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Http\Request;

interface CheckEmailExistsInterface
{
    public function execute(Request $request, string $guard): bool;
}
