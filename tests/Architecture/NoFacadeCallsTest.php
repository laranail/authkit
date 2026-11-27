<?php

declare(strict_types=1);

use Simtabi\Laranail\Package\Tools\Testing\AssertsNoFacadeCalls;

uses(AssertsNoFacadeCalls::class);

it('keeps service dependencies explicit outside documented framework bridges', function (): void {
    $this->assertNoFacadeCallsIn(dirname(__DIR__, 2) . '/src', minFiles: 75);
});
