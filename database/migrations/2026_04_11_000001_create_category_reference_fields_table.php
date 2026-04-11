<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_reference_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('reference_type');
            $table->string('label');
            $table->string('help_text')->nullable();
            $table->string('input_type')->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_primary_match_key')->default(false);
            $table->string('normalizer')->default('uppercase_compact');
            $table->string('dwh_entity')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'reference_type'], 'category_reference_fields_category_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_reference_fields');
    }
};
