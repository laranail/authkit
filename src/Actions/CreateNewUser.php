<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;
use Laravel\Fortify\Contracts\CreatesNewUsers as FortifyCreateNewUser;

class CreateNewUser implements FortifyCreateNewUser
{
    public function create(array $input): Authenticatable
    {
        $model = UserModelResolver::resolve();

        // Normalise before validating, not after. The address was validated as typed and then
        // stored lowercased, so registering ADA@example.com against an existing ada@example.com
        // passed the unique rule -- no row matched that exact casing -- and then hit the database
        // constraint. The user saw a 500 from a PDOException where they should have seen "this
        // email is already taken".
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = Str::lower(value: $input['email']);
        }

        Validator::make(
            data: $input,
            rules: array_merge([
                'name'     => ['required', 'string', 'max:255'],
                'email'    => ['required', 'string', 'email', 'max:255', Rule::unique(table: $model)],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ], [
                config(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response') => AuthKit::turnstileRules(),
            ]),
        )->validate();

        /** @var \Illuminate\Database\Eloquent\Model&Authenticatable $user */
        $user = $model::query()->create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => Hash::make(value: $input['password']),
        ]);

        return $user;
    }
}
