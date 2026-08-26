<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\TokenResult;
use Simtabi\Laranail\AuthKit\Contracts\IssueTokenForUserInterface;

class IssueTokenForUser implements IssueTokenForUserInterface
{
    /** @param array<int, string> $abilities */
    public function execute(Authenticatable $user, ?string $name = null, array $abilities = ['*']): TokenResult
    {
        $token = $user->createToken(
            name: $name ?? 'api-token',
            abilities: $abilities,
        );

        return new TokenResult(
            user: $user,
            token: $token->plainTextToken,
        );
    }
}
