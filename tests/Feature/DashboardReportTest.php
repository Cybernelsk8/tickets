<?php

use App\Actions\Reports\BuildDashboardReport;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('builds global aggregates for a super admin within the selected period', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $assignee = User::factory()->colaborador()->create(['area_id' => $area->id]);

    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
        'subject' => 'Ticket del periodo',
    ]);
    $ticket->forceFill(['created_at' => now()->subDays(5)])->save();

    $doneTask = Task::factory()->done()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $assignee->id,
        'started_at' => now()->subDays(5),
        'testing_at' => now()->subDays(4),
        'completed_at' => now()->subDays(3),
    ]);
    $doneTask->forceFill(['created_at' => now()->subDays(5)])->save();

    Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $assignee->id,
        'status' => TaskStatus::Todo,
    ]);

    $oldTicket = Ticket::factory()->create(['area_id' => $area->id]);
    $oldTicket->forceFill(['created_at' => now()->subDays(60)])->save();

    $report = (new BuildDashboardReport)->handle($admin, [
        'from' => now()->subDays(30)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $statusValues = collect($report['statusSeries'])->pluck('value', 'label')->all();

    expect($report['scopeLabel'])->toBe('Todas las áreas')
        ->and($report['totalTickets'])->toBe(1)
        ->and($report['openTickets'])->toBe(1)
        ->and($report['totalTasks'])->toBe(2)
        ->and($report['completedTasks'])->toBe(1)
        ->and($report['completionRate'])->toBe(50.0)
        ->and($statusValues['Terminado'])->toBe(1)
        ->and($statusValues['Por hacer'])->toBe(1)
        ->and($report['recentTickets'])->toHaveCount(1)
        ->and($report['recentTickets']->first()->subject)->toBe('Ticket del periodo');
});

it('limits an area manager to the areas they manage', function () {
    $manager = User::factory()->jefeArea()->create();
    $managedArea = Area::factory()->managedBy($manager)->create();
    $otherArea = Area::factory()->create();

    $visibleTicket = Ticket::factory()->create(['area_id' => $managedArea->id, 'subject' => 'Ticket propio']);
    $hiddenTicket = Ticket::factory()->create(['area_id' => $otherArea->id, 'subject' => 'Ticket ajeno']);

    $report = (new BuildDashboardReport)->handle($manager);

    expect($report['scopeLabel'])->toBe('Áreas gestionadas')
        ->and($report['totalTickets'])->toBe(1)
        ->and($report['recentTickets']->pluck('id')->all())->toBe([$visibleTicket->id])
        ->and($report['recentTickets']->pluck('id')->all())->not->toContain($hiddenTicket->id);
});

it('limits collaborators without report permissions to their own data', function () {
    $area = Area::factory()->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $otherUser = User::factory()->colaborador()->create(['area_id' => $area->id]);

    Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
    ]);
    Ticket::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $otherUser->id,
    ]);
    Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
    ]);
    Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $otherUser->id,
    ]);

    $report = (new BuildDashboardReport)->handle($collaborator);

    expect($report['scopeLabel'])->toBe('Mis datos')
        ->and($report['totalTickets'])->toBe(1)
        ->and($report['totalTasks'])->toBe(1)
        ->and($report['filters']['assignee_id'])->toBe($collaborator->id);
});

it('applies date, area, category and assignee filters together', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $otherArea = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $otherCategory = TicketCategory::factory()->create();
    $assignee = User::factory()->colaborador()->create(['area_id' => $area->id]);

    $matchingTicket = Ticket::factory()->create([
        'area_id' => $area->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
    ]);
    $matchingTicket->forceFill(['created_at' => now()->subDays(10)])->save();

    $otherDateTicket = Ticket::factory()->create([
        'area_id' => $area->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
    ]);
    $otherDateTicket->forceFill(['created_at' => now()->subDays(90)])->save();

    Ticket::factory()->create([
        'area_id' => $otherArea->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
    ]);
    Ticket::factory()->create([
        'area_id' => $area->id,
        'category_id' => $otherCategory->id,
        'assignee_id' => $assignee->id,
    ]);

    Task::factory()->create([
        'ticket_id' => $matchingTicket->id,
        'area_id' => $area->id,
        'assignee_id' => $assignee->id,
    ]);
    $oldTask = Task::factory()->create([
        'ticket_id' => $otherDateTicket->id,
        'area_id' => $area->id,
        'assignee_id' => $assignee->id,
    ]);
    $oldTask->forceFill(['created_at' => now()->subDays(90)])->save();

    $report = (new BuildDashboardReport)->handle($admin, [
        'from' => now()->subDays(30)->toDateString(),
        'to' => now()->toDateString(),
        'area_id' => $area->id,
        'category_id' => $category->id,
        'assignee_id' => $assignee->id,
    ]);

    expect($report['totalTickets'])->toBe(1)
        ->and($report['totalTasks'])->toBe(1)
        ->and($report['filters']['area_id'])->toBe($area->id)
        ->and($report['filters']['category_id'])->toBe($category->id)
        ->and($report['filters']['assignee_id'])->toBe($assignee->id);
});

it('renders dashboard KPIs, charts and recent tickets in Livewire', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'subject' => '<script>alert(1)</script>',
    ]);

    Livewire::actingAs($admin)->test(DashboardIndex::class)
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Todas las áreas')
        ->assertSee('Tareas por estado')
        ->assertSee('Tendencia semanal de tareas creadas')
        ->assertSeeHtml('apex-chart-status-')
        ->assertSeeHtml('data-flux-table')
        ->assertSeeHtml('&lt;script&gt;')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});

it('does not render data outside the manager area and clears dashboard filters', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $otherArea = Area::factory()->create();

    Ticket::factory()->create(['area_id' => $area->id, 'subject' => 'Ticket visible']);
    Ticket::factory()->create(['area_id' => $otherArea->id, 'subject' => 'Ticket oculto']);

    Livewire::actingAs($manager)->test(DashboardIndex::class)
        ->assertSee('Ticket visible')
        ->assertDontSee('Ticket oculto')
        ->set('areaId', $otherArea->id)
        ->assertSet('areaId', $otherArea->id)
        ->call('clearFilters')
        ->assertSet('areaId', null)
        ->assertSet('from', now()->subDays(30)->toDateString())
        ->assertSet('to', now()->toDateString());
});
