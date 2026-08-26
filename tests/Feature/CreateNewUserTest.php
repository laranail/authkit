<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Simtabi\Laranail\AuthKit\Actions\CreateNewUser;

it(description: 'creates a user with a hashed password', closure: function (): void {
    $user = app(abstract: CreateNewUser::class)->create(input: [
        'name'                  => 'Ada Lovelace',
        'email'                 => 'ADA@EXAMPLE.COM',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(value: $user->email)->toBe(expected: 'ada@example.com')
        ->and(value: $user->password)->not->toBe(expected: 'password');
});

it(description: 'fails validation for duplicate email addresses', closure: function (): void {
    User::factory()->create(attributes: ['email' => 'ada@example.com']);

    $validator = Validator::make(data: [
        'name'                  => 'Ada Lovelace',
        'email'                 => 'ada@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ], rules: [
        'email' => ['required', 'string', 'email', 'max:255', Rule::unique(table: User::class)],
    ]);

    expect(value: $validator->fails())->toBeTrue()
        ->and(value: $validator->errors()->has(key: 'email'))->toBeTrue();
});

it(description: 'fails validation when password confirmation does not match', closure: function (): void {
    $validator = Validator::make(data: [
        'name'                  => 'Ada Lovelace',
        'email'                 => 'ada@example.com',
        'password'              => 'password',
        'password_confirmation' => 'different',
    ], rules: [
        'password' => ['required', 'string', Password::default(), 'confirmed'],
    ]);

    expect(value: $validator->fails())->toBeTrue()
        ->and(value: $validator->errors()->has(key: 'password'))->toBeTrue();
});

it(description: 'fails validation when name is missing', closure: function (): void {
    $validator = Validator::make(data: [
        'name'                  => '',
        'email'                 => 'ada@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ], rules: [
        'name' => ['required', 'string', 'max:255'],
    ]);

    expect(value: $validator->fails())->toBeTrue()
        ->and(value: $validator->errors()->has(key: 'name'))->toBeTrue();
});

it(description: 'fails validation when email is invalid', closure: function (): void {
    $validator = Validator::make(data: [
        'name'                  => 'Ada Lovelace',
        'email'                 => 'not-an-email',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ], rules: [
        'email' => ['required', 'string', 'email', 'max:255'],
    ]);

    expect(value: $validator->fails())->toBeTrue()
        ->and(value: $validator->errors()->has(key: 'email'))->toBeTrue();
});

it(description: 'fails validation when password is missing', closure: function (): void {
    $validator = Validator::make(data: [
        'name'  => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ], rules: [
        'password' => ['required', 'string', Password::default(), 'confirmed'],
    ]);

    expect(value: $validator->fails())->toBeTrue()
        ->and(value: $validator->errors()->has(key: 'password'))->toBeTrue();
});

it(description: 'resolves model from guard config when user_model is not set', closure: function (): void {
    config()->set(key: 'laranail.authkit.user_model', value: null);
    config()->set(key: 'laranail.authkit.guard', value: 'web');

    $user = app(abstract: CreateNewUser::class)->create(input: [
        'name'                  => 'Ada Lovelace',
        'email'                 => 'ada@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(value: $user)->toBeInstanceOf(class: User::class);
});

it(description: 'resolves model from explicit user_model config', closure: function (): void {
    config()->set(key: 'laranail.authkit.user_model', value: User::class);

    $user = app(abstract: CreateNewUser::class)->create(input: [
        'name'                  => 'Ada Lovelace',
        'email'                 => 'ada2@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(value: $user)->toBeInstanceOf(class: User::class);
});
