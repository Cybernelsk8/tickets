<?php

use App\Actions\Reports\BuildDashboardReport;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Models\Area;
use App\Models\Task;
use App\Models\TaskStatusTransition;
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
        'title' => 'Entregable terminado del ticket',
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
        'title' => 'Entregable pendiente del ticket',
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
        ->and($report['recentWorkItems'])->toHaveCount(2)
        ->and($report['recentWorkItems']->pluck('code')->unique()->all())->toBe([$ticket->code])
        ->and($report['recentWorkItems']->pluck('subject')->all())->toContain('Entregable terminado del ticket');
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
        ->and($report['recentWorkItems']->pluck('ticketId')->all())->toBe([$visibleTicket->id])
        ->and($report['recentWorkItems']->pluck('ticketId')->all())->not->toContain($hiddenTicket->id);
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
        ->assertSee('Entregables recientes')
        ->assertSee('Listas para revisión')
        ->assertSee('Sin desglose')
        ->assertSee('Tendencia semanal de entregables creados')
        ->assertDontSee('Tareas recientes')
        ->assertDontSee('Tickets recientes')
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

    $workItem = $report['recentWorkItems']->first();

    expect($report['totalDeliverables'])->toBe(0)
        ->and($report['completedDeliverables'])->toBe(0)
        ->and($report['deliverableCompletionRate'])->toBe(0.0)
        ->and($report['totalSubtasks'])->toBe(1)
        ->and($report['completedSubtasks'])->toBe(1)
        ->and($report['subtaskProgressRate'])->toBe(100.0)
        ->and($report['readyForReview'])->toBe(0)
        ->and($report['recentWorkItems'])->toHaveCount(1)
        ->and($workItem->key)->toBe('task-'.$root->id)
        ->and($workItem->subject)->toBe('Entregable pendiente')
        ->and($workItem->progressLabel)->toBe('1/1 subtareas')
        ->and($workItem->progressPercent)->toBe(100.0)
        ->and($workItem->progressColor)->toBe('green')
        ->and($workItem->assigneeName)->toBe($manager->name)
        ->and($workItem->isReadyForReview)->toBeTrue()
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
        ->and($report['readyForReviewWorkItems']->pluck('key')->all())->toBe(['task-'.$ready->id])
        ->and($report['readyForReviewWorkItems']->first()->subtasksDone)->toBe(1)
        ->and($report['readyForReviewWorkItems']->first()->subtasksTotal)->toBe(1)
        ->and($report['readyForReviewWorkItems']->first()->isReadyForReview)->toBeTrue();
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
        ->assertSee('Entregable visible')
        ->assertDontSee('Subtarea del colaborador')
        ->call('toggleWorkItem', 'task-'.$root->id)
        ->assertSet('expandedWorkItemId', 'task-'.$root->id)
        ->assertSee('Subtarea del colaborador')
        ->assertSee('Subtarea de: Entregable visible')
        ->assertDontSee('Entregable terminado');
});

it('unifies tickets and root tasks into a single work item list', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);

    $ticket = Ticket::factory()->create([
        'code' => 'TKT-1001',
        'area_id' => $area->id,
        'category_id' => $category->id,
        'subject' => 'Asunto del correo',
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'created_by' => $manager->id,
        'title' => 'Entregable del ticket',
    ]);
    $standalone = Task::factory()->create([
        'ticket_id' => null,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'title' => 'Entregable creado por el jefe',
    ]);

    $report = (new BuildDashboardReport)->handle($admin);
    $ticketItem = $report['recentWorkItems']->firstWhere('key', 'task-'.$root->id);
    $standaloneItem = $report['recentWorkItems']->firstWhere('key', 'task-'.$standalone->id);

    expect($report['recentWorkItems'])->toHaveCount(2)
        ->and($ticketItem->displayTitle)->toBe('TKT-1001 — Entregable del ticket')
        ->and($ticketItem->sourceLabel)->toBe('Ticket')
        ->and($ticketItem->categoryName)->toBe($category->name)
        ->and($standaloneItem->displayTitle)->toBe('Entregable creado por el jefe')
        ->and($standaloneItem->sourceLabel)->toBe('Tarea')
        ->and($standaloneItem->code)->toBeNull();
});

it('includes tickets without a root task in the work item list', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $ticket = Ticket::factory()->assigned()->create([
        'area_id' => $area->id,
        'subject' => 'Ticket sin tarea',
    ]);

    $report = (new BuildDashboardReport)->handle($admin);
    $item = $report['recentWorkItems']->firstWhere('key', 'ticket-'.$ticket->id);

    expect($item)->not->toBeNull()
        ->and($item->displayTitle)->toBe($ticket->code.' — Ticket sin tarea')
        ->and($item->status)->toBe(TaskStatus::Assigned)
        ->and($item->progressLabel)->toBe('Sin desglose')
        ->and($item->showProgressBar)->toBeFalse();
});

it('uses progress colors based on finished subtasks', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);

    $makeRoot = function (string $title, int $doneCount) use ($area, $manager): Task {
        $root = Task::factory()->create([
            'area_id' => $area->id,
            'assignee_id' => $manager->id,
            'status' => TaskStatus::InProgress,
            'title' => $title,
        ]);

        for ($index = 0; $index < 4; $index++) {
            $factory = Task::factory()->childOf($root);
            $index < $doneCount ? $factory->done()->create(['area_id' => $area->id]) : $factory->create(['area_id' => $area->id]);
        }

        return $root;
    };

    $roots = [
        'Rojo' => $makeRoot('Rojo', 0),
        'Naranja' => $makeRoot('Naranja', 1),
        'Amarillo' => $makeRoot('Amarillo', 2),
        'Verde' => $makeRoot('Verde', 4),
    ];

    $standaloneDone = Task::factory()->done()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'title' => 'Terminado sin desglose',
    ]);
    $standaloneTodo = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'title' => 'Pendiente sin desglose',
    ]);

    $report = (new BuildDashboardReport)->handle($admin);
    $items = $report['recentWorkItems']->keyBy('subject');

    expect($items['Rojo']->progressColor)->toBe('red')
        ->and($items['Naranja']->progressColor)->toBe('orange')
        ->and($items['Amarillo']->progressColor)->toBe('yellow')
        ->and($items['Verde']->progressColor)->toBe('green')
        ->and($items['Terminado sin desglose']->progressColor)->toBe('green')
        ->and($items['Terminado sin desglose']->progressPercent)->toBe(100.0)
        ->and($items['Terminado sin desglose']->progressLabel)->toBe('Sin desglose')
        ->and($items['Pendiente sin desglose']->showProgressBar)->toBeFalse()
        ->and($items['Pendiente sin desglose']->progressColor)->toBe('zinc')
        ->and($items[$standaloneDone->title]->taskId)->toBe($standaloneDone->id)
        ->and($items[$standaloneTodo->title]->taskId)->toBe($standaloneTodo->id)
        ->and($roots['Verde']->status)->toBe(TaskStatus::InProgress);
});

it('shows the author and date of the latest status change in the work item', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);

    $task = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::InProgress,
        'title' => 'Entregable con historial',
    ]);
    $transition = TaskStatusTransition::factory()->create([
        'task_id' => $task->id,
        'from_status' => TaskStatus::Todo->value,
        'to_status' => TaskStatus::InProgress->value,
        'changed_by' => $manager->id,
        'occurred_at' => now()->subHours(2),
    ]);

    $report = (new BuildDashboardReport)->handle($admin);
    $item = $report['recentWorkItems']->firstWhere('key', 'task-'.$task->id);

    expect($item->statusChangedByName)->toBe($manager->name)
        ->and($item->statusChangedAt)->toBe($transition->occurred_at->format('d/m/Y H:i'));
});

it('expands a work item on demand and opens its details modal', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $category = TicketCategory::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);

    $ticket = Ticket::factory()->create([
        'area_id' => $area->id,
        'category_id' => $category->id,
        'subject' => '<script>alert(1)</script>',
        'requester_name' => 'Ana Solicitante',
        'requester_email' => 'ana@example.com',
        'assigned_at' => now()->subDay(),
    ]);
    $root = Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'title' => '<script>alert(1)</script>',
    ]);
    Task::factory()->childOf($root)->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'status' => TaskStatus::InProgress,
        'title' => 'Subtarea visible solo al expandir',
    ]);

    Livewire::actingAs($admin)->test(DashboardIndex::class)
        ->assertDontSee('Subtarea visible solo al expandir')
        ->call('toggleWorkItem', 'task-'.$root->id)
        ->assertSet('expandedWorkItemId', 'task-'.$root->id)
        ->assertSee('Subtarea visible solo al expandir')
        ->call('openDetails', 'task-'.$root->id)
        ->assertSet('showDetailsModal', true)
        ->assertSee('Solicitante')
        ->assertSee('ana@example.com')
        ->assertSee('Categoría')
        ->assertSeeHtml('&lt;script&gt;')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});

it('ignores manipulated work item keys', function () {
    $admin = User::factory()->superAdmin()->create();

    Livewire::actingAs($admin)->test(DashboardIndex::class)
        ->call('toggleWorkItem', 'task-999999')
        ->assertSet('expandedWorkItemId', null)
        ->call('openDetails', 'ticket-999999')
        ->assertSet('showDetailsModal', false);
});

it('filters the unified table to items ready for review', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $manager = User::factory()->jefeArea()->create(['area_id' => $area->id]);

    $ready = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Testing,
        'title' => 'Listo para aprobar',
    ]);
    Task::factory()->childOf($ready)->done()->create(['area_id' => $area->id]);

    Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $manager->id,
        'status' => TaskStatus::Todo,
        'title' => 'Trabajo en curso',
    ]);

    Livewire::actingAs($admin)->test(DashboardIndex::class)
        ->assertSee('Listo para aprobar')
        ->assertSee('Trabajo en curso')
        ->set('workItemFilter', 'review')
        ->assertSee('Listo para aprobar')
        ->assertDontSee('Trabajo en curso')
        ->call('setWorkItemFilter', 'all')
        ->assertSee('Trabajo en curso');
});
