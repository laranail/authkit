<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Workbench\App\Models\User;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;
use Simtabi\Laranail\Package\Tools\Testing\AssertsNoStateLeak;
use Simtabi\Laranail\AuthKit\Actions\AttemptEmailPasswordLogin;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;

uses(AssertsNoStateLeak::class);

it('does not retain the previous identity in a reused credential action', function (): void {
    $password = TestCredentials::password('login');
    $attempt = app(AttemptEmailPasswordLogin::class);

    $this->assertNoStateLeaksBetweenRequests(function (string $marker) use ($password, $attempt): string {
        $email = $marker . '@example.test';
        User::factory()->create(['email' => $email, 'password' => $password]);
        $result = $attempt->execute(Request::create('/login', 'POST', ['email' => $email, 'password' => $password]), 'web');

        expect($result->isPassed())->toBeTrue();

        return $result->user->email;
    });
});

it('does not retain the previous token owner in the singleton issuer registry', function (): void {
    $registry = app(TokenIssuerRegistryInterface::class);

    $this->assertNoStateLeaksBetweenRequests(function (string $marker) use ($registry): string {
        $user = User::factory()->create(['email' => $marker . '@example.test']);
        $result = $registry->issue($user, $marker);

        expect($result->user->is($user))->toBeTrue();
        expect($user->tokens()->sole()->name)->toBe($marker);

        return $result->user->email;
    });
});
