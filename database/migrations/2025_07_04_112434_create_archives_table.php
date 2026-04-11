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
        Schema::create('archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('archive_category')->constrained('categories')->onDelete('cascade');
            $table->foreignId('archive_user')->constrained('users')->onDelete('cascade');
            $table->string('archive_name');
            $table->string('archive_description');
            $table->string('archive_path')->unique();
            $table->string('archive_type');
            $table->date('archive_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
