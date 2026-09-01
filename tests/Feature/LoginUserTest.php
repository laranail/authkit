<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Actions\LoginUser;
use Workbench\App\Models\User;

it(description: 'logs the user into the guard', closure: function () {
    $user = User::factory()->create();

    app(abstract: LoginUser::class)->execute(user: $user, guard: 'web');

    expect(value: auth()->check())->toBeTrue()
        ->and(value: auth()->id())->toBe(expected: $user->getAuthIdentifier());
});
