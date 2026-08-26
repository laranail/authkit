<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Contracts;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Support\SocialRedirectResult;

interface SocialRedirectActionInterface
{
    public function execute(Request $request): SocialRedirectResult;
}
