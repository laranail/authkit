<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Requests;

use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

class UpdatePasswordRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public static function rulesFor(string $guard): array
    {
        return [
            'current_password' => ['required', 'string', "current_password:{$guard}"],
            'password'         => ['required', 'string', Password::default(), 'confirmed'],
        ];
    }

    /** @return array<string, string> */
    public static function messagesFor(): array
    {
        return [
            'current_password.current_password' => __(key: 'The provided password does not match your current password.'),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::rulesFor(guard: AuthKit::guard());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return self::messagesFor();
    }
}
