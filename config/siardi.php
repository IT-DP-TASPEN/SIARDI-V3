<?php

return [
    'features' => [
        'business_references' => env('SIARDI_ENABLE_BUSINESS_REFERENCES', true),
        'dwh_reconciliation' => env('SIARDI_ENABLE_DWH_RECONCILIATION', true),
        'legacy_reference_linking' => env('SIARDI_ENABLE_LEGACY_REFERENCE_LINKING', true),
    ],

    'admin_roles' => [
        'super_admin',
    ],

    'supported_reconciliation_categories' => [
        'TABUNGAN' => 'savings',
        'KREDIT' => 'loans',
        'BILYET DEPOSITO' => 'time_deposits',
    ],
];
