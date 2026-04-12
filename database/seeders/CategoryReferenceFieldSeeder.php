<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoryReferenceFieldSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'TABUNGAN' => [
                [
                    'reference_type' => 'cif',
                    'label' => 'CIF',
                    'help_text' => 'Masukkan CIF nasabah sesuai data DWH.',
                    'input_type' => 'text',
                    'sort_order' => 10,
                    'is_required' => true,
                    'is_primary_match_key' => false,
                    'normalizer' => 'uppercase_compact',
                    'dwh_entity' => 'savings',
                ],
                [
                    'reference_type' => 'savings_account_no',
                    'label' => 'Nomor Rekening Tabungan',
                    'help_text' => 'Nomor rekening tabungan yang akan direkonsiliasi dengan DWH.',
                    'input_type' => 'text',
                    'sort_order' => 20,
                    'is_required' => true,
                    'is_primary_match_key' => true,
                    'normalizer' => 'uppercase_compact',
                    'dwh_entity' => 'savings',
                ],
            ],
            'KREDIT' => [
                [
                    'reference_type' => 'cif',
                    'label' => 'CIF',
                    'help_text' => 'Masukkan CIF debitur sesuai data DWH.',
                    'input_type' => 'text',
                    'sort_order' => 10,
                    'is_required' => true,
                    'is_primary_match_key' => false,
                    'normalizer' => 'uppercase_compact',
                    'dwh_entity' => 'loans',
                ],
                [
                    'reference_type' => 'loan_account_no',
                    'label' => 'Nomor Rekening Kredit',
                    'help_text' => 'Gunakan nomor rekening kredit atau loan account dari DWH.',
                    'input_type' => 'text',
                    'sort_order' => 20,
                    'is_required' => true,
                    'is_primary_match_key' => true,
                    'normalizer' => 'uppercase_compact',
                    'dwh_entity' => 'loans',
                ],
            ],
            'BILYET DEPOSITO' => [
                [
                    'reference_type' => 'cif',
                    'label' => 'CIF',
                    'help_text' => 'Masukkan CIF nasabah deposito.',
                    'input_type' => 'text',
                    'sort_order' => 10,
                    'is_required' => true,
                    'is_primary_match_key' => false,
                    'normalizer' => 'uppercase_compact',
                    'dwh_entity' => 'time_deposits',
                ],
                [
                    'reference_type' => 'deposito_bilyet_no',
                    'label' => 'Nomor Bilyet Deposito',
                    'help_text' => 'Gunakan nomor bilyet atau identifier deposito dari DWH.',
                    'input_type' => 'text',
                    'sort_order' => 20,
                    'is_required' => true,
                    'is_primary_match_key' => true,
                    'normalizer' => 'uppercase_compact',
                    'dwh_entity' => 'time_deposits',
                ],
            ],
        ];

        foreach ($definitions as $categoryName => $fields) {
            $category = Category::query()->where('category_name', $categoryName)->first();

            if (! $category) {
                continue;
            }

            foreach ($fields as $field) {
                DB::table('category_reference_fields')->updateOrInsert(
                    [
                        'category_id' => $category->id,
                        'reference_type' => $field['reference_type'],
                    ],
                    $field + [
                        'category_id' => $category->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }
}
