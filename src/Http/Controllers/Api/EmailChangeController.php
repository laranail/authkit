<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\AuthKit\Services\EmailChange;

final class EmailChangeController
{
    public function show(Request $request, EmailChange $changes): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => ['pending' => $changes->pending($request->user())]])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, EmailChange $changes): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $changes->request($request->user(), $input['email']);

        return $this->show($request, $changes);
    }

    public function verify(Request $request, EmailChange $changes): JsonResponse
    {
        $input = $request->validate(['token' => ['required', 'string', 'size:64']]);
        $changes->confirm($request->user(), $input['token']);

        return $this->show($request, $changes);
    }

    public function destroy(Request $request, EmailChange $changes): JsonResponse
    {
        $changes->cancel($request->user());

        return $this->show($request, $changes);
    }
}
