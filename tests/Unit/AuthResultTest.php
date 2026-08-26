<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Enums\AuthStatus;
use Simtabi\Laranail\AuthKit\Support\AuthResult;

it(description: 'creates a passed result', closure: function () {
    $user = Mockery::mock(Authenticatable::class);

    $result = AuthResult::passed(user: $user);

    expect(value: $result->isPassed())->toBeTrue()
        ->and(value: $result->status)->toBe(expected: AuthStatus::Passed)
        ->and(value: $result->user)->toBe(expected: $user);
});

it(description: 'creates a failed result', closure: function () {
    $result = AuthResult::failed();

    expect(value: $result->isPassed())->toBeFalse()
        ->and(value: $result->status)->toBe(expected: AuthStatus::Failed)
        ->and(value: $result->user)->toBeNull();
});

it(description: 'creates a throttled result with retry seconds', closure: function () {
    $result = AuthResult::throttled(retryAfterSeconds: 30);

    expect(value: $result->status)->toBe(expected: AuthStatus::Throttled)
        ->and(value: $result->retryAfterSeconds)->toBe(expected: 30);
});
