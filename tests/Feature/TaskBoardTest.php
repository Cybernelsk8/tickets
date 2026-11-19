<?php

use App\Livewire\Boards\Index;
use App\Models\Area;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskStatusTransition;
use App\Models\Ticket;
use App\Models\User;
use App\TaskStatus;
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
        ->assertSee('+ Nueva');
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

it('hides task creation from collaborators', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create();
    $collaborator->update(['area_id' => $area->id]);

    Task::factory()->create(['area_id' => $area->id, 'title' => 'Tarea del colaborador']);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->assertSee('Tarea del colaborador')
        ->assertDontSee('+ Nueva')
        ->call('openCreate', 'todo')
        ->assertForbidden();
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
