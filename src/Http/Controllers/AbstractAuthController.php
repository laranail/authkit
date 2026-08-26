<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Simtabi\Laranail\AuthKit\Support\AuthKit;

abstract class AbstractAuthController
{
    protected function guard(): string
    {
        return AuthKit::guard();
    }

    protected function jsonResponse(string $status, mixed $data = [], int $code = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(data: [
            'status' => $status,
            'data'   => $data,
        ], status: $code);
    }
}
