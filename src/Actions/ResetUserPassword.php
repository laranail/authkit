<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Simtabi\Laranail\AuthKit\Services\UserValidationService;
use Simtabi\Laranail\AuthKit\Http\Requests\ResetPasswordRequest;
use Simtabi\Laranail\AuthKit\Contracts\TokenIssuerRegistryInterface;
use Laravel\Fortify\Contracts\ResetsUserPasswords as FortifyResetUserPassword;

class ResetUserPassword implements FortifyResetUserPassword
{
    public function __construct(
        private \Illuminate\Contracts\Hashing\Hasher $hasher,
        private UserValidationService $validation,
        private TokenIssuerRegistryInterface $issuers,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function reset($user, array $input): void
    {
        $this->validation->validate(
            input: $input,
            rules: ResetPasswordRequest::rulesFor(),
        );

        $user->forceFill([
            'password'       => $this->hasher->make($input['password']),
            'remember_token' => null,
        ])->save();

        $this->issuers->revokeAll($user);
        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\PasswordUpdated($user::class, (string) $user->getAuthIdentifier(), 'reset'));
    }
}
