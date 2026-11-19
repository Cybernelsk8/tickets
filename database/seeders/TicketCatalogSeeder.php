<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TicketCatalogSeeder extends Seeder
{
    /**
     * Seed the catalog required to assign tickets: areas, categories and responsibles.
     */
    public function run(): void
    {
        $jefe = User::firstOrCreate([
            'email' => 'jefe.soporte@tickets.test',
        ], [
            'name' => 'Jefe de Soporte',
            'password' => Hash::make('password'),
            'job_title' => 'Jefe de área',
            'is_active' => true,
        ]);
        $jefe->syncRoles(['jefe_area']);
        $jefe->forceFill(['email_verified_at' => now()])->save();

        $colaborador = User::firstOrCreate([
            'email' => 'colaborador.soporte@tickets.test',
        ], [
            'name' => 'Colaborador de Soporte',
            'password' => Hash::make('password'),
            'job_title' => 'Técnico',
            'is_active' => true,
        ]);
        $colaborador->syncRoles(['colaborador']);
        $colaborador->forceFill(['email_verified_at' => now()])->save();

        $soporte = Area::firstOrCreate([
            'slug' => 'soporte-tecnico',
        ], [
            'name' => 'Soporte Técnico',
            'description' => 'Atención de incidencias y soporte a usuarios',
            'color' => '#3b82f6',
            'is_active' => true,
        ]);
        $soporte->update(['manager_id' => $jefe->id]);

        $informatica = Area::firstOrCreate([
            'slug' => 'informatica',
        ], [
            'name' => 'Informática',
            'description' => 'Infraestructura, sistemas y aplicaciones',
            'color' => '#8b5cf6',
            'is_active' => true,
        ]);

        $administracion = Area::firstOrCreate([
            'slug' => 'administracion',
        ], [
            'name' => 'Administración',
            'description' => 'Gestión administrativa y financiera',
            'color' => '#f59e0b',
            'is_active' => true,
        ]);

        Area::firstOrCreate([
            'slug' => 'recursos-humanos',
        ], [
            'name' => 'Recursos Humanos',
            'description' => 'Gestión de personal y desarrollo humano',
            'color' => '#10b981',
            'is_active' => true,
        ]);

        $jefe->update(['area_id' => $soporte->id]);
        $colaborador->update(['area_id' => $soporte->id]);

        $existingLocalUser = User::query()
            ->whereNotIn('email', ['jefe.soporte@tickets.test', 'colaborador.soporte@tickets.test'])
            ->whereNull('area_id')
            ->first();

        if ($existingLocalUser !== null) {
            $existingLocalUser->update(['area_id' => $soporte->id, 'is_active' => true]);
        }

        $categories = [
            ['slug' => 'incidente', 'name' => 'Incidente', 'description' => 'Fallas que interrumpen el servicio', 'color' => '#ef4444', 'default_area_id' => $soporte->id],
            ['slug' => 'solicitud', 'name' => 'Solicitud', 'description' => 'Pedidos de servicio o información', 'color' => '#3b82f6', 'default_area_id' => $administracion->id],
            ['slug' => 'consulta', 'name' => 'Consulta', 'description' => 'Dudas y orientación general', 'color' => '#8b5cf6', 'default_area_id' => $informatica->id],
            ['slug' => 'acceso-y-credenciales', 'name' => 'Acceso y credenciales', 'description' => 'Cuentas, contraseñas y permisos', 'color' => '#f59e0b', 'default_area_id' => $soporte->id],
        ];

        foreach ($categories as $category) {
            TicketCategory::firstOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
