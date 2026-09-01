<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Actions\LoginUser;
use Simtabi\Laranail\AuthKit\Actions\LogoutUser;
use Workbench\App\Models\User;

it(description: 'logs the user out of the guard', closure: function () {
    $user = User::factory()->create();

    app(abstract: LoginUser::class)->execute(user: $user, guard: 'web');
    expect(value: auth()->check())->toBeTrue();

    app(abstract: LogoutUser::class)->execute(guard: 'web');
    expect(value: auth()->check())->toBeFalse();
});

it(description: 'invalidates the session', closure: function () {
    $user = User::factory()->create();

    app(abstract: LoginUser::class)->execute(user: $user, guard: 'web');
    $oldSessionId = session()->getId();

    app(abstract: LogoutUser::class)->execute(guard: 'web');

    expect(value: session()->getId())->not->toBe($oldSessionId);
});
