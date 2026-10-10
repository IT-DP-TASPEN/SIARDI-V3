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

    protected function createSavingDetailsTable(): void
    {
        Schema::connection('dwh')->create('fincloud_saving_details', function (Blueprint $table): void {
            $table->string('account_no')->primary();
            $table->string('cif_no');
            $table->string('location_id')->nullable();
            $table->string('alt_no')->nullable();
            $table->string('document_status')->nullable();
        });
    }

    protected function createLoanDetailsTable(): void
    {
        Schema::connection('dwh')->create('fincloud_loan_details', function (Blueprint $table): void {
            $table->string('account_no')->primary();
            $table->string('cif_no');
            $table->string('location_id')->nullable();
            $table->string('alt_no')->nullable();
            $table->string('loan_agreement_no')->nullable();
            $table->string('document_status')->nullable();
        });
    }

    protected function createTimeDepositDetailsTable(): void
    {
        Schema::connection('dwh')->create('fincloud_time_deposit_details', function (Blueprint $table): void {
            $table->string('account_no')->primary();
            $table->string('cif_no');
            $table->string('location_id')->nullable();
            $table->string('certificate_no')->nullable();
            $table->string('document_status')->nullable();
        });
    }
}
