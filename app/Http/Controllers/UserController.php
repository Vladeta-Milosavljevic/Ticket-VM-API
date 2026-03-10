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
     *
     * Authorization: Only admins and managers can create users.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $currentUser = $request->user();
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
     *
     * Authorization: Only admins and managers can update users.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource|JsonResponse
    {
        $currentUser = $request->user();
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
     *
     * Authorization: Only admins can delete users.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $currentUser = $request->user();

        // Log user deletion for audit trail
        Log::info('User deleted', [
            'deleted_by_user_id' => $currentUser->id,
            'deleted_by_user_email' => $currentUser->email,
            'deleted_user_id' => $user->id,
            'deleted_user_email' => $user->email,
            'deleted_user_role' => $user->role,
        ]);

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully'], 200);
    }

    /**
     * Get tickets for a specific user.
     *
     * Authorization: Admins, managers, or the user themselves can view.
     */
    public function tickets(User $user): AnonymousResourceCollection
    {
        $this->authorize('viewTickets', $user);

        $tickets = ($user->isManager() ? $user->managerTickets() : $user->tickets())
            ->with(['category', 'manager', 'agent'])
            ->latest()
            ->paginate(15);

        return TicketResource::collection($tickets);
    }

    /**
     * Restore a soft-deleted user.
     *
     * Authorization: Only admins can restore users.
     */
    public function restore(Request $request, User $user): UserResource|JsonResponse
    {
        $this->authorize('restore', $user);

        $currentUser = $request->user();

        if (! $user->trashed()) {
            return response()->json(['message' => 'User is not deleted'], 400);
        }

        $user->restore();

        Log::info('User restored', [
            'restored_by_user_id' => $currentUser->id,
            'restored_by_user_email' => $currentUser->email,
            'restored_user_id' => $user->id,
            'restored_user_email' => $user->email,
        ]);

        return new UserResource($user->fresh());
    }
}
