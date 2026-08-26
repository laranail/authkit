<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers;

use Illuminate\Http\Request;
use Simtabi\Laranail\AuthKit\Contracts\CheckEmailExistsInterface;
use Simtabi\Laranail\AuthKit\Http\Requests\CheckEmailExistsRequest;

abstract class AbstractCheckEmailExistsController extends AbstractAuthController
{
    public function __invoke(CheckEmailExistsRequest $request, CheckEmailExistsInterface $action): mixed
    {
        $exists = $action->execute(
            request: $request,
            guard: $this->guard(),
        );

        if ($request->expectsJson()) {
            return $this->jsonResponse(status: 'passed', data: ['exists' => $exists]);
        }

        return $this->respond(request: $request, exists: $exists);
    }

    protected function respond(Request $request, bool $exists): mixed
    {
        return response()->json(data: ['exists' => $exists]);
    }
}
