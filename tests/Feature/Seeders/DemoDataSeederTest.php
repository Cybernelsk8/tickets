<?php

use App\Models\Area;
use App\Models\Task;
use App\Models\Ticket;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

it('seeds a coherent demo hierarchy', function () {
    $this->seed([RolesAndPermissionsSeeder::class, DemoDataSeeder::class]);

    $area = Area::where('slug', 'soporte')->firstOrFail();
    $ticket = Ticket::where('code', 'TKT-0001')->firstOrFail();
    $rootTask = $ticket->rootTask;

    expect($area->manager->email)->toBe('jefe@tickets.test');
    expect($ticket->assignee->email)->toBe('jefe@tickets.test');
    expect($rootTask->children()->count())->toBe(2);
    expect($rootTask->children()->where('title', 'Aplicar corrección y validar')->firstOrFail()->dependencies()->count())->toBe(1);
    expect($rootTask->children()->where('title', 'Aplicar corrección y validar')->firstOrFail()->comments()->where('is_internal', false)->count())->toBe(1);
});

it('can be run twice without duplicating demo records', function () {
    $this->seed([RolesAndPermissionsSeeder::class, DemoDataSeeder::class]);
    $ticketCount = Ticket::count();
    $taskCount = Task::count();

    $this->seed(DemoDataSeeder::class);

    expect(Ticket::count())->toBe($ticketCount);
    expect(Task::count())->toBe($taskCount);
});
