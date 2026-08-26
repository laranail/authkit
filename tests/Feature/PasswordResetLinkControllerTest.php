<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractPasswordResetLinkController;

it('returns the same response for known and unknown password reset email addresses', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'known@example.com']);

    $controller = new class () extends AbstractPasswordResetLinkController {};

    $knownResponse = $controller->store(jsonRequest(['email' => 'known@example.com']));
    $unknownResponse = $controller->store(jsonRequest(['email' => 'unknown@example.com']));

    expect($knownResponse->getStatusCode())->toBe(200)
        ->and($unknownResponse->getStatusCode())->toBe(200)
        ->and($unknownResponse->getData(true))->toBe($knownResponse->getData(true));
});

function jsonRequest(array $data): Request
{
    return Request::create(
        uri: '/forgot-password',
        method: 'POST',
        parameters: $data,
        server: ['HTTP_ACCEPT' => 'application/json'],
    );
}
