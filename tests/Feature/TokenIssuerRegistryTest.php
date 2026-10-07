<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerInterface;
use Simtabi\Laranail\AuthKit\Services\TokenIssuerRegistry;
use Simtabi\Laranail\AuthKit\Support\TokenResult;

it('dispatches issuance to the configured token issuer', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $result = new TokenResult($user, 'passport-token');
    $issuer = Mockery::mock(TokenIssuerInterface::class);
    $issuer->shouldReceive('issue')->once()->with($user, 'client', ['user:read'], null, false)->andReturn($result);

    config()->set('laranail.authkit.tokens.driver', 'passport');
    $registry = new TokenIssuerRegistry;
    $registry->register('passport', $issuer);

    expect($registry->issue($user, 'client', ['user:read']))->toBe($result);
});

it('asks every registered issuer to revoke a user current token and all tokens', function (): void {
    $user = Mockery::mock(Authenticatable::class);
    $issuer = Mockery::mock(TokenIssuerInterface::class);
    $issuer->shouldReceive('revokeCurrent')->once()->with($user);
    $issuer->shouldReceive('revokeAll')->once()->with($user);

    $registry = new TokenIssuerRegistry;
    $registry->register('test', $issuer);
    $registry->revokeCurrent($user);
    $registry->revokeAll($user);
});
