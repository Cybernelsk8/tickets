<?php

use App\Actions\Tasks\CreateTask;
use App\Models\Area;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('creates a standalone task with a dependency and an initial transition', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $dependency = Task::factory()->done()->create(['area_id' => $area->id]);

    $this->actingAs($manager);

    $task = app(CreateTask::class)->handle([
        'title' => 'Instalar luminarias',
        'description' => 'Revisar el inventario antes de instalar.',
        'area_id' => $area->id,
        'parent_id' => null,
        'assignee_id' => null,
        'status' => TaskStatus::Todo,
        'priority' => TicketPriority::High,
        'estimated_minutes' => 120,
        'actual_minutes' => null,
        'due_at' => Carbon::parse('2026-12-20'),
        'depends_on_task_id' => $dependency->id,
    ]);

    expect($task->ticket_id)->toBeNull()
        ->and($task->parent_id)->toBeNull()
        ->and($task->area_id)->toBe($area->id)
        ->and($task->priority)->toBe(TicketPriority::High)
        ->and($task->due_at->toDateString())->toBe('2026-12-20')
        ->and($task->dependencies()->first()->depends_on_task_id)->toBe($dependency->id)
        ->and($task->statusTransitions()->first()->to_status)->toBe(TaskStatus::Todo->value);
});

it('rejects creating a task in an area the manager does not manage', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    Area::factory()->managedBy($manager)->create();
    $otherArea = Area::factory()->create();

    $this->actingAs($manager);

    expect(fn () => app(CreateTask::class)->handle([
        'title' => 'Tarea en área ajena',
        'description' => null,
        'area_id' => $otherArea->id,
        'parent_id' => null,
        'assignee_id' => null,
        'status' => TaskStatus::Todo,
        'priority' => TicketPriority::Medium,
        'estimated_minutes' => null,
        'actual_minutes' => null,
        'due_at' => null,
        'depends_on_task_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('lets a collaborator create a task assigned to themselves in their own area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);
    $otherCollaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);

    $this->actingAs($collaborator);

    $task = app(CreateTask::class)->handle([
        'title' => 'Tarea del colaborador',
        'description' => null,
        'area_id' => $area->id,
        'parent_id' => null,
        'assignee_id' => $otherCollaborator->id,
        'status' => TaskStatus::Done,
        'priority' => TicketPriority::Medium,
        'estimated_minutes' => null,
        'actual_minutes' => null,
        'due_at' => null,
        'depends_on_task_id' => null,
    ]);

    expect($task->assignee_id)->toBe($collaborator->id)
        ->and($task->area_id)->toBe($area->id)
        ->and($task->status)->toBe(TaskStatus::Todo)
        ->and($task->created_by)->toBe($collaborator->id);
});

it('does not let a collaborator create a task in another area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $ownArea = Area::factory()->create(['manager_id' => null]);
    $otherArea = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $ownArea->id]);

    $this->actingAs($collaborator);

    expect(fn () => app(CreateTask::class)->handle([
        'title' => 'Tarea en área ajena',
        'description' => null,
        'area_id' => $otherArea->id,
        'parent_id' => null,
        'assignee_id' => $collaborator->id,
        'status' => TaskStatus::Todo,
        'priority' => TicketPriority::Medium,
        'estimated_minutes' => null,
        'actual_minutes' => null,
        'due_at' => null,
        'depends_on_task_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('lets a collaborator create a subtask assigned to themselves', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);
    $otherCollaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $parent = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $collaborator->id]);

    $this->actingAs($collaborator);

    $subtask = app(CreateTask::class)->handle([
        'title' => 'Desglose del colaborador',
        'description' => null,
        'area_id' => $area->id,
        'parent_id' => $parent->id,
        'assignee_id' => $otherCollaborator->id,
        'status' => TaskStatus::Done,
        'priority' => TicketPriority::Medium,
        'estimated_minutes' => null,
        'actual_minutes' => null,
        'due_at' => null,
        'depends_on_task_id' => null,
    ]);

    expect($subtask->parent_id)->toBe($parent->id)
        ->and($subtask->area_id)->toBe($area->id)
        ->and($subtask->assignee_id)->toBe($collaborator->id)
        ->and($subtask->status)->toBe(TaskStatus::Todo)
        ->and($subtask->created_by)->toBe($collaborator->id)
        ->and($subtask->statusTransitions()->first()->to_status)->toBe(TaskStatus::Todo->value);
});

it('does not let a collaborator create a subtask in another area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $collaborator = User::factory()->colaborador()->create();
    $ownArea = Area::factory()->create(['manager_id' => null]);
    $otherArea = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $ownArea->id]);
    $parent = Task::factory()->create(['area_id' => $otherArea->id, 'assignee_id' => $collaborator->id]);

    $this->actingAs($collaborator);

    expect(fn () => app(CreateTask::class)->handle([
        'title' => 'Subtarea en área ajena',
        'description' => null,
        'area_id' => $otherArea->id,
        'parent_id' => $parent->id,
        'assignee_id' => $collaborator->id,
        'status' => TaskStatus::Todo,
        'priority' => TicketPriority::Medium,
        'estimated_minutes' => null,
        'actual_minutes' => null,
        'due_at' => null,
        'depends_on_task_id' => null,
    ]))->toThrow(ValidationException::class);
});

it('requires the dependency to be done before creating a task already in progress', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $dependency = Task::factory()->create(['area_id' => $area->id]);

    $this->actingAs($manager);

    expect(fn () => app(CreateTask::class)->handle([
        'title' => 'Tarea bloqueada por dependencia',
        'description' => null,
        'area_id' => $area->id,
        'parent_id' => null,
        'assignee_id' => null,
        'status' => TaskStatus::InProgress,
        'priority' => TicketPriority::Medium,
        'estimated_minutes' => null,
        'actual_minutes' => null,
        'due_at' => null,
        'depends_on_task_id' => $dependency->id,
    ]))->toThrow(ValidationException::class);

    expect(TaskDependency::count())->toBe(0);
});
