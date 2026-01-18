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
        // Authorization: Only admins and managers can create users
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = User::create($request->validated());

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
        // Authorization: Only admins and managers can update users
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->update($request->validated());

        return new UserResource($user->fresh());
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Authorization: Only admins can delete users
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

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
