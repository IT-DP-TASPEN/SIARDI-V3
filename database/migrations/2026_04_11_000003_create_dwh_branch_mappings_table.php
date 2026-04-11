<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dwh_branch_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_office_id')->constrained('branch_offices')->cascadeOnDelete();
            $table->string('siardi_branch_code', 2);
            $table->string('dwh_location_code', 3);
            $table->string('dwh_location_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique('branch_office_id');
            $table->unique('dwh_location_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dwh_branch_mappings');
    }
};
