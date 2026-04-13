<?php

namespace Database\Seeders;

use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            BranchOfficeSeeder::class,
            CategorySeeder::class,
            BusinessReferenceConfigurationSeeder::class,
        ]);

        $adminBranchOffice = BranchOffice::query()->where('branch_code', '01')->first();

        $user = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'SIARDI Admin',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'branch_office_id' => $adminBranchOffice?->id,
            ],
        );

        $user->syncRoles(['super_admin']);

        $user->permittedCategories()->syncWithoutDetaching(Category::query()->pluck('id')->all());
    }
}
