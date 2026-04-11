<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_business_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('archive_id')->constrained('archives')->cascadeOnDelete();
            $table->foreignId('category_reference_field_id')->constrained('category_reference_fields')->cascadeOnDelete();
            $table->string('reference_type');
            $table->string('raw_value');
            $table->string('normalized_value')->nullable()->index();
            $table->string('source_system')->nullable();
            $table->string('source_table')->nullable();
            $table->string('source_key_name')->nullable();
            $table->string('branch_code')->nullable()->index();
            $table->string('matched_table')->nullable();
            $table->string('matched_source_key')->nullable()->index();
            $table->timestamps();
            $table->unique(['archive_id', 'category_reference_field_id'], 'archive_business_references_archive_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_business_references');
    }
};
