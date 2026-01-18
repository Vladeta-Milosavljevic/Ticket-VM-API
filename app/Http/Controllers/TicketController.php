<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Handles ticket lifecycle management and workflow operations.
 *
 * Ticket Workflow:
 * 1. Ticket created (any authenticated user)
 * 2. Manager assigns agent (via assign endpoint)
 * 3. Agent completes work (via complete endpoint)
 * 4. Manager approves/rejects (via approve/reject endpoints)
 *
 * Authorization Rules:
 * - Comments: Only ticket manager, assigned agent, or admins
 * - Assignment: Only ticket manager or admins
 * - Completion: Only assigned agent or admins
 * - Approval/Rejection: Only ticket manager or admins
 */
class TicketController extends Controller
{
    /**
     * Display a paginated listing of tickets.
     *
     * Eager loads relationships to prevent N+1 queries:
     * - category, manager, agent
     */
    public function index(): AnonymousResourceCollection
    {
        $tickets = Ticket::with(['category', 'manager', 'agent'])->latest()->paginate(15);

        return TicketResource::collection($tickets);
    }

    /**
     * Display the specified ticket with all related data.
     *
     * Eager loads:
     * - category, manager, agent
     * - comments with their authors (nested relationship)
     */
    public function show(Ticket $ticket): TicketResource
    {
        $ticket->load(['category', 'manager', 'agent', 'comments.user']);

        return new TicketResource($ticket);
    }

    /**
     * Create a new ticket.
     *
     * Authorization: Any authenticated user can create tickets.
     *
     * Business Rules:
     * - Managers/admins: manager_id optional (auto-assigned to themselves if omitted)
     * - Non-managers: manager_id required (must specify a manager)
     * - Only admins/managers can set agent_id during creation
     * - Others must use the assign endpoint to assign agents
     */
    public function store(StoreTicketRequest $request): TicketResource
    {
        $validated = $request->validated();

        // Auto-assign manager if user is a manager and no manager_id provided
        // This allows managers to quickly create tickets without specifying themselves
        if ($request->user()->isManager() && ! isset($validated['manager_id'])) {
            $validated['manager_id'] = $request->user()->id;
        }

        // Restrict agent assignment during creation to admins/managers only
        // Non-managers cannot assign agents - they must use the assign endpoint
        // This ensures proper workflow: ticket created → manager assigns agent
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            unset($validated['agent_id']);
        }

        $ticket = Ticket::create($validated);

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    /**
     * Update the specified ticket.
     *
     * Uses fresh() to ensure we return the latest data after update,
     * including any database-level changes (triggers, defaults, etc.)
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        $ticket->update($request->validated());

        // Refresh to get latest data including any database-level changes
        return new TicketResource($ticket->fresh());
    }

    /**
     * Delete the specified ticket.
     *
     * Authorization: Only admins and managers can delete tickets.
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully'], 200);
    }

    /**
     * Assign an agent to a ticket.
     *
     * Authorization: Only the ticket's manager or admins can assign agents.
     *
     * This is a separate endpoint from update() to enforce the workflow:
     * tickets are created first, then agents are assigned by managers.
     */
    public function assign(Request $request, Ticket $ticket): TicketResource|JsonResponse
    {
        // Only ticket manager or admins can assign agents
        if (! $request->user()->isAdmin() && $ticket->manager_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'agent_id' => ['required', 'exists:users,id'],
        ]);
        $ticket->update(['agent_id' => $validated['agent_id']]);

        return new TicketResource($ticket->fresh(['category', 'manager', 'agent']));
    }

    /**
     * Mark ticket as completed by agent.
     *
     * Authorization: Only the assigned agent or admins can complete tickets.
     *
     * Workflow: Changes status to 'pending_review' and records completion timestamp.
     * The ticket now awaits manager approval via the approve() or reject() endpoints.
     */
    public function complete(Request $request, Ticket $ticket): TicketResource|JsonResponse
    {
        // Only assigned agent or admins can mark ticket as complete
        if (! $request->user()->isAdmin() && $ticket->agent_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Status changes to pending_review - manager must approve or reject
        $ticket->update(['status' => 'pending_review', 'completed_by_agent_at' => now()]);

        return new TicketResource($ticket->fresh(['category', 'manager', 'agent']));
    }

    /**
     * Manager approves agent's completion.
     *
     * Authorization: Only the ticket's manager or admins can approve completion.
     *
     * Workflow: Changes status to 'completed' and records manager approval timestamp.
     * This finalizes the ticket workflow.
     */
    public function approve(Request $request, Ticket $ticket): TicketResource|JsonResponse
    {
        // Only ticket manager or admins can approve completion
        if (! $request->user()->isAdmin() && $ticket->manager_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Final status - ticket is fully completed
        $ticket->update(['status' => 'completed', 'completed_by_manager_at' => now()]);

        return new TicketResource($ticket->fresh(['category', 'manager', 'agent']));
    }

    /**
     * Manager rejects agent's completion and sends ticket back for revision.
     *
     * Authorization: Only the ticket's manager or admins can reject completion.
     *
     * Business Rules:
     * - Status resets to 'in_progress' (agent must redo work)
     * - completed_by_agent_at is cleared (work not accepted)
     * - rejection_reason is required (provides feedback for agent)
     * - rejected_at timestamp is recorded for tracking
     */
    public function reject(Request $request, Ticket $ticket): TicketResource|JsonResponse
    {
        // Only ticket manager or admins can reject completion
        if (! $request->user()->isAdmin() && $ticket->manager_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);
        $ticket->update([
            'status' => 'in_progress', // Reset status - agent must redo work
            'rejection_reason' => $validated['rejection_reason'], // Required feedback
            'rejected_at' => now(), // Track rejection timestamp
            'completed_by_agent_at' => null, // Clear previous completion - work not accepted
        ]);

        return new TicketResource($ticket->fresh(['category', 'manager', 'agent']));
    }

    /**
     * Get comments for a specific ticket.
     *
     * Authorization: Only ticket manager, assigned agent, or admins can view comments.
     * This ensures private conversations remain between relevant parties.
     *
     * Comments are paginated and ordered by latest first.
     * Eager loads user relationship to prevent N+1 queries.
     */
    public function comments(Request $request, Ticket $ticket): AnonymousResourceCollection|JsonResponse
    {
        $user = $request->user();
        // Comments are private - only ticket participants and admins can view
        $canViewComments = $user->isAdmin() || $ticket->manager_id === $user->id || $ticket->agent_id === $user->id;
        if (! $canViewComments) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Eager load user relationship to avoid N+1 queries
        $comments = $ticket->comments()->with('user')->latest()->paginate(15);

        return CommentResource::collection($comments);
    }

    /**
     * Store a new comment on a ticket.
     *
     * Authorization: Only ticket manager, assigned agent, or admins can add comments.
     *
     * Business Rules:
     * - body is required and limited to 255 characters
     * - is_internal is optional (defaults to false if not provided)
     * - user_id is automatically set to authenticated user
     */
    public function storeComment(Request $request, Ticket $ticket): CommentResource|JsonResponse
    {
        $user = $request->user();
        // Only ticket participants and admins can add comments
        $canStoreComments = $user->isAdmin() || $ticket->manager_id === $user->id || $ticket->agent_id === $user->id;
        if (! $canStoreComments) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:255'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);
        $comment = $ticket->comments()->create([
            'body' => $validated['body'],
            'is_internal' => $validated['is_internal'] ?? false, // Default to false if not provided
            'user_id' => $user->id, // Automatically set to authenticated user
        ]);

        // Eager load user relationship before returning
        return (new CommentResource($comment->load('user')))->response()->setStatusCode(201);
    }
}
