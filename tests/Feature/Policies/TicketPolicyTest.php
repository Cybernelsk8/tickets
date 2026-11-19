<?php

use App\Models\Area;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

it('allows area managers to assign tickets in their area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();
    $area = Area::factory()->managedBy($jefeArea)->create();
    $ticket = Ticket::factory()->create(['area_id' => $area->id]);

    expect(Gate::forUser($jefeArea)->allows('assign', $ticket))->toBeTrue();
});

it('forbids area managers from assigning tickets in another area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();
    Area::factory()->managedBy($jefeArea)->create();
    $ticket = Ticket::factory()->create();

    expect(Gate::forUser($jefeArea)->allows('assign', $ticket))->toBeFalse();
});

it('allows mail managers to assign tickets in any area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $mailManager = User::factory()->mailManager()->create();
    $ticket = Ticket::factory()->create();

    expect(Gate::forUser($mailManager)->allows('assign', $ticket))->toBeTrue();
});

it('lets an assignee view a ticket without global view permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $colaborador->id]);

    expect(Gate::forUser($colaborador)->allows('view', $ticket))->toBeTrue();
});
