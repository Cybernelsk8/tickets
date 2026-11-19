<?php

namespace App\Models;

use Database\Factories\MailAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $imap_host
 * @property int $imap_port
 * @property string $imap_encryption
 * @property string $smtp_host
 * @property int $smtp_port
 * @property string $smtp_encryption
 * @property string $username
 * @property string $credentials
 * @property int|null $owner_user_id
 * @property bool $is_active
 * @property int $sync_every_minutes
 * @property Carbon|null $last_synced_at
 */
#[Fillable([
    'name', 'email', 'imap_host', 'imap_port', 'imap_encryption',
    'smtp_host', 'smtp_port', 'smtp_encryption', 'username', 'credentials',
    'owner_user_id', 'is_active', 'sync_every_minutes', 'last_synced_at',
])]
#[Hidden(['credentials'])]
class MailAccount extends Model
{
    /** @use HasFactory<MailAccountFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'is_active' => 'boolean',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'sync_every_minutes' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return HasMany<MailMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    /** @return HasMany<MailSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(MailSyncRun::class);
    }
}
