<?php

declare(strict_types=1);

namespace Simtabi\Laranail\AuthKit\Http\Controllers\Api;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Simtabi\Laranail\AuthKit\Services\TwoFactorAuthentication;

final class TwoFactorManagementController
{
    public function show(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => [
                'method' => $request->user()->two_factor_method instanceof BackedEnum
                    ? $request->user()->two_factor_method->value
                    : ($request->user()->two_factor_method ?? 'none'),
                'enabled'                  => $twoFactor->enabled($request->user()),
                'recovery_codes_remaining' => count($twoFactor->recoveryCodes($request->user())),
            ],
        ]);
    }

    public function begin(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        if ($twoFactor->enabled($user)) {
            abort(409, 'Two-factor authentication is already enabled.');
        }

        $setup = $twoFactor->begin($user);

        return response()->json(['status' => 'success', 'data' => $setup])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/']]);
        $codes = $twoFactor->confirm($request->user(), $validated['code']);

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'The authenticator code is invalid.']);
        }

        return response()->json([
            'status' => 'success',
            'data'   => ['method' => 'totp', 'recovery_codes' => $codes],
        ])->header('Cache-Control', 'no-store');
    }

    public function disable(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'min:6', 'max:20']]);
        $user = $request->user();

        if (! $twoFactor->verify($user, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator or recovery code is invalid.']);
        }

        $twoFactor->disable($user);

        return response()->json(['status' => 'success', 'data' => ['method' => 'none']]);
    }

    public function regenerateRecoveryCodes(Request $request, TwoFactorAuthentication $twoFactor): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'min:6', 'max:20']]);
        $user = $request->user();

        if (! $twoFactor->verify($user, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'The authenticator or recovery code is invalid.']);
        }

        return response()->json([
            'status' => 'success',
            'data'   => ['recovery_codes' => $twoFactor->rotateRecoveryCodes($user)],
        ])->header('Cache-Control', 'no-store');
    }
}
