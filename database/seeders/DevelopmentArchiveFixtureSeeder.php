<?php

namespace Database\Seeders;

use App\Models\Archive;
use App\Models\BranchOffice;
use App\Models\Category;
use App\Models\User;
use App\Services\ArchiveBusinessReferenceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DevelopmentArchiveFixtureSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DevelopmentArchiveFixtureSeeder skipped outside local/testing environments.');

            return;
        }

        $user = User::query()->firstOrCreate(
            ['email' => 'dev-fixtures@example.com'],
            [
                'name' => 'Development Fixtures',
                'username' => 'devfixtures',
                'password' => Hash::make('password'),
                'branch_office_id' => BranchOffice::query()->where('branch_code', '01')->value('id'),
            ],
        );

        $definitions = [
            [
                'category_name' => 'TABUNGAN',
                'branch_code' => '01',
                'archive_code' => 'DEV-TAB-001',
                'archive_name' => 'Fixture Tabungan 001',
                'archive_description' => 'Local-only sample archive for savings coverage.',
                'archive_date' => '2026-04-10',
                'archive_path' => 'archives/dev/fixture-tabungan-001.txt',
                'archive_type' => 'txt',
                'references' => [
                    'cif' => '00001234',
                    'savings_account_no' => ' 001 234 567 ',
                ],
            ],
            [
                'category_name' => 'KREDIT',
                'branch_code' => '02',
                'archive_code' => 'DEV-KRD-001',
                'archive_name' => 'Fixture Kredit 001',
                'archive_description' => 'Local-only sample archive for loan coverage.',
                'archive_date' => '2026-04-10',
                'archive_path' => 'archives/dev/fixture-kredit-001.txt',
                'archive_type' => 'txt',
                'references' => [
                    'cif' => '00005678',
                    'loan_account_no' => 'LN 000 9988',
                ],
            ],
            [
                'category_name' => 'BILYET DEPOSITO',
                'branch_code' => '03',
                'archive_code' => 'DEV-DPS-001',
                'archive_name' => 'Fixture Deposito 001',
                'archive_description' => 'Local-only sample archive for deposito coverage.',
                'archive_date' => '2026-04-10',
                'archive_path' => 'archives/dev/fixture-deposito-001.txt',
                'archive_type' => 'txt',
                'references' => [
                    'cif' => '00007890',
                    'deposito_bilyet_no' => 'BLY 000 123',
                ],
            ],
        ];

        $referenceService = app(ArchiveBusinessReferenceService::class);

        foreach ($definitions as $definition) {
            $category = Category::query()->where('category_name', $definition['category_name'])->first();
            $branchOffice = BranchOffice::query()->where('branch_code', $definition['branch_code'])->first();

            if (! $category || ! $branchOffice) {
                continue;
            }

            Storage::disk('public')->put(
                $definition['archive_path'],
                implode(PHP_EOL, [
                    'SIARDI development fixture',
                    'Category: '.$definition['category_name'],
                    'Archive code: '.$definition['archive_code'],
                ]),
            );

            $archive = Archive::query()->updateOrCreate(
                ['archive_code' => $definition['archive_code']],
                [
                    'archive_category' => $category->id,
                    'archive_user' => $user->id,
                    'archive_name' => $definition['archive_name'],
                    'archive_description' => $definition['archive_description'],
                    'archive_path' => $definition['archive_path'],
                    'archive_type' => $definition['archive_type'],
                    'archive_branch_office' => $branchOffice->id,
                    'archive_date' => $definition['archive_date'],
                ],
            );

            $fields = $referenceService->getFieldDefinitionsForCategory($category->id)->keyBy('reference_type');
            $formState = [];

            foreach ($definition['references'] as $referenceType => $rawValue) {
                $field = $fields->get($referenceType);

                if ($field) {
                    $formState[$field->id] = $rawValue;
                }
            }

            $referenceService->syncForArchive($archive->fresh('branchOffice.dwhMapping'), $formState);
        }
    }
}
