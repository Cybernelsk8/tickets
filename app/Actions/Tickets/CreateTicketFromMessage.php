<?php

namespace App\Actions\Tickets;

use App\Models\MailMessage;
use App\Models\Ticket;
use App\TaskStatus;
use App\TicketPriority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTicketFromMessage
{
    /**
     * Create a ticket from an imported email, reusing an existing one if present.
     */
    public function handle(MailMessage $message, int|string|null $createdBy = null): Ticket
    {
        Gate::authorize('create', Ticket::class);

        $existingTicket = Ticket::query()->where('mail_message_id', $message->id)->first();

        if ($existingTicket !== null) {
            return $existingTicket;
        }

        return DB::transaction(function () use ($message, $createdBy): Ticket {
            return Ticket::create([
                'code' => $this->nextCode(),
                'source' => 'email',
                'mail_message_id' => $message->id,
                'subject' => $message->subject,
                'description' => $message->body_text,
                'requester_name' => $message->from_name,
                'requester_email' => $message->from_email,
                'priority' => TicketPriority::Medium,
                'status' => TaskStatus::Todo,
                'created_by' => $createdBy ?? auth()->id(),
            ]);
        });
    }

    /**
     * Build the next sequential code without relying on identifier gaps.
     */
    private function nextCode(): string
    {
        $lastCode = Ticket::query()
            ->where('code', 'like', 'TKT-%')
            ->selectRaw('MAX(CAST(SUBSTRING(code, 5) AS UNSIGNED)) as max_code')
            ->value('max_code');

        $code = sprintf('TKT-%04d', ((int) $lastCode) + 1);

        while (Ticket::query()->where('code', $code)->exists()) {
            $code = sprintf('TKT-%04d', ((int) substr($code, 4)) + 1);
        }

        return $code;
    }
}
