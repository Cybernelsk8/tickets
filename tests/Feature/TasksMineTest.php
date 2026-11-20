<?php

use App\Livewire\Boards\Index;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('shows only the tasks assigned to the authenticated collaborator by default', function () {
    $collaborator = User::factory()->colaborador()->create();
    $area = Area::factory()->create(['manager_id' => null]);
    $collaborator->update(['area_id' => $area->id]);
    $otherCollaborator = User::factory()->colaborador()->create(['area_id' => $area->id]);

    Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $collaborator->id,
        'title' => 'Tarea propia visible',
    ]);
    Task::factory()->create([
        'area_id' => $area->id,
        'assignee_id' => $otherCollaborator->id,
        'title' => 'Tarea ajena oculta',
    ]);

    Livewire::actingAs($collaborator)->test(Index::class)
        ->assertSet('scope', 'mine')
        ->assertSee('Mis tareas')
        ->assertSee('Tarea propia visible')
        ->assertDontSee('Tarea ajena oculta');
});

it('uses the area scope by default for area managers', function () {
    $manager = User::factory()->jefeArea()->create();
    $ownedArea = Area::factory()->managedBy($manager)->create(['name' => 'Soporte propio']);
    $otherArea = Area::factory()->create(['name' => 'Soporte ajeno']);

    Task::factory()->create(['area_id' => $ownedArea->id, 'title' => 'Tarea del área propia']);
    Task::factory()->create(['area_id' => $otherArea->id, 'title' => 'Tarea del área ajena']);

    Livewire::actingAs($manager)->test(Index::class)
        ->assertSet('scope', 'area')
        ->assertSee('Mi área')
        ->assertSee('Tarea del área propia')
        ->assertDontSee('Tarea del área ajena')
        ->assertDontSee('Todas las áreas');
});

it('uses the all areas scope by default for super administrators', function () {
    $admin = User::factory()->superAdmin()->create();
    $firstArea = Area::factory()->create(['name' => 'Área norte']);
    $secondArea = Area::factory()->create(['name' => 'Área sur']);

    Task::factory()->create(['area_id' => $firstArea->id, 'title' => 'Tarea norte']);
    Task::factory()->create(['area_id' => $secondArea->id, 'title' => 'Tarea sur']);

    Livewire::actingAs($admin)->test(Index::class)
        ->assertSet('scope', 'all')
        ->assertSee('Todas las áreas')
        ->assertSee('Tarea norte')
        ->assertSee('Tarea sur');
});
