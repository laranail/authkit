<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Contracts\Auth\Authenticatable;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;

/**
 * Creates and updates the user record itself.
 *
 * The actions previously wrote to the model directly, which put three decisions inside each of
 * them: which model class to use, how a password is hashed, and how an address is normalised. An
 * application storing users somewhere other than a local Eloquent table -- an LDAP directory, an
 * upstream identity service -- had to replace every action to change any of it.
 *
 * Address normalisation lives here so it cannot drift between the paths that write a user. It has
 * already caused a real defect: registration validated the address as typed and stored it
 * lowercased, so a differently-cased duplicate passed the unique rule and then hit the database
 * constraint as a 500.
 */
class UserProvisioningService
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function create(array $attributes): Authenticatable
    {
        $model = UserModelResolver::resolve();

        /** @var Model&Authenticatable $user */
        $user = $model::query()->create([
            'name'     => $attributes['name'],
            'email'    => $this->normaliseEmail($attributes['email']),
            'password' => Hash::make(value: $attributes['password']),
        ]);

        return $user;
    }

    /**
     * An address is the same address whatever its casing, so it is stored one way.
     *
     * Callers normalise before validating rather than after, or a differently-cased duplicate
     * passes a unique rule that then fails at the database.
     */
    public function normaliseEmail(string $email): string
    {
        return Str::lower(value: mb_trim($email));
    }
}
