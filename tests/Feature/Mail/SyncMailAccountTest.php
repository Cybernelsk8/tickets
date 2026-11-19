<?php

use App\Actions\Mail\SyncMailAccount;
use App\Models\Attachment;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\MailSyncRun;
use App\Support\Mail\InboundAttachment;
use App\Support\Mail\InboundMessage;
use App\Support\Mail\MailClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeMailClient;

/**
 * @param  array<string, mixed>  $overrides
 */
function inbound_test_message(string $externalUid, array $overrides = []): InboundMessage
{
    return new InboundMessage(
        folder: $overrides['folder'] ?? 'INBOX',
        externalUid: $externalUid,
        messageId: $overrides['messageId'] ?? "<{$externalUid}@example.com>",
        references: $overrides['references'] ?? [],
        inReplyTo: $overrides['inReplyTo'] ?? null,
        subject: $overrides['subject'] ?? 'Correo de prueba',
        fromName: $overrides['fromName'] ?? 'Ana Pérez',
        fromEmail: $overrides['fromEmail'] ?? 'ana@example.com',
        to: $overrides['to'] ?? ['soporte@example.com'],
        cc: $overrides['cc'] ?? [],
        bodyText: $overrides['bodyText'] ?? 'Hola, necesito ayuda.',
        bodyHtml: $overrides['bodyHtml'] ?? null,
        receivedAt: $overrides['receivedAt'] ?? CarbonImmutable::parse('2026-11-18 10:00:00'),
        isRead: $overrides['isRead'] ?? false,
        attachments: $overrides['attachments'] ?? [],
    );
}

it('imports messages and private attachments from the mail client', function () {
    Storage::fake('local');

    $account = MailAccount::factory()->create();
    $client = new FakeMailClient;
    $client->messagesByAccount[$account->id] = [inbound_test_message('101', [
        'subject' => 'No puedo ingresar',
        'attachments' => [new InboundAttachment('captura.png', 'image/png', 'binary-content')],
    ])];

    $this->app->instance(MailClient::class, $client);

    $run = $this->app->make(SyncMailAccount::class)->handle($account);
    $message = MailMessage::query()->firstOrFail();
    $attachment = $message->attachments->firstOrFail();

    expect($message->external_uid)->toBe('101')
        ->and($message->subject)->toBe('No puedo ingresar')
        ->and($message->from_email)->toBe('ana@example.com')
        ->and($message->is_read)->toBeFalse()
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->source)->toBe('email')
        ->and($attachment->checksum)->toBe(hash('sha256', 'binary-content'))
        ->and(Storage::disk('local')->get($attachment->path))->toBe('binary-content');

    expect($client->folders)->toBe([config('mail.inbound.folder')]);
    expect($run->processed)->toBe(1)
        ->and($run->failed)->toBe(0)
        ->and($run->finished_at)->not->toBeNull();
    expect($account->fresh()->last_synced_at)->not->toBeNull();
});

it('does not duplicate messages or attachments when synchronization runs twice', function () {
    Storage::fake('local');

    $account = MailAccount::factory()->create();
    $client = new FakeMailClient;
    $client->messagesByAccount[$account->id] = [inbound_test_message('202', [
        'attachments' => [new InboundAttachment('reporte.pdf', 'application/pdf', 'pdf-content')],
    ])];

    $this->app->instance(MailClient::class, $client);

    $sync = $this->app->make(SyncMailAccount::class);
    $sync->handle($account);
    $sync->handle($account);

    expect(MailMessage::query()->count())->toBe(1)
        ->and(Attachment::query()->count())->toBe(1);
});

it('links replies to their imported parent and keeps one thread key', function () {
    $account = MailAccount::factory()->create();
    $root = inbound_test_message('301', [
        'messageId' => '<root@example.com>',
        'subject' => 'Consulta sobre facturación',
    ]);
    $reply = inbound_test_message('302', [
        'messageId' => '<reply@example.com>',
        'inReplyTo' => '<root@example.com>',
        'references' => ['<root@example.com>'],
        'subject' => 'Re: Consulta sobre facturación',
        'receivedAt' => CarbonImmutable::parse('2026-11-18 10:05:00'),
    ]);

    $client = new FakeMailClient;
    $client->messagesByAccount[$account->id] = [$root, $reply];

    $this->app->instance(MailClient::class, $client);
    $this->app->make(SyncMailAccount::class)->handle($account);

    $importedRoot = MailMessage::query()->where('external_uid', '301')->firstOrFail();
    $importedReply = MailMessage::query()->where('external_uid', '302')->firstOrFail();

    expect($importedReply->thread_key)->toBe($importedRoot->thread_key)
        ->and($importedReply->parent_message_id)->toBe($importedRoot->id);
});

it('skips attachments larger than the configured limit', function () {
    Storage::fake('local');
    config()->set('mail.inbound.attachment_max_bytes', 4);

    $account = MailAccount::factory()->create();
    $client = new FakeMailClient;
    $client->messagesByAccount[$account->id] = [inbound_test_message('401', [
        'attachments' => [new InboundAttachment('grande.zip', 'application/zip', '12345')],
    ])];

    $this->app->instance(MailClient::class, $client);
    $this->app->make(SyncMailAccount::class)->handle($account);

    expect(MailMessage::query()->count())->toBe(1)
        ->and(Attachment::query()->count())->toBe(0);
});

it('records a failed synchronization run when the mail client throws', function () {
    $account = MailAccount::factory()->create();
    $client = new FakeMailClient;
    $client->exception = new RuntimeException('IMAP caído');

    $this->app->instance(MailClient::class, $client);

    try {
        $this->app->make(SyncMailAccount::class)->handle($account);
        $this->fail('La sincronización debía propagar el error.');
    } catch (RuntimeException) {
        // Expected: the caller decides how to report the failed job.
    }

    $run = MailSyncRun::query()->firstOrFail();

    expect($run->failed)->toBe(1)
        ->and($run->error)->toContain('IMAP caído');
});
