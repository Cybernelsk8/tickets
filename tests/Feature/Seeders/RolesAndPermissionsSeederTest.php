<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Role;

it('creates roles with the expected permission boundaries', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $superAdmin = Role::findByName('super_admin');
    $jefeArea = Role::findByName('jefe_area');
    $colaborador = Role::findByName('colaborador');

    expect($superAdmin->hasPermissionTo('mail.accounts.delete'))->toBeTrue();
    expect($jefeArea->hasPermissionTo('tickets.assign'))->toBeTrue();
    expect($jefeArea->hasPermissionTo('mail.accounts.delete'))->toBeFalse();
    expect($colaborador->hasPermissionTo('tickets.assign'))->toBeFalse();
    expect($colaborador->hasPermissionTo('comments.create'))->toBeTrue();
});

it('can be run twice without duplicating roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(5);
});
