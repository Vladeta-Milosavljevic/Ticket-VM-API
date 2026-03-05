<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Ticket extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Ticket $ticket): void {
            $ticket->comments->each->delete();
            foreach ($ticket->attachments as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
            $ticket->attachments()->delete();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'urgency',
        'deadline',
        'status',
        'category_id',
        'manager_id',
        'agent_id',
        'completed_by_agent_at',
        'completed_by_manager_at',
        'rejected_at',
        'rejection_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'completed_by_agent_at' => 'datetime',
            'completed_by_manager_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Scope a query to apply filters from the index request.
     *
     * @param  Builder<Ticket>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Ticket>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            match ($key) {
                'category_id' => $query->where('category_id', $value),
                'manager_id' => $query->where('manager_id', $value),
                'agent_id' => $query->where('agent_id', $value),
                'status' => $query->where('status', $value),
                'urgency' => $query->where('urgency', $value),
                'unassigned' => $value ? $query->whereNull('agent_id') : $query,
                'overdue' => $value ? $query->where('deadline', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled']) : $query,
                'search' => $query->where(function (Builder $q) use ($value) {
                    $search = '%'.$value.'%';
                    $q->where('title', 'like', $search)
                        ->orWhere('description', 'like', $search);
                }),
                'deadline_from' => $query->whereDate('deadline', '>=', $value),
                'deadline_to' => $query->whereDate('deadline', '<=', $value),
                default => $query,
            };
        }

        return $query;
    }
}
