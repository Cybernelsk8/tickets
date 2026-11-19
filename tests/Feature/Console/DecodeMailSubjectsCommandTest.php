<?php

use App\Models\MailMessage;
use App\Models\Task;
use App\Models\Ticket;

it('decodes stored subjects, sender names and task titles', function () {
    $encoded = '=?utf-8?Q?Cierre_de_Recepci=C3=B3n_de_bienes_muebles_inservibles?=';
    $decoded = 'Cierre de Recepción de bienes muebles inservibles';

    $message = MailMessage::factory()->create(['subject' => $encoded, 'from_name' => $encoded]);
    $ticket = Ticket::factory()->create(['subject' => $encoded]);
    $task = Task::factory()->create(['title' => "TKT-0001 — {$encoded}"]);

    $this->artisan('tickets:decode-mail-subjects')->assertSuccessful();

    expect($message->fresh()->subject)->toBe($decoded)
        ->and($message->fresh()->from_name)->toBe($decoded)
        ->and($ticket->fresh()->subject)->toBe($decoded)
        ->and($task->fresh()->title)->toBe("TKT-0001 — {$decoded}");
});

it('does not rewrite records when there is nothing left to decode', function () {
    $message = MailMessage::factory()->create([
        'subject' => '=?utf-8?Q?Cierre_de_Recepci=C3=B3n?=',
    ]);

    $this->artisan('tickets:decode-mail-subjects')->assertSuccessful();

    $message->update(['updated_at' => now()->subDay()]);
    $previousUpdate = $message->fresh()->updated_at;

    $this->artisan('tickets:decode-mail-subjects')->assertSuccessful();

    expect($message->fresh()->updated_at->equalTo($previousUpdate))->toBeTrue();
});
