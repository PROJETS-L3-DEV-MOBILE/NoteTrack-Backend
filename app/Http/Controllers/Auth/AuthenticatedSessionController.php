<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedSessionController extends Controller
{
    // Login
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();

        $days = ($user->role === 'admin') ? 1 : 2;
        $accessTokenExpiration = now()->addDays($days);
        $refreshTokenExpiration = now()->addDays(14);

        $accessToken = $user->createToken('access_token', ['access-api'], $accessTokenExpiration)->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], $refreshTokenExpiration)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $accessTokenExpiration->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ]
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tokenCan('issue-access-token')) {
            return response()->json(['message' => 'Forbidden action with this token.'], 403);
        }

        $days = ($user->role === 'admin') ? 1 : 2;
        $accessTokenExpiration = now()->addDays($days);

        $user->tokens()->where('name', 'access_token')->delete();

        $newAccessToken = $user->createToken('access_token', ['access-api'], $accessTokenExpiration)->plainTextToken;

        return response()->json([
            'status' => 'success',
            'access_token' => $newAccessToken,
            'expires_at' => $accessTokenExpiration->toIso8601String()
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout successful',
        ]);
    }
}
