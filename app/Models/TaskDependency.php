<?php

namespace App\Models;

use Database\Factories\TaskDependencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $task_id
 * @property int $depends_on_task_id
 * @property string $type
 */
#[Fillable(['task_id', 'depends_on_task_id', 'type'])]
class TaskDependency extends Model
{
    /** @use HasFactory<TaskDependencyFactory> */
    use HasFactory;

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function dependsOnTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }
}
