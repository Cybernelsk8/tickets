<?php

use App\Livewire\Boards\Index;
use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\TaskStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('loads only the first batch of tasks and loads more on demand', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();

    $tasks = collect(range(1, 55))->map(fn (int $number): Task => Task::factory()->create([
        'area_id' => $area->id,
        'title' => sprintf('Tarea %03d', $number),
        'position' => $number,
    ]));

    Livewire::actingAs($admin)->test(Index::class)
        ->assertSeeHtml('wire:key="board-task-'.$tasks->get(0)->id.'"')
        ->assertSeeHtml('wire:key="board-task-'.$tasks->get(49)->id.'"')
        ->assertDontSeeHtml('wire:key="board-task-'.$tasks->get(50)->id.'"')
        ->assertSeeHtml('>55</span>')
        ->call('loadMore', 'todo')
        ->assertSeeHtml('wire:key="board-task-'.$tasks->get(50)->id.'"');
});

it('keeps recent done tasks on the board and loads old history by date on demand', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();

    $recent = Task::factory()->done()->create([
        'area_id' => $area->id,
        'title' => 'Terminada reciente',
    ]);
    $old = Task::factory()->done()->create([
        'area_id' => $area->id,
        'title' => 'Terminada antigua',
        'completed_at' => now()->subDays(40),
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->assertSeeHtml('wire:key="board-task-'.$recent->id.'"')
        ->assertDontSeeHtml('wire:key="board-task-'.$old->id.'"')
        ->assertSee('Historial por fecha')
        ->call('toggleHistoryGroup', $old->completed_at->format('Y-m'))
        ->assertSeeHtml('wire:key="history-task-'.$old->id.'"')
        ->assertSee($old->title);
});

it('finds tasks with the board search and switches to the list view', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();

    $matching = Task::factory()->create([
        'area_id' => $area->id,
        'title' => 'Informe mensual de ventas',
    ]);
    $other = Task::factory()->create([
        'area_id' => $area->id,
        'title' => 'Actualizar permisos',
    ]);

    Livewire::actingAs($admin)->test(Index::class)
        ->set('search', 'mensual')
        ->assertSeeHtml('wire:key="board-task-'.$matching->id.'"')
        ->assertDontSeeHtml('wire:key="board-task-'.$other->id.'"')
        ->call('setViewMode', 'list')
        ->assertSee('Lista de tareas')
        ->assertSeeHtml('data-flux-table')
        ->assertSee($matching->title)
        ->assertDontSeeHtml('wire:key="list-task-'.$other->id.'"');
});

it('does not reindex archived done tasks when a recent task is dropped', function () {
    $manager = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($manager)->create();

    $recent = Task::factory()->done()->create([
        'area_id' => $area->id,
        'position' => 5,
    ]);
    $old = Task::factory()->done()->create([
        'area_id' => $area->id,
        'completed_at' => now()->subDays(40),
        'position' => 0,
    ]);

    Livewire::actingAs($manager)->test(Index::class)
        ->call('handleSort', $recent->id, 0, 'done')
        ->assertHasNoErrors();

    expect($recent->fresh()->position)->toBe(0)
        ->and($recent->fresh()->status)->toBe(TaskStatus::Done)
        ->and($old->fresh()->position)->toBe(0);
});
