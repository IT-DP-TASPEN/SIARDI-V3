<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branch_offices', function (Blueprint $table) {
            $table
                ->char('branch_code', 2)
                ->unique()
                ->after('id')
                ->comment('Kode Cabang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branch_offices', function (Blueprint $table) {
            if (Schema::hasColumn('branch_offices', 'branch_code')) {
                $table->dropColumn('branch_code');
            }
        });
    }
};
