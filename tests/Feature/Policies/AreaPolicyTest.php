<?php

use App\Models\Area;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;

it('allows area managers to update only their own area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $jefeArea = User::factory()->jefeArea()->create();

    $ownArea = Area::factory()->managedBy($jefeArea)->create();
    $otherArea = Area::factory()->create();

    expect(Gate::forUser($jefeArea)->allows('update', $ownArea))->toBeTrue();
    expect(Gate::forUser($jefeArea)->allows('update', $otherArea))->toBeFalse();
});

it('allows super admins to manage every area', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();

    expect(Gate::forUser($admin)->allows('delete', $area))->toBeTrue();
});

it('hides area management from collaborators', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $colaborador = User::factory()->colaborador()->create();
    $area = Area::factory()->create();

    expect(Gate::forUser($colaborador)->allows('update', $area))->toBeFalse();
    expect(Gate::forUser($colaborador)->allows('view', $area))->toBeTrue();
});
