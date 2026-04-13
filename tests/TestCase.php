<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
            $this->seed(RoleSeeder::class);
        }
    }

    protected function assignRole(User $user, string $role): User
    {
        $user->assignRole($role);

        return $user;
    }
}
