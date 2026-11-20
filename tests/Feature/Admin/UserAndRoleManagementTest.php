<?php

use App\Livewire\Admin\Roles\Index as RolesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\Area;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates a user with roles and direct permissions from the admin panel', function () {
    $admin = User::factory()->superAdmin()->create();
    $area = Area::factory()->create();

    Livewire::actingAs($admin)->test(UsersIndex::class)
        ->set('name', 'María López')
        ->set('email', 'maria@example.com')
        ->set('password', 'password123')
        ->set('areaId', $area->id)
        ->set('jobTitle', 'Analista')
        ->set('selectedRoles', ['colaborador'])
        ->set('selectedPermissions', ['reports.view'])
        ->call('save')
        ->assertHasNoErrors();

    $user = User::where('email', 'maria@example.com')->firstOrFail();

    expect($user->hasRole('colaborador'))->toBeTrue()
        ->and($user->hasPermissionTo('reports.view'))->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeTrue()
        ->and($user->area_id)->toBe($area->id);
});

it('updates the roles and direct permissions of an existing user', function () {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->colaborador()->create();

    Livewire::actingAs($admin)->test(UsersIndex::class)
        ->call('openEdit', $target->id)
        ->set('name', 'Nombre actualizado')
        ->set('selectedRoles', ['consultor'])
        ->set('selectedPermissions', ['reports.view'])
        ->call('save')
        ->assertHasNoErrors();

    $target->refresh();

    expect($target->name)->toBe('Nombre actualizado')
        ->and($target->hasRole('consultor'))->toBeTrue()
        ->and($target->hasRole('colaborador'))->toBeFalse()
        ->and($target->hasPermissionTo('reports.view'))->toBeTrue();
});

it('protects super administrators and the current user from deletion', function () {
    $admin = User::factory()->superAdmin()->create();
    $otherAdmin = User::factory()->superAdmin()->create();

    Livewire::actingAs($admin)->test(UsersIndex::class)
        ->call('delete', $admin->id)
        ->assertHasErrors('user')
        ->call('delete', $otherAdmin->id)
        ->assertHasErrors('user');

    expect(User::count())->toBe(2);
});

it('creates a role with permissions from the admin panel', function () {
    $admin = User::factory()->superAdmin()->create();

    Livewire::actingAs($admin)->test(RolesIndex::class)
        ->set('name', 'auditor')
        ->set('selectedPermissions', ['reports.view', 'tickets.viewAny'])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::findByName('auditor');

    expect($role->hasPermissionTo('reports.view'))->toBeTrue()
        ->and($role->hasPermissionTo('tickets.viewAny'))->toBeTrue();
});

it('cannot rename or delete the super administrator role', function () {
    $admin = User::factory()->superAdmin()->create();
    $role = Role::findByName('super_admin');

    Livewire::actingAs($admin)->test(RolesIndex::class)
        ->call('openEdit', $role->id)
        ->set('name', 'otro_nombre')
        ->call('save')
        ->assertHasErrors('role')
        ->call('delete', $role->id)
        ->assertHasErrors('role');

    expect(Role::findByName('super_admin'))->not->toBeNull();
});

it('deletes a role without users', function () {
    $admin = User::factory()->superAdmin()->create();
    $role = Role::create(['name' => 'temporal']);

    Livewire::actingAs($admin)->test(RolesIndex::class)
        ->call('delete', $role->id)
        ->assertHasNoErrors();

    expect(Role::find('temporal'))->toBeNull();
});

it('refuses administration pages to users without permissions', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with(['admin.users.index', 'admin.roles.index']);

it('shows administration navigation only to authorized users', function () {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Usuarios')
        ->assertSee('Roles y permisos');

    $this->actingAs(User::factory()->colaborador()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Roles y permisos');
});
