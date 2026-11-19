<?php

namespace Tests\Support;

use App\Models\MailAccount;
use App\Support\Mail\InboundMessage;
use App\Support\Mail\MailClient;
use Throwable;

final class FakeMailClient implements MailClient
{
    /** @var array<int, list<InboundMessage>> */
    public array $messagesByAccount = [];

    /** @var list<string> */
    public array $folders = [];

    public bool $connectionSucceeds = true;

    public int $connectionChecks = 0;

    public ?Throwable $exception = null;

    public function fetchMessages(MailAccount $account, string $folder = 'INBOX'): array
    {
        $this->folders[] = $folder;

        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->messagesByAccount[$account->id] ?? [];
    }

    public function testConnection(MailAccount $account): bool
    {
        $this->connectionChecks++;

        return $this->connectionSucceeds;
    }
}
