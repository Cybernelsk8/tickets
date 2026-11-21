<?php

use App\Actions\Reports\BuildDashboardReport;
use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('closes stale tickets whose root is done and lowers the open ticket KPI', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $completedAt = now()->subDays(2)->startOfSecond();

    $staleTicket = Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Assigned,
        'subject' => 'Ticket desincronizado',
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $staleTicket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::InProgress,
    ]);
    $root->updateQuietly([
        'status' => TaskStatus::Done,
        'completed_at' => $completedAt,
    ]);

    $openTicket = Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Assigned,
        'subject' => 'Ticket realmente abierto',
    ]);
    Task::factory()->create([
        'ticket_id' => $openTicket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::InProgress,
    ]);

    expect((new BuildDashboardReport)->handle($admin)['openTickets'])->toBe(2);

    $this->artisan('tickets:close-completed-roots')
        ->expectsOutputToContain('Tickets cerrados: 1')
        ->assertExitCode(0);

    $repaired = $staleTicket->fresh();

    expect($repaired->status)->toBe(TaskStatus::Done)
        ->and($repaired->closed_at?->toDateTimeString())->toBe($completedAt->toDateTimeString())
        ->and($openTicket->fresh()->status)->toBe(TaskStatus::Assigned)
        ->and((new BuildDashboardReport)->handle($admin)['openTickets'])->toBe(1);
});

it('previews stale tickets without changing them on a dry run', function () {
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Assigned,
        'subject' => 'Ticket pendiente de cierre',
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::InProgress,
    ]);
    $root->updateQuietly(['status' => TaskStatus::Done, 'completed_at' => now()]);

    $this->artisan('tickets:close-completed-roots --dry-run')
        ->expectsOutputToContain('Tickets que se cerrarían: 1')
        ->assertExitCode(0);

    expect($ticket->fresh()->status)->toBe(TaskStatus::Assigned)
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

it('closes the ticket when a root task becomes done outside the board action', function () {
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Assigned,
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Testing,
    ]);

    $root->update(['status' => TaskStatus::Done, 'completed_at' => now()]);

    expect($ticket->fresh()->status)->toBe(TaskStatus::Done)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('closes the ticket when a root task is created already done', function () {
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Assigned,
    ]);

    Task::factory()->done()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
    ]);

    expect($ticket->fresh()->status)->toBe(TaskStatus::Done)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});
