<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Tests\TestCase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(TestCase::class, LazilyRefreshDatabase::class)->in('Feature', 'Unit');
