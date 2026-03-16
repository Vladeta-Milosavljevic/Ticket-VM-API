<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\IndexTicketRequest;
use App\Http\Requests\RejectTicketRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\CommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Supports filtering via query parameters: category_id, manager_id, agent_id,
     * status, urgency, unassigned, overdue, search, deadline_from, deadline_to.
     * Supports sorting via sort and order parameters.
     *
     * Eager loads relationships to prevent N+1 queries:
     * - category, manager, agent
     */
    public function index(IndexTicketRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $sort = $validated['sort'] ?? 'created_at';
        $order = $validated['order'] ?? 'desc';

        $tickets = Ticket::with(['category', 'manager', 'agent'])
            ->filter($validated)
            ->orderBy($sort, $order)
            ->paginate(15)
            ->withQueryString();

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
        $ticket->load(['category', 'manager', 'agent', 'comments.user', 'comments.attachments', 'attachments']);

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
        $ticketData = Arr::except($validated, ['attachments']);

        // Auto-assign manager if user is a manager and no manager_id provided
        if ($request->user()->isManager() && ! isset($ticketData['manager_id'])) {
            $ticketData['manager_id'] = $request->user()->id;
        }

        // Restrict agent assignment during creation to admins/managers only
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            unset($ticketData['agent_id']);
        }
        $ticket = Ticket::create($ticketData);

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($request->file('attachments'), $ticket, $request->user());
        }

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

        return new TicketResource($ticket->load(['category', 'manager', 'agent', 'attachments']));
    }

    /**
     * Workflow fields that only admin or assigned manager can update.
     * Others (e.g. assigned agent) can update title, description, urgency, deadline, category_id.
     *
     * @var list<string>
     */
    private const WORKFLOW_FIELDS = [
        'status',
        'manager_id',
        'agent_id',
        'completed_by_agent_at',
        'completed_by_manager_at',
        'rejected_at',
        'rejection_reason',
    ];

    /**
     * Update the specified ticket.
     *
     * Authorization: Admin, ticket manager, or assigned agent can update.
     * Workflow fields (status, manager_id, agent_id, timestamps, rejection_reason) are
     * only persisted when the user is admin or the ticket's assigned manager.
     * Uses fresh() to ensure we return the latest data after update.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        $validated = $request->validated();
        $ticketData = Arr::except($validated, ['attachments']);

        $canUpdateWorkflow = $request->user()->isAdmin()
            || $ticket->manager_id === $request->user()->id;

        if (! $canUpdateWorkflow) {
            $ticketData = Arr::except($ticketData, self::WORKFLOW_FIELDS);
        }

        $oldStatus = $ticket->status;
        $ticket->update($ticketData);

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($request->file('attachments'), $ticket, $request->user());
        }

        // Log status changes for workflow tracking
        $freshTicket = $ticket->fresh(['category', 'manager', 'agent', 'attachments']);
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
        $this->authorize('delete', $ticket);

        $user = $request->user();

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
    public function assign(AssignTicketRequest $request, Ticket $ticket): TicketResource|JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $oldAgentId = $ticket->agent_id;

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
        $this->authorize('complete', $ticket);

        $user = $request->user();
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
        $this->authorize('approve', $ticket);

        $user = $request->user();
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
    public function reject(RejectTicketRequest $request, Ticket $ticket): TicketResource|JsonResponse
    {
        $user = $request->user();
        // Only pending review tickets can be rejected
        if ($ticket->status !== 'pending_review') {
            return response()->json(['message' => 'Ticket must be pending review to be rejected'], 400);
        }

        $validated = $request->validated();
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
     * Store attachments for an attachable model (Ticket or Comment).
     */
    private function storeAttachments(array $files, Ticket|\App\Models\Comment $attachable, User $user): void
    {
        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $pathSegment = strtolower(class_basename($attachable::class)).'s';

        foreach ($files as $file) {
            $sanitizedFilename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                .'.'.$file->getClientOriginalExtension();

            // Store the file in the storage directory and return the path, also memory efficient.
            $path = $file->storeAs(
                "attachments/{$pathSegment}/{$attachable->id}",
                Str::uuid()."_{$sanitizedFilename}",
                ['disk' => $disk]
            );

            $attachable->attachments()->create([
                'filename' => $sanitizedFilename,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'disk' => $disk,
                'path' => $path,
                'user_id' => $user->id,
            ]);

            Log::info('Attachment uploaded', [
                'user_id' => $user->id,
                'attachable_type' => $attachable::class,
                'attachable_id' => $attachable->id,
                'filename' => $sanitizedFilename,
            ]);
        }
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
        $this->authorize('viewComments', $ticket);

        // Eager load user and attachments to avoid N+1 queries
        $comments = $ticket->comments()->with(['user', 'attachments'])->latest()->paginate(15);

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
    public function storeComment(StoreCommentRequest $request, Ticket $ticket): CommentResource|JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $commentData = Arr::except($validated, ['attachments']);

        $comment = $ticket->comments()->create([
            'body' => $commentData['body'],
            'is_internal' => $commentData['is_internal'] ?? false,
            'user_id' => $user->id,
        ]);

        if ($request->hasFile('attachments')) {
            $this->storeAttachments($request->file('attachments'), $comment, $user);
        }

        // Log comment creation for audit trail
        Log::info('Comment created on ticket', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'ticket_id' => $ticket->id,
            'ticket_title' => $ticket->title,
            'comment_id' => $comment->id,
            'is_internal' => $comment->is_internal,
        ]);

        return (new CommentResource($comment->load(['user', 'attachments'])))->response()->setStatusCode(201);
    }
}
