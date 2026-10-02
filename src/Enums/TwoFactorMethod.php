<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Enums;

enum TwoFactorMethod: string
{
    case NONE = 'none';
    case TOTP = 'totp';
}
