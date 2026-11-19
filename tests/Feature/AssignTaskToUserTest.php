<?php

use App\Actions\Tasks\AssignTaskToUser;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;

it('assigns a board task to a collaborator of the same area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $collaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);
    $task = Task::factory()->create(['area_id' => $area->id, 'assignee_id' => null]);

    $this->actingAs($manager);

    $updated = app(AssignTaskToUser::class)->handle($task, $collaborator->id);

    expect($updated->assignee_id)->toBe($collaborator->id);
});

it('rejects a responsible who belongs to another area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();
    $task = Task::factory()->create(['area_id' => $area->id]);
    $outsider = User::factory()->colaborador()->create();

    $this->actingAs($manager);

    expect(fn () => app(AssignTaskToUser::class)->handle($task, $outsider->id))
        ->toThrow(ValidationException::class);
});
