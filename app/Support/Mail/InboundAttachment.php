<?php

namespace App\Support\Mail;

final readonly class InboundAttachment
{
    public function __construct(
        public string $name,
        public string $contentType,
        public string $content,
    ) {}

    /**
     * Return the binary length of the decoded attachment content.
     */
    public function size(): int
    {
        return strlen($this->content);
    }
}
