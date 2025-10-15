<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("CREATE SEQUENCE IF NOT EXISTS account_seq");

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('txn_id');
            $table->uuid('account_id');
            $table->bigInteger('amount_minor');
            $table->string('currency_code');
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->string('idempotency_key');
            $table->bigInteger('account_seq')->default(DB::raw("nextval('account_seq')"));

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('currency_code')->references('code')->on('currencies');

            $table->unique(['idempotency_key']);
            $table->index(['account_id', 'effective_at']);
            $table->index(['txn_id']);
        });

        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT amount_nonzero CHECK (amount_minor <> 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        DB::statement("DROP SEQUENCE IF EXISTS account_seq");
    }
};
