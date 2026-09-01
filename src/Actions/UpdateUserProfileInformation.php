<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Simtabi\Laranail\AuthKit\Http\Requests\UpdateProfileInformationRequest;
use Simtabi\Laranail\AuthKit\Services\UserValidationService;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update($user, array $input): void
    {
        // Normalised before validating for the same reason as registration: a differently-cased
        // address is the same address, and comparing it as typed lets a duplicate through.
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower(value: $input['email']);
        }

        app(abstract: UserValidationService::class)->validate(
            input: $input,
            rules: UpdateProfileInformationRequest::rulesFor(
                table: $user->getTable(),
                ignoreId: $user->getKey(),
            ),
            errorBag: 'updateProfileInformation',
        );

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser(user: $user, input: $input);

            return;
        }

        $user->forceFill(attributes: [
            'name' => $input['name'],
            'email' => $input['email'],
        ])->save();
    }

    protected function updateVerifiedUser($user, array $input): void
    {
        $user->forceFill(attributes: [
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
