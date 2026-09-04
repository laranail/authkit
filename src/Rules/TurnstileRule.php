<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Rules;

use Closure;
use Simtabi\Laranail\AuthKit\Services\Turnstile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class TurnstileRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! app(Turnstile::class)->validate(token: (string) $value)) {
            $fail(trans(key: 'The computer thinks you are a robot. Prove it wrong!'));
        }
    }
}
