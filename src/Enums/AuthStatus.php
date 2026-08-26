<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Enums;

enum AuthStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Throttled = 'throttled';
}
