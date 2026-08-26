<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords as FortifyResetUserPassword;

class ResetUserPassword implements FortifyResetUserPassword
{
    public function reset($user, array $input): void
    {
        Validator::make($input, [
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ])->validate();

        $user->forceFill([
            'password'       => Hash::make($input['password']),
            'remember_token' => null,
        ])->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }
}
