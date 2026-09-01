<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Simtabi\Laranail\AuthKit\Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class)->in('Feature', 'Unit');
