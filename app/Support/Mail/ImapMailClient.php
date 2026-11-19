<?php

namespace App\Support\Mail;

use App\Models\MailAccount;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class ImapMailClient implements MailClient
{
    /**
     * Fetch messages from the configured inbound folder.
     */
    public function fetchMessages(MailAccount $account, string $folder = 'INBOX'): array
    {
        $client = $this->makeClient($account);

        try {
            $client->connect();

            $mailFolder = $client->getFolder($folder);

            if ($mailFolder === null) {
                throw new RuntimeException("No se pudo abrir la carpeta IMAP [{$folder}].");
            }

            // Zimbra rejects an empty SEARCH criteria, so make "all messages" explicit.
            $messages = $mailFolder->messages()
                ->whereAll()
                ->setFetchBody(true)
                ->setFetchFlags(true)
                ->setFetchOrderDesc()
                ->limit((int) config('mail.inbound.batch_limit', 100))
                ->get();

            $inboundMessages = [];

            foreach ($messages as $message) {
                $inboundMessages[] = $this->toInboundMessage($message, $folder, $account->email);
            }

            return $inboundMessages;
        } finally {
            $this->disconnect($client);
        }
    }

    /**
     * Verify that the stored IMAP settings can open a connection.
     */
    public function testConnection(MailAccount $account): bool
    {
        $client = $this->makeClient($account);

        try {
            $client->connect();
            $folder = $client->getFolder((string) config('mail.inbound.folder', 'INBOX'));

            return $folder !== null;
        } catch (Throwable) {
            return false;
        } finally {
            $this->disconnect($client);
        }
    }

    /**
     * Build a Webklex client from the encrypted account credentials.
     */
    private function makeClient(MailAccount $account): Client
    {
        return (new ClientManager)->make([
            'host' => $account->imap_host,
            'port' => $account->imap_port,
            'encryption' => $this->imapEncryption($account->imap_encryption),
            'validate_cert' => true,
            'username' => $account->username,
            'password' => $account->credentials,
            'timeout' => (int) config('mail.inbound.timeout_seconds', 30),
        ]);
    }

    /**
     * Translate the account encryption setting into a Webklex value.
     */
    private function imapEncryption(string $encryption): string|false
    {
        return match (strtolower($encryption)) {
            'none', '' => false,
            'tls' => 'tls',
            default => 'ssl',
        };
    }

    /**
     * Convert a Webklex message into a transport-agnostic DTO.
     */
    private function toInboundMessage(Message $message, string $folder, string $accountEmail): InboundMessage
    {
        $fromAddress = $this->firstAddress($message->getFrom());

        $dateValue = $message->getDate()->first();

        $receivedAt = $dateValue instanceof DateTimeInterface
            ? CarbonImmutable::instance($dateValue)
            : CarbonImmutable::now();

        $attachments = [];

        foreach ($message->getAttachments() as $vendorAttachment) {
            $content = $vendorAttachment->content;

            if (! is_string($content) || $content === '') {
                continue;
            }

            $attachments[] = new InboundAttachment(
                name: $this->stringValue($vendorAttachment->name) ?? 'attachment.bin',
                contentType: $this->stringValue($vendorAttachment->content_type) ?? 'application/octet-stream',
                content: $content,
            );
        }

        return new InboundMessage(
            folder: $folder,
            externalUid: (string) $message->getUid(),
            messageId: $this->stringValue($message->getMessageId()),
            references: $this->stringList($message->getReferences()),
            inReplyTo: $this->stringValue($message->getInReplyTo()),
            subject: MimeHeaderDecoder::decode($this->stringValue($message->getSubject())) ?? '(sin asunto)',
            fromName: MimeHeaderDecoder::decode($fromAddress?->personal),
            fromEmail: $fromAddress?->mail ?: $this->stringValue($message->getFrom()) ?: $accountEmail,
            to: $this->stringList($message->getTo()),
            cc: $this->stringList($message->getCc()),
            bodyText: $this->bodyString($message->getTextBody()),
            bodyHtml: $this->bodyString($message->getHtmlBody()),
            receivedAt: $receivedAt,
            isRead: $message->hasFlag('\\Seen'),
            attachments: $attachments,
        );
    }

    /**
     * Return the first address from an IMAP address attribute.
     */
    private function firstAddress(mixed $value): ?Address
    {
        $items = $value instanceof Attribute ? $value->all() : [$value];

        foreach ($items as $item) {
            if ($item instanceof Address) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Normalize an IMAP attribute into a list of non-empty strings.
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        $items = $value instanceof Attribute ? $value->all() : (is_array($value) ? $value : [$value]);
        $result = [];

        foreach ($items as $item) {
            $text = $this->stringValue($item);

            if ($text !== null) {
                $result[] = $text;
            }
        }

        return $result;
    }

    /**
     * Normalize a scalar IMAP attribute into a string.
     */
    private function stringValue(mixed $value): ?string
    {
        if ($value instanceof Attribute) {
            return $this->stringValue($value->first());
        }

        if ($value instanceof Address) {
            return $value->mail !== '' ? $value->mail : null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Keep message bodies intact, including line breaks.
     */
    private function bodyString(mixed $value): ?string
    {
        if ($value instanceof Attribute) {
            $value = $value->first();
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Disconnect without masking the original synchronization error.
     */
    private function disconnect(Client $client): void
    {
        try {
            $client->disconnect();
        } catch (Throwable) {
            // The connection may already be closed.
        }
    }
}
