<?php

namespace App\Support\Mail;

use App\Models\MailAccount;

interface MailClient
{
    /**
     * Fetch the inbound messages available for the given account and folder.
     *
     * @return list<InboundMessage>
     */
    public function fetchMessages(MailAccount $account, string $folder = 'INBOX'): array;

    /**
     * Determine whether the account settings can open an IMAP connection.
     */
    public function testConnection(MailAccount $account): bool;
}
