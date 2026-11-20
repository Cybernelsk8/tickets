<?php

use App\Livewire\Boards\Index;
use App\Models\Area;
use App\Models\Attachment;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskStatusTransition;
use App\Models\Ticket;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

it('shows ticket tasks and tasks created directly on the board', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $ticket = Ticket::factory()->create(['area_id' => $area->id]);

    Task::factory()->create([
        'ticket_id' => $ticket->id,
        'area_id' => $area->id,
        'title' => 'Tarea desde correo',
    ]);
    Task::factory()->create([
        'ticket_id' => null,
        'area_id' => $area->id,
        'title' => 'Tarea creada en el tablero',
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->assertSee('tareas de tickets y tareas creadas en el tablero.')
        ->assertSee('Tarea desde correo')
        ->assertSee('Tarea creada en el tablero')
        ->assertSee('Nueva tarea');
});

it('lets an area manager create a subtask with a responsible and times from the board', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $rootTask = Task::factory()->create(['area_id' => $area->id, 'title' => 'Proyecto base']);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('openCreate', 'todo')
        ->set('title', 'Revisar permisos')
        ->set('description', 'Validar los accesos del área.')
        ->set('parentTaskId', $rootTask->id)
        ->set('assigneeId', $collaborator->id)
        ->set('estimatedMinutes', 90)
        ->set('dueAt', '2026-12-31')
        ->call('createTask')
        ->assertHasNoErrors()
        ->assertSee('Revisar permisos');

    $task = Task::where('title', 'Revisar permisos')->firstOrFail();

    expect($task->ticket_id)->toBeNull()
        ->and($task->parent_id)->toBe($rootTask->id)
        ->and($task->assignee_id)->toBe($collaborator->id)
        ->and($task->created_by)->toBe($manager->id)
        ->and($task->estimated_minutes)->toBe(90)
        ->and($task->status)->toBe(TaskStatus::Todo)
        ->and($task->statusTransitions()->count())->toBe(1);
});

it('lets a collaborator open the create modal with themselves as responsible', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);

    Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'title' => 'Tarea del colaborador',
    ]);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->assertSee('Tarea del colaborador')
        ->assertSee('Nueva tarea')
        ->call('openCreate', 'done')
        ->assertSet('assigneeId', $collaborator->id)
        ->assertSet('targetStatus', 'todo')
        ->assertSee($collaborator->name);
});

it('lets a collaborator create a task from the board assigned to themselves', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->call('openCreate', 'done')
        ->set('title', 'Tarea creada por el colaborador')
        ->call('createTask')
        ->assertHasNoErrors()
        ->assertSee('Tarea creada por el colaborador');

    $task = Task::where('title', 'Tarea creada por el colaborador')->firstOrFail();

    expect($task->assignee_id)->toBe($collaborator->id)
        ->and($task->status)->toBe(TaskStatus::Todo)
        ->and($task->area_id)->toBe($area->id)
        ->and($task->created_by)->toBe($collaborator->id);
});

it('does not let a collaborator choose another responsible or initial column', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);
    $otherCollaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->call('openCreate', 'todo')
        ->set('title', 'Tarea con responsable ajeno')
        ->set('assigneeId', $otherCollaborator->id)
        ->set('targetStatus', 'done')
        ->call('createTask')
        ->assertHasErrors(['assigneeId', 'targetStatus']);

    expect(Task::where('title', 'Tarea con responsable ajeno')->count())->toBe(0);
});

it('lets a collaborator create a subtask from the board assigned to themselves', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);
    $parent = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'title' => 'Tarea del colaborador',
    ]);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->assertSee('Tarea del colaborador')
        ->assertSee('Nueva tarea')
        ->call('openSubtask', $parent->id)
        ->assertSet('parentTaskId', $parent->id)
        ->set('title', 'Desglose del colaborador')
        ->call('createTask')
        ->assertHasNoErrors()
        ->assertSee('Desglose del colaborador');

    $subtask = Task::where('title', 'Desglose del colaborador')->firstOrFail();

    expect($subtask->parent_id)->toBe($parent->id)
        ->and($subtask->assignee_id)->toBe($collaborator->id)
        ->and($subtask->status)->toBe(TaskStatus::Todo)
        ->and($subtask->created_by)->toBe($collaborator->id);
});

it('uses three Kanban columns and groups assigned tasks under Por hacer', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Assigned,
        'title' => 'Tarea ya asignada',
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->assertSee('Por hacer')
        ->assertSee('En proceso')
        ->assertSee('Terminado')
        ->assertSee('Tarea ya asignada')
        ->assertDontSee('Asignado');
});

it('reorders tasks inside the same column without changing their status', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create(['area_id' => $area->id, 'status' => TaskStatus::Todo]);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('handleSort', $task->id, 0, 'todo')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->fresh()->statusTransitions()->count())->toBe(0);
});

it('moves a task between board columns through drag and drop', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create(['area_id' => $area->id, 'status' => TaskStatus::Todo]);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('handleSort', $task->id, 0, 'in_progress')
        ->assertHasNoErrors();

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and($task->fresh()->started_at)->not->toBeNull();
});

it('lets an area manager correct a subtask responsible from the edit modal', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $wrongAssignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $rightAssignee = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $rootTask = Task::factory()->create(['area_id' => $area->id, 'title' => 'Proyecto correctivo']);
    $subtask = Task::factory()->childOf($rootTask)->create([
        'title' => 'Subtarea mal asignada',
        'assignee_id' => $wrongAssignee->id,
        'created_by' => $manager->id,
    ]);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('openEdit', $subtask->id)
        ->assertSet('editTitle', 'Subtarea mal asignada')
        ->set('editTitle', 'Subtarea corregida')
        ->set('editAssigneeId', $rightAssignee->id)
        ->call('updateTask')
        ->assertHasNoErrors();

    expect($subtask->fresh()->title)->toBe('Subtarea corregida')
        ->and($subtask->fresh()->assignee_id)->toBe($rightAssignee->id);
});

it('does not let a collaborator change the responsible when editing their own subtask', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);
    $otherCollaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $rootTask = Task::factory()->create(['area_id' => $area->id]);
    $subtask = Task::factory()->childOf($rootTask)->create([
        'title' => 'Subtarea del colaborador',
        'assignee_id' => $collaborator->id,
        'created_by' => $collaborator->id,
    ]);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->call('openEdit', $subtask->id)
        ->set('editTitle', 'Subtarea editada por el colaborador')
        ->set('editAssigneeId', $otherCollaborator->id)
        ->call('updateTask')
        ->assertHasErrors('board');

    expect($subtask->fresh()->title)->toBe('Subtarea del colaborador')
        ->and($subtask->fresh()->assignee_id)->toBe($collaborator->id);
});

it('shows column movements on cards and in the task detail', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::InProgress,
        'title' => 'Tarea con movimientos',
    ]);

    TaskStatusTransition::factory()->create([
        'task_id' => $task->id,
        'from_status' => null,
        'to_status' => TaskStatus::Todo->value,
        'occurred_at' => now()->subDays(2),
    ]);
    TaskStatusTransition::factory()->create([
        'task_id' => $task->id,
        'from_status' => TaskStatus::Todo->value,
        'to_status' => TaskStatus::InProgress->value,
        'changed_by' => $manager->id,
        'occurred_at' => now()->subDay(),
    ]);

    Livewire::actingAs($manager)->test(Index::class)
        ->assertDontSee('Movida')
        ->call('toggleCard', $task->id)
        ->assertSee('Movida')
        ->assertSee('→ En proceso')
        ->call('openDetails', $task->id)
        ->assertSee('Historial de estados y columnas')
        ->assertSee('→ En proceso');
});

it('labels the pending status as Por hacer', function () {
    expect(TaskStatus::Todo->label())->toBe('Por hacer');
});

it('shows the parent task on expanded subtasks without the board origin badge', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $ticket = Ticket::factory()->create(['area_id' => $area->id]);
    $ticketTask = Task::factory()->create([
        'area_id' => $area->id,
        'ticket_id' => $ticket->id,
        'title' => 'Tarea desde ticket',
    ]);
    $parent = Task::factory()->create(['area_id' => $area->id, 'title' => 'Tarea principal visible']);
    $subtask = Task::factory()->childOf($parent)->create([
        'area_id' => $area->id,
        'title' => 'Subtarea sin ticket',
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->call('toggleCard', $ticketTask->id)
        ->assertSee($ticket->code)
        ->call('toggleCard', $subtask->id)
        ->assertSee('Subtarea de')
        ->assertSee('Tarea principal visible')
        ->assertDontSee('Tablero');
});

it('expands a task card in place with its current status and badges', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::InProgress,
        'priority' => TicketPriority::High,
        'title' => 'Tarjeta expandible',
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->assertSee('Tarjeta expandible')
        ->assertDontSee('En proceso → Pruebas')
        ->call('toggleCard', $task->id)
        ->assertSet('expandedTaskId', $task->id)
        ->assertSee('Pasar a Pruebas')
        ->assertSeeHtml('data-flux-badge')
        ->call('toggleCard', $task->id)
        ->assertSet('expandedTaskId', null)
        ->assertDontSee('Pasar a Pruebas');
});

it('exposes card actions through a dropdown with only the vertical dots icon', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create(['area_id' => $area->id, 'title' => 'Acciones de tarjeta']);

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSeeHtml('data-flux-dropdown')
        ->assertSeeHtml('wire:sort:ignore')
        ->assertSee('Crear subtarea')
        ->assertSee('Editar tarea')
        ->assertDontSeeHtml('wire:click="moveTask('.$task->id.', \'done\')"');
});

it('shows the assigned collaborator avatar on collapsed cards', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create([
        'area_id' => $area->id,
        'name' => 'Ana Colaboradora',
    ]);
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'due_at' => now()->addDay(),
        'title' => 'Tarea con avatar',
    ]);

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSee('Tarea con avatar')
        ->assertSeeHtml('data-flux-avatar')
        ->assertSee('Ana Colaboradora');
});

it('does not render direct state buttons except the in progress to testing transition', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Todo,
        'title' => 'Sin botones de estado',
    ]);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('toggleCard', $task->id)
        ->assertDontSeeHtml('wire:click="moveTask('.$task->id.', \'in_progress\')"')
        ->assertDontSeeHtml('wire:click="moveTask('.$task->id.', \'done\')"');
});

it('groups detail attachments by type with file names and a count badge', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $task = Task::factory()->create(['area_id' => $area->id, 'title' => 'Tarea con adjuntos']);

    Attachment::factory()->create([
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'original_name' => 'foto-uno.png',
        'mime_type' => 'image/png',
    ]);
    Attachment::factory()->create([
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'original_name' => 'foto-dos.jpg',
        'mime_type' => 'image/jpeg',
    ]);
    Attachment::factory()->create([
        'attachable_type' => Task::class,
        'attachable_id' => $task->id,
        'original_name' => 'informe.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->call('openDetails', $task->id)
        ->assertSeeHtml('data-flux-tooltip')
        ->assertSeeHtml('data-flux-dropdown')
        ->assertSee('foto-uno.png, foto-dos.jpg')
        ->assertSee('informe.pdf')
        ->assertSeeHtml('absolute -top-1 -end-1 flex size-4')
        ->assertSeeHtml('size-10')
        ->assertSeeHtml('target="_blank"');
});

it('shows the task detail with subtasks, dependencies and status history', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'title' => 'Proyecto con detalle',
        'description' => 'Descripción detallada del proyecto.',
    ]);
    $child = Task::factory()->childOf($task)->create(['title' => 'Subtarea visible']);
    $dependency = Task::factory()->done()->create([
        'area_id' => $area->id,
        'title' => 'Tarea dependiente',
    ]);

    TaskDependency::create([
        'task_id' => $task->id,
        'depends_on_task_id' => $dependency->id,
        'type' => 'finish_to_start',
    ]);
    TaskStatusTransition::factory()->create([
        'task_id' => $task->id,
        'changed_by' => $admin->id,
        'to_status' => TaskStatus::Todo->value,
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->call('openDetails', $task->id)
        ->assertSee('Proyecto con detalle')
        ->assertSee('Descripción detallada del proyecto.')
        ->assertSee($child->title)
        ->assertSee($dependency->title)
        ->assertSee('Historial de estados');
});
