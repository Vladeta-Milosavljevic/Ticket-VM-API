<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Handles user authentication (login/logout) for API.
 */
class AuthController extends Controller
{
    /**
     * Authenticate a user using bearer token authentication.
     *
     * Business Rules:
     * - Validates email and password
     * - Creates a bearer token for API authentication
     * - Returns user data and access token
     * - Logs successful and failed login attempts
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // Attempt authentication
        if (! Auth::attempt($credentials)) {
            // Log failed login attempt
            Log::warning('Failed login attempt', [
                'email' => $credentials['email'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();

        // Create bearer token for API authentication
        $token = $user->createToken('api-token')->plainTextToken;

        // Log successful login
        Log::info('User logged in', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => new UserResource($user),
        ], 200);
    }

    /**
     * Logout the current user (revoke bearer token).
     *
     * Business Rules:
     * - Requires authentication (via auth:sanctum middleware)
     * - Revokes the current bearer token
     * - Logs logout event
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Log logout before revoking token
        Log::info('User logged out', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
        ]);

        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }
}
