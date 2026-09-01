<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\ResetsUserPasswords as FortifyResetUserPassword;
use Simtabi\Laranail\AuthKit\Http\Requests\ResetPasswordRequest;
use Simtabi\Laranail\AuthKit\Services\UserValidationService;

class ResetUserPassword implements FortifyResetUserPassword
{
    public function reset($user, array $input): void
    {
        app(abstract: UserValidationService::class)->validate(
            input: $input,
            rules: ResetPasswordRequest::rulesFor(),
        );

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'remember_token' => null,
        ])->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }
}
