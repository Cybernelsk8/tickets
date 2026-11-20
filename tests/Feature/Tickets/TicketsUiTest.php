<?php

use App\Livewire\Tickets\Index;
use App\Mail\TicketAssignedMail;
use App\Models\Area;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lists tickets and assigns one from the interface', function () {
    $manager = User::factory()->mailManager()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $assignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'subject' => 'Ticket de prueba',
        'requester_email' => 'ana@example.com',
        'area_id' => null,
        'category_id' => null,
    ]);

    Mail::fake();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('Ticket de prueba')
        ->call('openAssign', $ticket->id)
        ->set('areaId', $area->id)
        ->set('categoryId', $category->id)
        ->set('priority', 'high')
        ->set('assigneeId', $assignee->id)
        ->call('assign')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'area_id' => $area->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
        'priority' => 'high',
        'status' => 'assigned',
    ]);
    Mail::assertQueued(TicketAssignedMail::class);
});

it('rejects an assignee from another area in the interface', function () {
    $manager = User::factory()->mailManager()->create();
    $area = Area::factory()->create();
    $otherArea = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $outsideAssignee = User::factory()->colaborador()->create(['area_id' => $otherArea->id]);
    $ticket = Ticket::factory()->create(['area_id' => null, 'category_id' => null]);

    Mail::fake();

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->call('openAssign', $ticket->id)
        ->set('areaId', $area->id)
        ->set('categoryId', $category->id)
        ->set('assigneeId', $outsideAssignee->id)
        ->call('assign')
        ->assertHasErrors('assigneeId');

    $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'todo']);
    Mail::assertNothingQueued();
});

it('hides assignment controls for finished tickets', function () {
    $manager = User::factory()->mailManager()->create();
    $ticket = Ticket::factory()->create([
        'subject' => 'Ticket terminado',
        'status' => TaskStatus::Done,
    ]);

    Livewire::actingAs($manager)
        ->test(Index::class)
        ->assertSee('Ticket terminado')
        ->assertDontSee('Asignar')
        ->call('openAssign', $ticket->id)
        ->assertForbidden();
});

it('still lets a super administrator open assignment for a finished ticket', function () {
    $admin = User::factory()->superAdmin()->create();
    $ticket = Ticket::factory()->create(['status' => TaskStatus::Done]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Asignar')
        ->call('openAssign', $ticket->id)
        ->assertSet('assigningTicketId', $ticket->id);
});

it('does not show the ticket list to unprivileged users', function () {
    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertForbidden();
});
