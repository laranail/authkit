<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Services;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;

/**
 * Runs a set of rules against input, on behalf of an action.
 *
 * The actions used to build their own validator inline, which meant the rules could not be changed
 * without replacing the entire action -- and with it the user creation or password update the
 * action also performed. The rules now live on the form requests, and this is the seam the actions
 * use to apply them when they are invoked outside an HTTP request: Fortify calls
 * CreateNewUser::create($input) directly, and a console command or a seeder may too, so validation
 * cannot depend on a request object having been resolved.
 *
 * Keeping the factory injected rather than reaching for the Validator facade is what lets a test
 * or a consumer substitute the whole mechanism.
 */
class UserValidationService
{
    public function __construct(private readonly ValidationFactory $validator) {}

    /**
     * Validate input, throwing Illuminate\Validation\ValidationException on failure.
     *
     * @param array<string, mixed> $input
     * @param array<string, array<int, mixed>> $rules
     * @param array<string, string> $messages
     *
     * @return array<string, mixed> the validated subset
     */
    public function validate(array $input, array $rules, array $messages = [], ?string $errorBag = null): array
    {
        $validator = $this->validator->make(data: $input, rules: $rules, messages: $messages);

        // The bag matters to the caller, not to this service: Fortify's own profile and password
        // screens read named bags, so a shared error bag would render the wrong messages under
        // the wrong form when both appear on one page.
        if ($errorBag !== null) {
            return $validator->validateWithBag(errorBag: $errorBag);
        }

        return $validator->validate();
    }
}
