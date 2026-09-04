<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractCheckEmailExistsController;

class CheckEmailExistsController extends AbstractCheckEmailExistsController
{
    protected function respond(Request $request, bool $exists): JsonResponse
    {
        return response()->json(data: ['exists' => $exists]);
    }
}
