<?php

namespace App\Models;

use Database\Factories\TaskStatusTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $task_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $changed_by
 * @property Carbon $occurred_at
 * @property string|null $note
 */
#[Fillable(['task_id', 'from_status', 'to_status', 'changed_by', 'occurred_at', 'note'])]
class TaskStatusTransition extends Model
{
    /** @use HasFactory<TaskStatusTransitionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
