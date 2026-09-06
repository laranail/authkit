<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\AuthKit\Support\AuthKit;
use Simtabi\Laranail\AuthKit\Contracts\LogoutUserInterface;
use Simtabi\Laranail\AuthKit\Http\Controllers\AbstractLogoutController;

class LogoutController extends AbstractLogoutController
{
    public function __invoke(Request $request, LogoutUserInterface $action): JsonResponse
    {
        $action->execute(guard: $this->guard());
        $request->user()?->currentAccessToken()?->delete();

        return $this->jsonResponse(status: 'success', data: [
            'message' => 'Logged out successfully.',
        ]);
    }

    protected function guard(): string
    {
        return AuthKit::guard();
    }

    protected function loggedOut(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'status' => 'logged_out',
        ]);
    }
}
