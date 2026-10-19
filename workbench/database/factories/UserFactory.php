<?php

declare(strict_types=1);

namespace Workbench\Database\Factories;

use Illuminate\Support\Str;
use Workbench\App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Factories\Factory;
use Simtabi\Laranail\Package\Tools\Testing\TestCredentials;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => Hash::make(TestCredentials::password('factory')),
            'remember_token'    => Str::random(10),
        ];
    }
}
