<?php

use App\Livewire\Mail\Inbox;
use App\Models\Attachment;
use App\Models\MailAccount;
use App\Models\MailMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('shows a selected message and escapes its body', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $admin = User::factory()->superAdmin()->create();
    $account = MailAccount::factory()->create();
    $message = MailMessage::factory()->create([
        'mail_account_id' => $account->id,
        'subject' => 'No puedo ingresar al sistema',
        'body_text' => '<script>alert(1)</script> Texto seguro del correo',
    ]);
    $attachment = Attachment::factory()->create([
        'attachable_type' => MailMessage::class,
        'attachable_id' => $message->id,
        'original_name' => 'captura.png',
        'mime_type' => 'image/png',
    ]);
    Storage::disk('local')->put($attachment->path, 'contenido-de-imagen');

    Livewire::actingAs($admin)->test(Inbox::class)
        ->assertSee('No puedo ingresar al sistema')
        ->call('selectMessage', $message->id)
        ->assertSee('Texto seguro del correo')
        ->assertSee('captura.png')
        ->assertDontSee('<script>', false);

    expect($message->fresh()->is_read)->toBeTrue();
});

it('filters messages by search text', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $account = MailAccount::factory()->create();
    $matching = MailMessage::factory()->create([
        'mail_account_id' => $account->id,
        'subject' => 'Ticket urgente de soporte',
    ]);
    $other = MailMessage::factory()->create([
        'mail_account_id' => $account->id,
        'subject' => 'Factura mensual',
    ]);

    Livewire::actingAs($admin)->test(Inbox::class)
        ->set('search', 'urgente')
        ->assertSee($matching->subject)
        ->assertDontSee($other->subject);
});

it('filters messages to unread only', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $account = MailAccount::factory()->create();
    $read = MailMessage::factory()->create([
        'mail_account_id' => $account->id,
        'subject' => 'Correo leído',
        'is_read' => true,
    ]);
    $unread = MailMessage::factory()->create([
        'mail_account_id' => $account->id,
        'subject' => 'Correo sin leer',
        'is_read' => false,
    ]);

    Livewire::actingAs($admin)->test(Inbox::class)
        ->set('unreadOnly', true)
        ->assertSee($unread->subject)
        ->assertDontSee($read->subject);
});

it('downloads a private attachment for an authorized user', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    $admin = User::factory()->superAdmin()->create();
    $attachment = Attachment::factory()->create([
        'original_name' => 'captura.png',
        'mime_type' => 'image/png',
    ]);
    Storage::disk('local')->put($attachment->path, 'contenido-de-imagen');

    $this->actingAs($admin)
        ->get(route('attachments.download', $attachment))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('refuses attachment downloads to unprivileged users', function () {
    $attachment = Attachment::factory()->create();
    Storage::fake('local');
    Storage::disk('local')->put($attachment->path, 'contenido');

    $this->actingAs(User::factory()->create())
        ->get(route('attachments.download', $attachment))
        ->assertForbidden();
});

it('does not show the inbox to unprivileged users', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Inbox::class)
        ->assertForbidden();
});

it('creates a ticket from the selected message', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $account = MailAccount::factory()->create();
    $message = MailMessage::factory()->create([
        'mail_account_id' => $account->id,
        'subject' => 'Correo convertido en ticket',
        'from_email' => 'ana@example.com',
    ]);

    Livewire::actingAs($admin)
        ->test(Inbox::class)
        ->call('selectMessage', $message->id)
        ->assertSee('Crear ticket')
        ->call('createTicket')
        ->assertSee('Ticket TKT-');

    $this->assertDatabaseHas('tickets', [
        'mail_message_id' => $message->id,
        'subject' => 'Correo convertido en ticket',
        'requester_email' => 'ana@example.com',
    ]);
});
