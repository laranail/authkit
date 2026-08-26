<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Simtabi\Laranail\AuthKit\Actions\LoginUser;

it(description: 'logs the user into the guard', closure: function () {
    $user = User::factory()->create();

    app(abstract: LoginUser::class)->execute(user: $user, guard: 'web');

    expect(value: auth()->check())->toBeTrue()
        ->and(value: auth()->id())->toBe(expected: $user->getAuthIdentifier());
});
