<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the application's roles and permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'mail.accounts.view',
            'mail.accounts.create',
            'mail.accounts.update',
            'mail.accounts.delete',
            'mail.sync.run',
            'tickets.viewAny',
            'tickets.viewOwnArea',
            'tickets.create',
            'tickets.assign',
            'tickets.update',
            'tickets.delete',
            'areas.viewAny',
            'areas.create',
            'areas.update',
            'areas.delete',
            'categories.viewAny',
            'categories.create',
            'categories.update',
            'categories.delete',
            'tasks.viewAny',
            'tasks.create',
            'tasks.update',
            'tasks.delete',
            'tasks.assign',
            'comments.viewInternal',
            'comments.create',
            'comments.update',
            'comments.delete',
            'attachments.view',
            'attachments.create',
            'attachments.delete',
            'reports.view',
            'reports.viewOwnArea',
            'users.viewAny',
            'users.create',
            'users.update',
            'users.delete',
            'roles.viewAny',
            'roles.create',
            'roles.update',
            'roles.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $rolePermissions = [
            'super_admin' => $permissions,
            'mail_manager' => [
                'mail.accounts.view', 'mail.accounts.create', 'mail.accounts.update',
                'mail.accounts.delete', 'mail.sync.run',
                'tickets.viewAny', 'tickets.create', 'tickets.assign', 'tickets.update',
                'areas.viewAny', 'categories.viewAny', 'tasks.viewAny',
                'attachments.view', 'reports.view',
            ],
            'jefe_area' => [
                'tickets.viewOwnArea', 'tickets.assign', 'tickets.update',
                'areas.viewAny', 'categories.viewAny',
                'tasks.viewAny', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign',
                'comments.viewInternal', 'comments.create', 'comments.update', 'comments.delete',
                'attachments.view', 'attachments.create', 'attachments.delete',
                'reports.viewOwnArea',
            ],
            'colaborador' => [
                'tickets.viewOwnArea',
                'tasks.viewAny', 'tasks.update',
                'comments.create', 'comments.update', 'comments.delete',
                'attachments.view', 'attachments.create',
            ],
            'consultor' => [
                'tickets.viewAny',
                'tasks.viewAny',
                'areas.viewAny',
                'categories.viewAny',
                'attachments.view',
                'reports.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($names);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
