<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Mail\TicketAssignedMail;
use Illuminate\Support\Facades\Mail;

class NotifyRequesterTicketAssigned
{
    /**
     * Handle the event.
     */
    public function handle(TicketAssigned $event): void
    {
        $recipient = $event->ticket->requester_email;

        if ($recipient === null || $recipient === '') {
            return;
        }

        Mail::to($recipient)->send(new TicketAssignedMail($event->ticket, $event->assignedBy));
    }
}
