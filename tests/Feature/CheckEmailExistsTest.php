<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use Simtabi\Laranail\AuthKit\Actions\CheckEmailExists;

function checkEmailRequest(string $email): Request
{
    return Request::create(uri: '/check-email', method: 'POST', parameters: ['email' => $email]);
}

it(description: 'returns true when the email exists', closure: function (): void {
    User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $action = app(CheckEmailExists::class);

    expect($action->execute(request: checkEmailRequest('existing@example.com'), guard: 'web'))->toBeTrue();
});

it(description: 'returns false when the email does not exist', closure: function (): void {
    $action = app(CheckEmailExists::class);

    expect($action->execute(request: checkEmailRequest('nobody@example.com'), guard: 'web'))->toBeFalse();
});

it(description: 'respects a custom guard', closure: function (): void {
    User::factory()->create([
        'email' => 'guardtest@example.com',
    ]);

    $action = app(CheckEmailExists::class);

    expect($action->execute(
        request: checkEmailRequest('guardtest@example.com'),
        guard: config('laranail.authkit.guard'),
    ))->toBeTrue();
});
