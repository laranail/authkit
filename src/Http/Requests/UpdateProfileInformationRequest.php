<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileInformationRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public static function rulesFor(string $table, mixed $ignoreId = null): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(table: $table)->ignore(id: $ignoreId),
            ],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return self::rulesFor(table: $this->user()?->getTable() ?? 'users', ignoreId: $this->user()?->getKey());
    }
}
