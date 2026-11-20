<?php

namespace App\Models;

use App\TaskStatus;
use App\TicketPriority;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $source
 * @property int|null $mail_message_id
 * @property string $subject
 * @property string|null $description
 * @property string|null $requester_name
 * @property string|null $requester_email
 * @property int|null $area_id
 * @property int|null $category_id
 * @property TicketPriority $priority
 * @property TaskStatus $status
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property Carbon|null $due_at
 * @property Carbon|null $assigned_at
 * @property Carbon|null $closed_at
 * @property Area|null $area
 * @property TicketCategory|null $category
 * @property User|null $assignee
 * @property User|null $creator
 * @property Task|null $rootTask
 */
#[Fillable([
    'code', 'source', 'mail_message_id', 'subject', 'description',
    'requester_name', 'requester_email', 'area_id', 'category_id', 'priority',
    'status', 'assignee_id', 'created_by', 'due_at', 'assigned_at', 'closed_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<MailMessage, $this> */
    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<TicketCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasOne<Task, $this> */
    public function rootTask(): HasOne
    {
        return $this->hasOne(Task::class)->whereNull('parent_id');
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
