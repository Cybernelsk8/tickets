<?php

use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

it('allows area managers to move tasks in their area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($jefeArea)->create();
    $task = Task::factory()->create(['area_id' => $area->id]);

    expect(Gate::forUser($jefeArea)->allows('create', Task::class))->toBeTrue();
    expect(Gate::forUser($jefeArea)->allows('move', $task))->toBeTrue();
});

it('forbids area managers from moving tasks in another area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();
    Area::factory()->managedBy($jefeArea)->create();
    $task = Task::factory()->create();

    expect(Gate::forUser($jefeArea)->allows('move', $task))->toBeFalse();
});

it('lets assignees view and update their own tasks', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $task = Task::factory()->create(['assignee_id' => $colaborador->id]);

    expect(Gate::forUser($colaborador)->allows('view', $task))->toBeTrue();
    expect(Gate::forUser($colaborador)->allows('update', $task))->toBeTrue();
});

it('does not let collaborators create tasks', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();

    expect(Gate::forUser($colaborador)->allows('create', Task::class))->toBeFalse();
});

it('lets area managers reassign tasks in their area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($jefeArea)->create();
    $task = Task::factory()->create(['area_id' => $area->id]);

    expect(Gate::forUser($jefeArea)->allows('reassign', $task))->toBeTrue();
});

it('does not let collaborators reassign tasks', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $colaborador->update(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $colaborador->id]);

    expect(Gate::forUser($colaborador)->allows('reassign', $task))->toBeFalse();
});

it('lets collaborators create subtasks on their own tasks in their area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $colaborador->update(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => $colaborador->id]);

    expect(Gate::forUser($colaborador)->allows('createSubtask', $task))->toBeTrue()
        ->and(Gate::forUser($colaborador)->allows('create', Task::class))->toBeFalse();
});

it('does not let collaborators create subtasks on tasks from another area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $ownArea = Area::factory()->create(['manager_id' => null]);
    $otherArea = Area::factory()->create(['manager_id' => null]);
    $colaborador->update(['area_id' => $ownArea->id]);
    $task = Task::factory()->create(['area_id' => $otherArea->id, 'assignee_id' => $colaborador->id]);

    expect(Gate::forUser($colaborador)->allows('createSubtask', $task))->toBeFalse();
});

it('does not let collaborators create subtasks on tasks assigned to someone else', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $colaborador->update(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id]);

    expect(Gate::forUser($colaborador)->allows('createSubtask', $task))->toBeFalse();
});

it('lets area managers create subtasks in their managed area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($jefeArea)->create();
    $task = Task::factory()->create(['area_id' => $area->id]);

    expect(Gate::forUser($jefeArea)->allows('createSubtask', $task))->toBeTrue();
});
