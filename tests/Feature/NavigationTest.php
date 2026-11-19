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
        ->assertSee('Tableros')
        ->assertSee('Mis tareas');
});

it('hides ticket navigation from unprivileged users', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Bandeja')
        ->assertDontSee('Cuentas de correo')
        ->assertDontSee('Tableros');
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
    'tasks.mine',
]);
