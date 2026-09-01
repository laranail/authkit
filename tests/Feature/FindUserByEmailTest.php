<?php

declare(strict_types=1);

use Simtabi\Laranail\AuthKit\Actions\FindUserByEmail;
use Workbench\App\Models\User;

it(description: 'returns user when the email exists', closure: function (): void {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $action = app(FindUserByEmail::class);

    $found = $action->execute(email: 'existing@example.com', guard: 'web');

    expect($found)->not->toBeNull()
        ->and($found->email)->toBe('existing@example.com');
});

it(description: 'returns null when the email does not exist', closure: function (): void {
    $action = app(FindUserByEmail::class);

    expect($action->execute(email: 'nobody@example.com', guard: 'web'))->toBeNull();
});

it(description: 'respects a custom guard', closure: function (): void {
    $user = User::factory()->create([
        'email' => 'guardtest@example.com',
    ]);

    $action = app(FindUserByEmail::class);

    $found = $action->execute(
        email: 'guardtest@example.com',
        guard: config('laranail.authkit.guard'),
    );

    expect($found)->not->toBeNull()
        ->and($found->email)->toBe('guardtest@example.com');
});
