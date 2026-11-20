<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

it('shows ticket navigation to users with permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Bandeja')
        ->assertSee('Cuentas de correo')
        ->assertSee('Tickets')
        ->assertSee('Tareas')
        ->assertDontSee('Mis tareas')
        ->assertSee('Usuarios')
        ->assertSee('Roles y permisos');
});

it('hides ticket navigation from unprivileged users', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Bandeja')
        ->assertDontSee('Cuentas de correo')
        ->assertDontSee('Tableros')
        ->assertDontSee('Mis tareas');
});

it('redirects the legacy my tasks route to the unified board', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('tasks.mine'))
        ->assertRedirect(route('boards.index', ['scope' => 'mine']));
});

it('renders each application page', function (string $routeName) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route($routeName))->assertOk();
})->with([
    'dashboard',
    'mail.inbox',
    'mail.accounts.index',
    'tickets.index',
    'boards.index',
    'admin.users.index',
    'admin.roles.index',
]);
