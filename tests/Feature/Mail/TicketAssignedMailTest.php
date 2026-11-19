<?php

use App\Mail\TicketAssignedMail;
use App\Models\Ticket;

it('renders the assignment details and escapes the subject', function () {
    $ticket = Ticket::factory()->make([
        'code' => 'TKT-0042',
        'subject' => '<script>alert(1)</script> Acceso al sistema',
        'requester_name' => 'Ana Pérez',
        'requester_email' => 'ana@example.com',
    ]);

    $mail = new TicketAssignedMail($ticket);

    $mail->assertHasSubject('Tu ticket TKT-0042 fue asignado');
    $mail->assertSeeInHtml('TKT-0042')
        ->assertSeeInHtml('Acceso al sistema');
    $mail->assertSeeInText('TKT-0042')
        ->assertSeeInText('Acceso al sistema');

    $html = $mail->render();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});
