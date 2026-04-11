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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->onDelete('cascade')
                ->after('email'); // Assuming you want to place it after the email column
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'branch_office_id')) {
                $table->dropForeign(['branch_office_id']);
                $table->dropColumn('branch_office_id');
            }
        });
    }
};
