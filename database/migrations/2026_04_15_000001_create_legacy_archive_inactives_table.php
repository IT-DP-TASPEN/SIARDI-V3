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
        Schema::create('legacy_archive_inactives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_id')
                ->unique()
                ->constrained('archives')
                ->cascadeOnDelete();
            $table->foreignId('marked_inactive_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('marked_inactive_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_archive_inactives');
    }
};
