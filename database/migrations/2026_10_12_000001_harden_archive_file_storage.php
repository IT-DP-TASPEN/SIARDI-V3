<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archives', function (Blueprint $table): void {
            $table->string('archive_original_name')->nullable()->after('archive_path');
            $table->string('archive_disk', 32)->default('archives')->after('archive_original_name');
        });

        // Every archive that exists before this migration was uploaded to the public disk.
        // `php artisan siardi:migrate-legacy-archive-files` moves them to the private disk.
        DB::table('archives')->update(['archive_disk' => 'public']);

        // Historical archives must survive deletion of their uploader or category.
        Schema::table('archives', function (Blueprint $table): void {
            $table->dropForeign(['archive_category']);
            $table->dropForeign(['archive_user']);
        });

        Schema::table('archives', function (Blueprint $table): void {
            $table->unsignedBigInteger('archive_user')->nullable()->change();
            $table->foreign('archive_category')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('archive_user')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('archives', function (Blueprint $table): void {
            $table->dropForeign(['archive_category']);
            $table->dropForeign(['archive_user']);
        });

        // archive_user stays nullable: rows orphaned by user deletion cannot be made NOT NULL again.
        Schema::table('archives', function (Blueprint $table): void {
            $table->foreign('archive_category')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('archive_user')->references('id')->on('users')->cascadeOnDelete();
            $table->dropColumn(['archive_original_name', 'archive_disk']);
        });
    }
};
