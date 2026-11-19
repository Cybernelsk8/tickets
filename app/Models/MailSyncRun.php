<?php

namespace App\Models;

use Database\Factories\MailSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mail_account_id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $processed
 * @property int $failed
 * @property string|null $error
 */
#[Fillable(['mail_account_id', 'started_at', 'finished_at', 'processed', 'failed', 'error'])]
class MailSyncRun extends Model
{
    /** @use HasFactory<MailSyncRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'processed' => 'integer',
            'failed' => 'integer',
        ];
    }

    /** @return BelongsTo<MailAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class, 'mail_account_id');
    }
}
