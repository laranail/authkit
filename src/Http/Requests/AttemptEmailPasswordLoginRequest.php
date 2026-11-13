<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Simtabi\Laranail\AuthKit\Support\AuthKit;

class AttemptEmailPasswordLoginRequest extends FormRequest
{
    /**
     * The rules are derived from the configured identifiers rather than written per mode.
     *
     * With the default email-only configuration this is the rule set this request has always
     * had. Any wider set drops the `email` rule, because a username is not an address and
     * validating one as an address rejects every username before the lookup is reached.
     */
    public function rules(): array
    {
        return [
            AuthKit::loginField() => AuthKit::loginIsEmailOnly()
                ? ['required', 'string', 'email']
                : ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }
}
