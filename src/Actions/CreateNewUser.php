<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Validation\Rule;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;
use Simtabi\Laranail\AuthKit\Http\Requests\RegisterRequest;
use Simtabi\Laranail\AuthKit\Services\UserValidationService;
use Simtabi\Laranail\AuthKit\Services\UserProvisioningService;
use Laravel\Fortify\Contracts\CreatesNewUsers as FortifyCreateNewUser;

class CreateNewUser implements FortifyCreateNewUser
{
    public function __construct(
        private UserProvisioningService $provisioning,
        private UserValidationService $validation,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    public function create(array $input): Authenticatable
    {
        $model = UserModelResolver::resolve();

        // Normalise before validating, not after. The address was validated as typed and then
        // stored lowercased, so registering ADA@example.com against an existing ada@example.com
        // passed the unique rule -- no row matched that exact casing -- and then hit the database
        // constraint. The user saw a 500 from a PDOException where they should have seen "this
        // email is already taken".
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = $this->provisioning->normaliseEmail($input['email']);
        }

        $this->validation->validate(
            input: $input,
            rules: RegisterRequest::rulesFor(),
        );

        $user = $this->provisioning->create(attributes: [
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => $input['password'],
        ]);

        $this->events->dispatch(new \Simtabi\Laranail\AuthKit\Events\UserRegistered($user::class, (string) $user->getAuthIdentifier()));

        return $user;
    }
}
