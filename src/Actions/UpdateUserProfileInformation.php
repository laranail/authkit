<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update($user, array $input): void
    {
        Validator::make(data: $input, rules: [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(table: $user->getTable())->ignore(id: $user->getKey()),
            ],
        ])->validateWithBag(errorBag: 'updateProfileInformation');

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser(user: $user, input: $input);

            return;
        }

        $user->forceFill(attributes: [
            'name'  => $input['name'],
            'email' => $input['email'],
        ])->save();
    }

    protected function updateVerifiedUser($user, array $input): void
    {
        $user->forceFill(attributes: [
            'name'              => $input['name'],
            'email'             => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
