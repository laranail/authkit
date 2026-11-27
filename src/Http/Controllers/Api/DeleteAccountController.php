<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simtabi\Laranail\AuthKit\Contracts\DeleteUserInterface;

final class DeleteAccountController
{
    public function __invoke(Request $request, DeleteUserInterface $delete): Response
    {
        $input = $request->validate([
            'password' => ['required', 'string'],
            'id'       => ['prohibited'],
            'user_id'  => ['prohibited'],
        ]);
        // The route has no target identifier. Only the accepted guard's authenticated account
        // can be passed to the action, regardless of arbitrary extra input.
        $delete->execute($request->user(), $input['password']);
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
