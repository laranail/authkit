<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;

uses(AssertsDriverContract::class);

it('reads package configuration only at its registered key', function (): void {
    // two_factor_verified belongs to the session registry, not configuration.
    $this->assertReadsConfigAtRegisteredKey(dirname(__DIR__, 2) . '/src', 'authkit', exempt: ['two_factor_verified']);
    $this->assertReadsConfigAtRegisteredKey(dirname(__DIR__, 2) . '/routes', 'authkit');
});
