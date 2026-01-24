<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

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

        // Log ticket creation for audit trail
        Log::info('Ticket created', [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'manager_id' => $ticket->manager_id,
            'agent_id' => $ticket->agent_id,
            'urgency' => $ticket->urgency,
            'status' => $ticket->status,
        ]);

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
        $oldStatus = $ticket->status;
        $ticket->update($request->validated());

        // Log status changes for workflow tracking
        $freshTicket = $ticket->fresh();
        if ($oldStatus !== $freshTicket->status) {
            Log::info('Ticket status changed', [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'ticket_id' => $freshTicket->id,
                'ticket_title' => $freshTicket->title,
                'old_status' => $oldStatus,
                'new_status' => $freshTicket->status,
            ]);
        }

        // Refresh to get latest data including any database-level changes
        return new TicketResource($freshTicket);
    }

    /**
     * Delete the specified ticket.
     *
     * Authorization: Only admins and managers can delete tickets.
     */
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();
        if (! $user->isAdmin() && ! $user->isManager()) {
            // Log unauthorized deletion attempt
            Log::warning('Unauthorized ticket deletion attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'delete',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Log ticket deletion for audit trail
        Log::info('Ticket deleted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'ticket_status' => $ticket->status,
        ]);

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
        $user = $request->user();
        // Only ticket manager or admins can assign agents
        if (! $user->isAdmin() && $ticket->manager_id !== $user->id) {
            // Log unauthorized assignment attempt
            Log::warning('Unauthorized ticket assignment attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'assign',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'agent_id' => ['required', 'exists:users,id'],
        ]);
        $oldAgentId = $ticket->agent_id;

        // Verify the agent is an agent
        $agent = User::findOrFail($validated['agent_id']);
        if (! $agent->isAgent()) {
            return response()->json(['message' => 'User is not an agent'], 400);
        }
        $ticket->update(['agent_id' => $validated['agent_id']]);

        // Log agent assignment for workflow tracking
        Log::info('Agent assigned to ticket', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'old_agent_id' => $oldAgentId,
            'new_agent_id' => $validated['agent_id'],
        ]);

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
        $user = $request->user();
        // Only assigned agent or admins can mark ticket as complete
        if (! $user->isAdmin() && $ticket->agent_id !== $user->id) {
            // Log unauthorized completion attempt
            Log::warning('Unauthorized ticket completion attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'complete',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }
        if (! $ticket->agent_id) {
            return response()->json(['message' => 'Ticket must have an assigned agent'], 400);
        }
        if ($ticket->status === 'completed') {
            return response()->json(['message' => 'Ticket is already completed'], 400);
        }
        if (! in_array($ticket->status, ['open', 'in_progress'])) {
            return response()->json(['message' => 'Ticket cannot be completed in current status'], 400);
        }

        // Status changes to pending_review - manager must approve or reject
        $ticket->update(['status' => 'pending_review', 'completed_by_agent_at' => now()]);

        // Log ticket completion for workflow tracking
        Log::info('Ticket marked as completed by agent', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'agent_id' => $ticket->agent_id,
            'status' => 'pending_review',
        ]);

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
        $user = $request->user();
        // Only ticket manager or admins can approve completion
        if (! $user->isAdmin() && $ticket->manager_id !== $user->id) {
            // Log unauthorized approval attempt
            Log::warning('Unauthorized ticket approval attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'approve',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Only pending review tickets can be approved
        if ($ticket->status !== 'pending_review') {
            return response()->json(['message' => 'Ticket must be pending review to be approved'], 400);
        }
        // Final status - ticket is fully completed
        $ticket->update(['status' => 'completed', 'completed_by_manager_at' => now()]);

        // Log ticket approval for workflow tracking
        Log::info('Ticket approved by manager', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'manager_id' => $ticket->manager_id,
            'agent_id' => $ticket->agent_id,
            'status' => 'completed',
        ]);

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
        $user = $request->user();
        // Only ticket manager or admins can reject completion
        if (! $user->isAdmin() && $ticket->manager_id !== $user->id) {
            // Log unauthorized rejection attempt
            Log::warning('Unauthorized ticket rejection attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'reject',
            ]);

            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Only pending review tickets can be rejected
        if ($ticket->status !== 'pending_review') {
            return response()->json(['message' => 'Ticket must be pending review to be rejected'], 400);
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

        // Log ticket rejection for workflow tracking
        Log::info('Ticket rejected by manager', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'manager_id' => $ticket->manager_id,
            'agent_id' => $ticket->agent_id,
            'rejection_reason' => $validated['rejection_reason'],
            'status' => 'in_progress',
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
            // Log unauthorized comment access attempt
            Log::warning('Unauthorized comment access attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'view_comments',
            ]);

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
            // Log unauthorized comment creation attempt
            Log::warning('Unauthorized comment creation attempt', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ticket_id' => $ticket->id,
                'ticket_title' => $ticket->title,
                'action' => 'create_comment',
            ]);

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

        // Log comment creation for audit trail
        Log::info('Comment created on ticket', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'comment_id' => $comment->id,
            'is_internal' => $comment->is_internal,
        ]);

        // Eager load user relationship before returning
        return (new CommentResource($comment->load('user')))->response()->setStatusCode(201);
    }
}
