<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Support\RbacPermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RbacSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_exact_legacy_roles_permissions_and_role_matrix(): void
    {
        $this->assertSame(
            collect(RbacPermissionMatrix::roles())->sort()->values()->all(),
            Role::query()->orderBy('name')->pluck('name')->all(),
        );

        $this->assertSame(
            collect(RbacPermissionMatrix::allPermissions())->sort()->values()->all(),
            Permission::query()->orderBy('name')->pluck('name')->all(),
        );

        foreach (RbacPermissionMatrix::rolePermissions() as $roleName => $expectedPermissions) {
            $this->assertSame(
                collect($expectedPermissions)->sort()->values()->all(),
                Role::query()
                    ->where('name', $roleName)
                    ->firstOrFail()
                    ->permissions()
                    ->orderBy('name')
                    ->pluck('name')
                    ->all(),
                $roleName,
            );
        }
    }
}
