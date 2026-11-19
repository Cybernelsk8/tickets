<?php

namespace App\Actions\Mail;

use App\Models\Attachment;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Support\Mail\InboundAttachment;
use App\Support\Mail\InboundMessage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessInboundMessage
{
    /**
     * Persist one inbound message, its thread context and its attachments.
     */
    public function handle(MailAccount $account, InboundMessage $message): MailMessage
    {
        $existing = $this->findExisting($account, $message);

        if ($existing !== null) {
            $existing->update(['synced_at' => now()]);

            return $existing;
        }

        $mailMessage = MailMessage::create([
            'mail_account_id' => $account->id,
            'folder' => $message->folder,
            'external_uid' => $message->externalUid,
            'message_id' => $message->messageId,
            'thread_key' => $this->threadKey($account, $message),
            'parent_message_id' => $this->findParentId($account, $message),
            'subject' => Str::limit($message->subject, 255, ''),
            'from_name' => $message->fromName === null ? null : Str::limit($message->fromName, 255, ''),
            'from_email' => $message->fromEmail,
            'recipients' => $message->to === [] ? null : implode(', ', $message->to),
            'cc_recipients' => $message->cc === [] ? null : implode(', ', $message->cc),
            'body_text' => $message->bodyText,
            'body_html' => $message->bodyHtml,
            'received_at' => $message->receivedAt,
            'is_read' => $message->isRead,
            'synced_at' => now(),
        ]);

        $this->storeAttachments($account, $mailMessage, $message->attachments);

        return $mailMessage;
    }

    /**
     * Find a message already imported by UID or by RFC message id.
     */
    private function findExisting(MailAccount $account, InboundMessage $message): ?MailMessage
    {
        $byUid = MailMessage::query()
            ->where('mail_account_id', $account->id)
            ->where('folder', $message->folder)
            ->where('external_uid', $message->externalUid)
            ->first();

        if ($byUid !== null) {
            return $byUid;
        }

        if ($message->messageId === null) {
            return null;
        }

        return MailMessage::query()
            ->where('mail_account_id', $account->id)
            ->where('message_id', $message->messageId)
            ->first();
    }

    /**
     * Build a stable thread key from the message chain or the normalized subject.
     */
    private function threadKey(MailAccount $account, InboundMessage $message): string
    {
        $rootMessageId = $message->references[0] ?? $message->inReplyTo ?? $message->messageId;

        if ($rootMessageId !== null && $rootMessageId !== '') {
            return 'mid:'.sha1($rootMessageId);
        }

        $subject = $message->subject;

        do {
            $previousSubject = $subject;
            $subject = preg_replace('/^\s*(?:re|fwd)\s*:\s*/iu', '', $subject) ?? $subject;
        } while ($subject !== $previousSubject);

        $normalizedSubject = mb_strtolower(trim($subject));

        if ($normalizedSubject !== '') {
            return 'subject:'.sha1($normalizedSubject);
        }

        return 'uid:'.sha1($account->id.'|'.$message->folder.'|'.$message->externalUid);
    }

    /**
     * Link a reply to its imported parent when the parent is already known.
     */
    private function findParentId(MailAccount $account, InboundMessage $message): ?int
    {
        $candidate = $message->inReplyTo;

        if ($candidate === null && $message->references !== []) {
            $candidate = $message->references[count($message->references) - 1];
        }

        if ($candidate === null || $candidate === '') {
            return null;
        }

        $parentId = MailMessage::query()
            ->where('mail_account_id', $account->id)
            ->where('message_id', $candidate)
            ->value('id');

        return $parentId === null ? null : (int) $parentId;
    }

    /**
     * Store valid email attachments on the private disk.
     *
     * @param  list<InboundAttachment>  $attachments
     */
    private function storeAttachments(MailAccount $account, MailMessage $message, array $attachments): void
    {
        $disk = (string) config('mail.inbound.attachment_disk', 'local');
        $maxBytes = (int) config('mail.inbound.attachment_max_bytes', 26214400);

        foreach ($attachments as $attachment) {
            $size = $attachment->size();

            if ($size === 0 || $size > $maxBytes) {
                continue;
            }

            $originalName = $this->safeOriginalName($attachment->name);
            $mimeType = Str::limit($attachment->contentType, 150, '');
            $path = sprintf(
                'mail/%d/%d/%s.%s',
                $account->id,
                $message->id,
                Str::random(40),
                $this->safeExtension($originalName, $mimeType),
            );

            Storage::disk($disk)->put($path, $attachment->content);

            Attachment::create([
                'attachable_type' => MailMessage::class,
                'attachable_id' => $message->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size_bytes' => $size,
                'checksum' => hash('sha256', $attachment->content),
                'source' => 'email',
                'uploaded_by' => null,
            ]);
        }
    }

    /**
     * Keep only the file name and respect the database column length.
     */
    private function safeOriginalName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = Str::afterLast($name, '/');

        return mb_substr($name, 0, 255) ?: 'attachment.bin';
    }

    /**
     * Derive a harmless file extension from the name or MIME subtype.
     */
    private function safeExtension(string $name, string $mimeType): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (preg_match('/^[a-z0-9]{1,10}$/', $extension) === 1) {
            return $extension;
        }

        $subtype = strtolower(Str::after($mimeType, '/'));

        return preg_match('/^[a-z0-9.+-]{1,20}$/', $subtype) === 1 ? $subtype : 'bin';
    }
}
