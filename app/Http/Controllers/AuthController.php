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
     * Authenticate a user using session-based authentication (HTTP-only cookies).
     *
     * Business Rules:
     * - Validates email and password
     * - Creates session with HTTP-only cookie (JavaScript cannot access)
     * - Returns user data (no token needed - cookie handles auth)
     * - Logs successful and failed login attempts
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // Attempt authentication
        if (! Auth::attempt($credentials, remember: true)) {
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

        // Regenerate session to prevent session fixation attacks
        $request->session()->regenerate();

        $user = Auth::user();

        // Log successful login
        Log::info('User logged in', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
        ], 200);
    }

    /**
     * Logout the current user (destroy session).
     *
     * Business Rules:
     * - Requires authentication (via auth:sanctum middleware)
     * - Destroys session and invalidates HTTP-only cookie
     * - Logs logout event
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Log logout before destroying session
        Log::info('User logged out', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ip_address' => $request->ip(),
        ]);

        // Logout and invalidate session
        Auth::guard('web')->logout();

        // Regenerate CSRF token after logout
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }
}
