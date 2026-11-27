<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Simtabi\Laranail\AuthKit\Services\UserValidationService;
use Simtabi\Laranail\AuthKit\Http\Requests\UpdateProfileInformationRequest;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function __construct(
        private UserValidationService $validation,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function update($user, array $input): void
    {
        // Normalised before validating for the same reason as registration: a differently-cased
        // address is the same address, and comparing it as typed lets a duplicate through.
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower(value: $input['email']);
        }

        $this->validation->validate(
            input: $input,
            rules: UpdateProfileInformationRequest::rulesFor(
                table: $user->getTable(),
                ignoreId: $user->getKey(),
            ),
            errorBag: 'updateProfileInformation',
        );

        if ($input['email'] !== $user->email && $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser(user: $user, input: $input);
            $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\ProfileUpdated($user::class, (string) $user->getAuthIdentifier()));

            return;
        }

        $user->forceFill(attributes: [
            'name'  => $input['name'],
            'email' => $input['email'],
        ])->save();
        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\ProfileUpdated($user::class, (string) $user->getAuthIdentifier()));
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
