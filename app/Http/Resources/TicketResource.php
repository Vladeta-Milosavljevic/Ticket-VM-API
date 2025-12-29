<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $title
 * @property string $description
 * @property 'low'|'medium'|'high'|'critical' $urgency
 * @property \Illuminate\Support\Carbon|null $deadline
 * @property 'open'|'in_progress'|'pending_review'|'completed'|'rejected'|'cancelled' $status
 * @property int|null $category_id
 * @property \App\Models\Category|null $category
 * @property int|null $comments_count
 * @property \App\Models\Comment[]|null $comments
 * @property \App\Models\User|null $manager
 * @property \App\Models\User|null $agent
 * @property \Illuminate\Support\Carbon|null $completed_by_agent_at
 * @property \Illuminate\Support\Carbon|null $completed_by_manager_at
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Model $resource
 */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'urgency' => $this->urgency,
            'deadline' => $this->deadline?->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'category_id' => $this->category_id,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'comments_count' => $this->whenCounted('comments'),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'manager' => new UserResource($this->whenLoaded('manager')),
            'agent' => new UserResource($this->whenLoaded('agent')),
            'completed_by_agent_at' => $this->completed_by_agent_at?->format('Y-m-d H:i:s'),
            'completed_by_manager_at' => $this->completed_by_manager_at?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
