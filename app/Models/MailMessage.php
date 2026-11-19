<?php

namespace App\Models;

use Database\Factories\MailMessageFactory;
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
 * @property int $mail_account_id
 * @property string $folder
 * @property string $external_uid
 * @property string|null $message_id
 * @property string $thread_key
 * @property int|null $parent_message_id
 * @property string $subject
 * @property string|null $from_name
 * @property string $from_email
 * @property string|null $recipients
 * @property string|null $cc_recipients
 * @property string|null $body_text
 * @property string|null $body_html
 * @property Carbon|null $received_at
 * @property bool $is_read
 * @property Carbon|null $synced_at
 */
#[Fillable([
    'mail_account_id', 'folder', 'external_uid', 'message_id', 'thread_key',
    'parent_message_id', 'subject', 'from_name', 'from_email', 'recipients',
    'cc_recipients', 'body_text', 'body_html', 'received_at', 'is_read', 'synced_at',
])]
class MailMessage extends Model
{
    /** @use HasFactory<MailMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'synced_at' => 'datetime',
            'is_read' => 'boolean',
        ];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }

    /** @return BelongsTo<MailMessage, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_message_id');
    }

    /** @return HasMany<MailMessage, $this> */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_message_id');
    }

    /** @return HasOne<Ticket, $this> */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
