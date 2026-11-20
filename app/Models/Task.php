<?php

namespace App\Models;

use App\TaskStatus;
use App\TicketPriority;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $ticket_id
 * @property int|null $parent_id
 * @property string $title
 * @property string|null $description
 * @property int|null $assignee_id
 * @property int|null $area_id
 * @property TaskStatus $status
 * @property TicketPriority $priority
 * @property int|null $estimated_minutes
 * @property int|null $actual_minutes
 * @property int $position
 * @property Carbon|null $due_at
 * @property Carbon|null $started_at
 * @property Carbon|null $testing_at
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property int $children_count
 * @property int $subtasks_total_count
 * @property int $subtasks_done_count
 */
#[Fillable([
    'ticket_id', 'parent_id', 'title', 'description', 'assignee_id', 'area_id',
    'status', 'priority', 'estimated_minutes', 'actual_minutes', 'position',
    'due_at', 'started_at', 'testing_at', 'completed_at', 'created_by',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TicketPriority::class,
            'estimated_minutes' => 'integer',
            'actual_minutes' => 'integer',
            'position' => 'integer',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'testing_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Restrict a query to root tasks, which are the reportable deliverables.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Restrict a query to subtasks, which only report operational progress.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeSubtasks(Builder $query): void
    {
        $query->whereNotNull('parent_id');
    }

    /**
     * Restrict a query to root tasks whose subtasks are all finished.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeReadyForReview(Builder $query): void
    {
        $query->whereNull('parent_id')
            ->whereNot('status', TaskStatus::Done->value)
            ->whereHas('children')
            ->whereDoesntHave('children', fn (Builder $childQuery): mixed => $childQuery
                ->whereNot('status', TaskStatus::Done->value));
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Task, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TaskDependency, $this> */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    /** @return HasMany<TaskDependency, $this> */
    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    /** @return HasMany<TaskStatusTransition, $this> */
    public function statusTransitions(): HasMany
    {
        return $this->hasMany(TaskStatusTransition::class);
    }

    /** @return MorphMany<Comment, $this> */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
