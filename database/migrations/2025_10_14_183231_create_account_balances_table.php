<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_balances', function (Blueprint $table) {
            $table->uuid('account_id')->primary();
            $table->bigInteger('balance_minor')->default(0);
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('account_id')
                ->references('id')->on('accounts')
                ->cascadeOnDelete();
        });

        DB::unprepared(<<<'SQL'
        CREATE OR REPLACE FUNCTION fn_apply_ledger_to_balance()
        RETURNS trigger
        LANGUAGE plpgsql
        AS $$
        DECLARE
          new_balance bigint;
          is_sys boolean := false;
        BEGIN
          INSERT INTO account_balances (account_id, balance_minor, updated_at)
          VALUES (NEW.account_id, NEW.amount_minor, now())
          ON CONFLICT (account_id) DO UPDATE
            SET balance_minor = account_balances.balance_minor + EXCLUDED.balance_minor,
                updated_at    = now()
          RETURNING account_balances.balance_minor INTO new_balance;

          SELECT COALESCE(a.is_system, false) INTO is_sys
          FROM accounts a
          WHERE a.id = NEW.account_id;

          IF is_sys = false AND new_balance < 0 THEN
            RAISE EXCEPTION 'Insufficient funds for account %, would become % (minor units)',
              NEW.account_id, new_balance
              USING ERRCODE = '23514';
          END IF;

          RETURN NEW;
        END
        $$;
        SQL);

        DB::unprepared(<<<'SQL'
        CREATE TRIGGER trg_apply_balance
        AFTER INSERT ON ledger_entries
        FOR EACH ROW
        EXECUTE FUNCTION fn_apply_ledger_to_balance();
        SQL);

    }

    public function down(): void
    {
        DB::statement("DROP TRIGGER IF EXISTS trg_apply_balance ON ledger_entries;");
        DB::statement("DROP FUNCTION IF EXISTS fn_apply_ledger_to_balance();");
        Schema::dropIfExists('account_balances');
    }
};
