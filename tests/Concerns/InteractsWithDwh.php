<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait InteractsWithDwh
{
    protected function setUpDwhConnection(): void
    {
        config()->set('database.connections.dwh', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('dwh');
        DB::reconnect('dwh');
    }

    protected function createRawSavingsTable(): void
    {
        Schema::connection('dwh')->create('raw_savings', function (Blueprint $table): void {
            $table->string('_row_key')->primary();
            $table->date('as_of_date')->nullable();
            $table->string('locationid')->nullable();
            $table->string('nocif')->nullable();
            $table->string('norekening')->nullable();
            $table->string('noalt')->nullable();
            $table->string('status_dokumen')->nullable();
        });
    }

    protected function createRawLoansTable(): void
    {
        Schema::connection('dwh')->create('raw_loans', function (Blueprint $table): void {
            $table->string('_row_key')->primary();
            $table->date('as_of_date')->nullable();
            $table->string('locationid')->nullable();
            $table->string('nocif')->nullable();
            $table->string('id')->nullable();
            $table->string('noalt')->nullable();
            $table->string('noperjanjiankredit')->nullable();
            $table->string('status_dokumen')->nullable();
        });
    }

    protected function createRawTimeDepositsTable(): void
    {
        Schema::connection('dwh')->create('raw_time_deposits', function (Blueprint $table): void {
            $table->string('_row_key')->primary();
            $table->date('as_of_date')->nullable();
            $table->string('locationid')->nullable();
            $table->string('nocif')->nullable();
            $table->string('nobilyet')->nullable();
            $table->string('status_dokumen')->nullable();
        });
    }
}
