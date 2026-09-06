<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Support\UserModelResolver;

/**
 * The rules a registration must satisfy.
 *
 * These live here rather than inside CreateNewUser so a consuming application can tighten them --
 * a longer password, an allow-listed email domain, an invite code -- by extending this request
 * instead of replacing the whole action and the user creation along with it.
 *
 * `rulesFor()` is static because the action is also reachable without an HTTP request at all:
 * Fortify calls CreateNewUser::create($input) directly, and a console command or a seeder may
 * too. Both paths therefore validate against exactly the same rules.
 */
class RegisterRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public static function rulesFor(): array
    {
        return array_merge([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique(table: UserModelResolver::resolve())],
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ], [
            config(key: 'laranail.authkit.turnstile.input', default: 'cf-turnstile-response') => AuthKit::turnstileRules(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::rulesFor();
    }
}
