<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'operativos.manage',
            'operativos.view',
            'users.manage',
            'users.view',
            'catalogs.manage',
            'catalogs.view',
            'nna.register',
            'nna.view',
            'nna.edit',
            'reports.view',
            'reports.export',
            'imports.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $rolePermissions = [
            'super-admin' => $permissions,
            'admin-nacional' => [
                'operativos.view',
                'users.view',
                'catalogs.manage',
                'catalogs.view',
                'nna.view',
                'nna.edit',
                'reports.view',
                'reports.export',
                'imports.manage',
            ],
            'coordinador-estatal' => [
                'operativos.view',
                'catalogs.view',
                'nna.register',
                'nna.view',
                'nna.edit',
                'reports.view',
                'reports.export',
            ],
            'registrador' => [
                'operativos.view',
                'catalogs.view',
                'nna.register',
                'nna.view',
            ],
            'consultor' => [
                'operativos.view',
                'catalogs.view',
                'nna.view',
                'reports.view',
                'reports.export',
            ],
        ];

        foreach ($rolePermissions as $roleName => $rolePerms) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePerms);
        }
    }
}
