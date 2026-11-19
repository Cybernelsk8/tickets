<?php

use App\Actions\Tickets\CreateTicketFromMessage;
use App\Models\MailMessage;
use App\Models\Ticket;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates a ticket from an inbound message', function () {
    $admin = User::factory()->superAdmin()->create();
    $message = MailMessage::factory()->create([
        'subject' => 'No puedo ingresar',
        'body_text' => 'Cuerpo del correo',
        'from_name' => 'Ana Pérez',
        'from_email' => 'ana@example.com',
    ]);

    $this->actingAs($admin);

    $ticket = app(CreateTicketFromMessage::class)->handle($message);

    expect($ticket->code)->toStartWith('TKT-')
        ->and($ticket->source)->toBe('email')
        ->and($ticket->mail_message_id)->toBe($message->id)
        ->and($ticket->subject)->toBe('No puedo ingresar')
        ->and($ticket->description)->toBe('Cuerpo del correo')
        ->and($ticket->requester_email)->toBe('ana@example.com')
        ->and($ticket->priority)->toBe(TicketPriority::Medium)
        ->and($ticket->status)->toBe(TaskStatus::Todo);

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'code' => $ticket->code]);
});

it('returns the existing ticket when the same message is converted twice', function () {
    $admin = User::factory()->superAdmin()->create();
    $message = MailMessage::factory()->create();

    $this->actingAs($admin);

    $action = app(CreateTicketFromMessage::class);
    $first = $action->handle($message);
    $second = $action->handle($message);

    expect($second->id)->toBe($first->id)
        ->and(Ticket::count())->toBe(1);
});

it('forbids users without ticket creation permission', function () {
    $user = User::factory()->create();
    $message = MailMessage::factory()->create();

    $this->actingAs($user);

    app(CreateTicketFromMessage::class)->handle($message);
})->throws(AuthorizationException::class);
