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
        ->and($report['totalDeliverables'])->toBe(2)
        ->and($report['completedDeliverables'])->toBe(1)
        ->and($report['deliverableCompletionRate'])->toBe(50.0)
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
        ->and($report['totalDeliverables'])->toBe(1)
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
        ->and($report['totalDeliverables'])->toBe(1)
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
        ->assertSee('Entregables por estado')
        ->assertSee('Tareas recientes')
        ->assertSee('Listas para revisión')
        ->assertSee('Tendencia semanal de entregables creados')
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

it('reports subtask progress without counting it as a finished deliverable', function () {
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);

    $root = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Testing,
        'title' => 'Entregable pendiente',
    ]);

    $subtask = Task::factory()->childOf($root)->done()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'title' => 'Subtarea terminada',
    ]);

    $report = (new BuildDashboardReport)->handle($collaborator);

    $workload = collect($report['workloadSeries'])->firstWhere('label', $collaborator->name);

    expect($report['totalDeliverables'])->toBe(0)
        ->and($report['completedDeliverables'])->toBe(0)
        ->and($report['deliverableCompletionRate'])->toBe(0.0)
        ->and($report['totalSubtasks'])->toBe(1)
        ->and($report['completedSubtasks'])->toBe(1)
        ->and($report['subtaskProgressRate'])->toBe(100.0)
        ->and($report['readyForReview'])->toBe(0)
        ->and($report['recentTasks']->pluck('id')->all())->toBe([$subtask->id])
        ->and($report['recentTasks']->first()->parent->title)->toBe('Entregable pendiente')
        ->and($workload['active'])->toBe(0)
        ->and($workload['completed'])->toBe(1);
});

it('only marks roots with all subtasks done as ready for review', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();

    $ready = Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Testing,
        'title' => 'Listo para revisión',
    ]);
    Task::factory()->childOf($ready)->done()->create(['area_id' => $area->id]);

    $pending = Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Testing,
        'title' => 'Aún con trabajo',
    ]);
    Task::factory()->childOf($pending)->done()->create(['area_id' => $area->id]);
    Task::factory()->childOf($pending)->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Todo,
    ]);

    $report = (new BuildDashboardReport)->handle($admin);

    expect($report['readyForReview'])->toBe(1)
        ->and($report['readyForReviewTasks']->pluck('id')->all())->toBe([$ready->id])
        ->and($report['readyForReviewTasks']->first()->subtasks_done_count)->toBe(1)
        ->and($report['readyForReviewTasks']->first()->subtasks_total_count)->toBe(1);
});

it('renders a collaborator subtask with its parent in the dashboard table', function () {
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);

    $root = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Testing,
        'title' => 'Entregable visible',
    ]);
    Task::factory()->childOf($root)->done()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'title' => 'Subtarea del colaborador',
    ]);

    Livewire::actingAs($collaborator)->test(DashboardIndex::class)
        ->assertSee('Subtarea del colaborador')
        ->assertSee('Subtarea de: Entregable visible')
        ->assertDontSee('Entregable terminado');
});
