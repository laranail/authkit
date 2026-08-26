<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckEmailExistsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
        ];
    }
}
