<?php

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

final readonly class InboundMessage
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $references
     * @param  list<InboundAttachment>  $attachments
     */
    public function __construct(
        public string $folder,
        public string $externalUid,
        public ?string $messageId,
        public array $references,
        public ?string $inReplyTo,
        public string $subject,
        public ?string $fromName,
        public string $fromEmail,
        public array $to,
        public array $cc,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public CarbonImmutable $receivedAt,
        public bool $isRead,
        public array $attachments,
    ) {}
}
