<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobileLoginRequest;
use App\Http\Resources\Api\V1\DriverProfileResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate driver and issue Sanctum token.
     */
    public function login(MobileLoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (! $user->isDriver()) {
            return response()->json([
                'message' => 'Access denied. Only driver accounts can authenticate on the mobile API.',
            ], 403);
        }

        $deviceName = $request->input('device_name', 'Mobile App');
        $token = $user->createToken($deviceName, ['role:driver'])->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new DriverProfileResource($user),
                'token' => $token,
            ],
            'message' => 'Login successful.',
        ]);
    }

    /**
     * Revoke current Sanctum token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Retrieve authenticated driver profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new DriverProfileResource($request->user()),
        ]);
    }
}
