<?php

use App\Actions\Tasks\MoveTask;
use App\Models\Area;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

it('moves a task to a valid status, records the transition and sets stage timestamps', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create([
        'area_id' => $area->id,
        'status' => TaskStatus::Assigned,
        'position' => 0,
    ]);

    $this->actingAs($manager);

    $moved = app(MoveTask::class)->handle($task, TaskStatus::InProgress, 0);

    expect($moved->status)->toBe(TaskStatus::InProgress)
        ->and($moved->started_at)->not->toBeNull()
        ->and($moved->testing_at)->toBeNull()
        ->and($moved->position)->toBe(0)
        ->and($moved->statusTransitions()->first()->from_status)->toBe(TaskStatus::Assigned->value)
        ->and($moved->statusTransitions()->first()->to_status)->toBe(TaskStatus::InProgress->value);
});

it('rejects a status transition that is not allowed', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->done()->create(['area_id' => $area->id]);

    $this->actingAs($manager);

    expect(fn () => app(MoveTask::class)->handle($task, TaskStatus::Assigned))
        ->toThrow(ValidationException::class);
});

it('blocks starting a task until its finish-to-start dependency is done', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $dependency = Task::factory()->create(['area_id' => $area->id, 'status' => TaskStatus::Todo]);
    $task = Task::factory()->create(['area_id' => $area->id, 'status' => TaskStatus::Todo]);

    TaskDependency::create([
        'task_id' => $task->id,
        'depends_on_task_id' => $dependency->id,
        'type' => 'finish_to_start',
    ]);

    $this->actingAs($manager);

    expect(fn () => app(MoveTask::class)->handle($task, TaskStatus::InProgress))
        ->toThrow(ValidationException::class);

    $dependency->update(['status' => TaskStatus::Done]);

    expect(app(MoveTask::class)->handle($task, TaskStatus::InProgress)->status)
        ->toBe(TaskStatus::InProgress);
});

it('does not let a manager move tasks outside their area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create(['status' => TaskStatus::Todo]);

    $this->actingAs($manager);

    expect(fn () => app(MoveTask::class)->handle($task, TaskStatus::InProgress))
        ->toThrow(AuthorizationException::class);
});
