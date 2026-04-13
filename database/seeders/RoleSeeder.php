<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\RbacPermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::query()->upsert(
            collect(RbacPermissionMatrix::allPermissions())
                ->map(fn (string $permission): array => [
                    'name' => $permission,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all(),
            ['name', 'guard_name'],
            ['updated_at'],
        );

        Role::query()->upsert(
            collect(RbacPermissionMatrix::roles())
                ->map(fn (string $role): array => [
                    'name' => $role,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all(),
            ['name', 'guard_name'],
            ['updated_at'],
        );

        foreach (RbacPermissionMatrix::rolePermissions() as $roleName => $permissions) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->firstOrFail();

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
