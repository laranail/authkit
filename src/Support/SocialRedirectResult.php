<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Support;

class SocialRedirectResult
{
    public function __construct(
        public string $url,
    ) {
    }
}
