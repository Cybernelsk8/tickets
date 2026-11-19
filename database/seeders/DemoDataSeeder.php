<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Comment;
use App\Models\MailAccount;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskStatusTransition;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\TaskStatus;
use App\TicketPriority;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed a small, coherent dataset for local development.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate([
            'email' => 'admin@tickets.test',
        ], [
            'name' => 'Administrador',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $admin->syncRoles(['super_admin']);

        $manager = User::firstOrCreate([
            'email' => 'jefe@tickets.test',
        ], [
            'name' => 'Jefe de Soporte',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'job_title' => 'Jefe de área',
        ]);
        $manager->syncRoles(['jefe_area']);

        $collaborator = User::firstOrCreate([
            'email' => 'colaborador@tickets.test',
        ], [
            'name' => 'Colaborador Soporte',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'job_title' => 'Técnico',
        ]);
        $collaborator->syncRoles(['colaborador']);

        $area = Area::firstOrCreate([
            'slug' => 'soporte',
        ], [
            'name' => 'Soporte Técnico',
            'description' => 'Atención de incidencias y soporte a usuarios',
            'manager_id' => $manager->id,
            'color' => '#3b82f6',
            'is_active' => true,
        ]);
        $area->update(['manager_id' => $manager->id]);

        $manager->update(['area_id' => $area->id]);
        $collaborator->update(['area_id' => $area->id]);

        $category = TicketCategory::firstOrCreate([
            'slug' => 'incidente',
        ], [
            'name' => 'Incidente',
            'description' => 'Fallas que interrumpen el servicio',
            'color' => '#ef4444',
            'default_area_id' => $area->id,
            'is_active' => true,
        ]);

        MailAccount::firstOrCreate([
            'email' => 'soporte@tickets.test',
        ], [
            'name' => 'Bandeja de Soporte',
            'imap_host' => 'imap.tickets.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.tickets.test',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'username' => 'soporte@tickets.test',
            'credentials' => 'demo-password',
            'owner_user_id' => $admin->id,
            'is_active' => true,
            'sync_every_minutes' => 15,
        ]);

        $ticket = Ticket::firstOrCreate([
            'code' => 'TKT-0001',
        ], [
            'source' => 'manual',
            'subject' => 'No puede acceder al sistema de facturación',
            'description' => 'El usuario reporta error de autenticación al abrir facturación.',
            'requester_name' => 'Usuario Demo',
            'requester_email' => 'usuario@example.com',
            'area_id' => $area->id,
            'category_id' => $category->id,
            'priority' => TicketPriority::High,
            'status' => TaskStatus::Assigned,
            'assignee_id' => $manager->id,
            'created_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $rootTask = Task::firstOrCreate([
            'ticket_id' => $ticket->id,
            'parent_id' => null,
        ], [
            'title' => 'Resolver acceso a facturación',
            'description' => 'Tarea raíz del proyecto. Coordinar diagnóstico y corrección.',
            'assignee_id' => $manager->id,
            'area_id' => $area->id,
            'status' => TaskStatus::Assigned,
            'priority' => TicketPriority::High,
            'estimated_minutes' => 240,
            'created_by' => $admin->id,
        ]);

        $firstSubtask = Task::firstOrCreate([
            'parent_id' => $rootTask->id,
            'title' => 'Diagnosticar credenciales',
        ], [
            'ticket_id' => $ticket->id,
            'description' => 'Revisar cuenta, permisos y logs de autenticación.',
            'assignee_id' => $collaborator->id,
            'area_id' => $area->id,
            'status' => TaskStatus::Done,
            'priority' => TicketPriority::High,
            'estimated_minutes' => 60,
            'actual_minutes' => 45,
            'started_at' => now()->subHours(3),
            'completed_at' => now()->subHours(2),
            'created_by' => $manager->id,
        ]);

        $secondSubtask = Task::firstOrCreate([
            'parent_id' => $rootTask->id,
            'title' => 'Aplicar corrección y validar',
        ], [
            'ticket_id' => $ticket->id,
            'description' => 'Ajustar permisos y confirmar acceso con el usuario.',
            'assignee_id' => $collaborator->id,
            'area_id' => $area->id,
            'status' => TaskStatus::Todo,
            'priority' => TicketPriority::High,
            'estimated_minutes' => 90,
            'created_by' => $manager->id,
        ]);

        TaskDependency::firstOrCreate([
            'task_id' => $secondSubtask->id,
            'depends_on_task_id' => $firstSubtask->id,
        ], [
            'type' => 'finish_to_start',
        ]);

        foreach ([$rootTask, $firstSubtask] as $task) {
            TaskStatusTransition::firstOrCreate([
                'task_id' => $task->id,
                'to_status' => TaskStatus::Assigned->value,
                'occurred_at' => $ticket->assigned_at ?? now(),
            ], [
                'changed_by' => $admin->id,
                'note' => 'Asignación inicial del ticket',
            ]);
        }

        Comment::firstOrCreate([
            'commentable_type' => Task::class,
            'commentable_id' => $secondSubtask->id,
            'user_id' => $collaborator->id,
            'body' => 'Quedo a la espera de que se libere la dependencia para continuar.',
        ], [
            'is_internal' => false,
        ]);
    }
}
