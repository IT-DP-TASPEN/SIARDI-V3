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
        Schema::table('archives', function (Blueprint $table) {
            $table->foreignId('archive_branch_office')
                ->nullable()
                ->after('archive_type')
                ->constrained('branch_offices')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table) {
            if (Schema::hasColumn('archives', 'archive_branch_office')) {
                $table->dropForeign(['archive_branch_office']);
                $table->dropColumn('archive_branch_office');
            }
        });
    }
};
