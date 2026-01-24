<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::latest()->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $currentUser = $request->user();
        // Authorization: Only admins and managers can create users
        if (! $currentUser->isAdmin() && ! $currentUser->isManager()) {
            // Log unauthorized user creation attempt
            Log::warning('Unauthorized user creation attempt', [
                'user_id' => $currentUser->id,
                'user_email' => $currentUser->email,
                'action' => 'create_user',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::create($request->validated());

        // Log user creation for audit trail
        Log::info('User created', [
            'created_by_user_id' => $currentUser->id,
            'created_by_user_email' => $currentUser->email,
            'new_user_id' => $user->id,
            'new_user_email' => $user->email,
            'new_user_role' => $user->role,
        ]);

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource|JsonResponse
    {
        $currentUser = $request->user();
        // Authorization: Only admins and managers can update users
        if (! $currentUser->isAdmin() && ! $currentUser->isManager()) {
            // Log unauthorized user update attempt
            Log::warning('Unauthorized user update attempt', [
                'user_id' => $currentUser->id,
                'user_email' => $currentUser->email,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'action' => 'update_user',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $oldRole = $user->role;
        $user->update($request->validated());
        $freshUser = $user->fresh();

        // Log role changes specifically (security-sensitive)
        if ($oldRole !== $freshUser->role) {
            Log::info('User role changed', [
                'updated_by_user_id' => $currentUser->id,
                'updated_by_user_email' => $currentUser->email,
                'target_user_id' => $freshUser->id,
                'target_user_email' => $freshUser->email,
                'old_role' => $oldRole,
                'new_role' => $freshUser->role,
            ]);
        }

        return new UserResource($freshUser);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        // Authorization: Only admins can delete users
        if (! $currentUser->isAdmin()) {
            // Log unauthorized user deletion attempt
            Log::warning('Unauthorized user deletion attempt', [
                'user_id' => $currentUser->id,
                'user_email' => $currentUser->email,
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'action' => 'delete_user',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Log user deletion for audit trail
        Log::info('User deleted', [
            'deleted_by_user_id' => $currentUser->id,
            'deleted_by_user_email' => $currentUser->email,
            'deleted_user_id' => $user->id,
            'deleted_user_email' => $user->email,
            'deleted_user_role' => $user->role,
        ]);

        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    /**
     * Get tickets for a specific user.
     */
    public function tickets(User $user): AnonymousResourceCollection
    {
        $tickets = $user->tickets()
            ->with(['category', 'manager', 'agent'])
            ->latest()
            ->paginate(15);

        return TicketResource::collection($tickets);
    }
}
