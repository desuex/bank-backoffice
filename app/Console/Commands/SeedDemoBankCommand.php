<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SeedDemoBankCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:demo
        {--users=5 : Number of users to create}
        {--currencies=EUR : Comma-separated list of ISO codes (e.g., EUR,USD)}
        {--accounts-per-user=1 : Number of accounts per user per currency}
        {--balance=0 : Starting balance in minor units (per account)}
        {--reset : Drop all users/accounts/payment data first}
        {--quiet-emails : Use deterministic emails like user+1@example.test}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create demo users, accounts, system accounts, and optional starting balances';

    /**
     * Execute the console command.
     */
    public function handle(LedgerService $ledger): int
    {
        $usersCount = (int)$this->option('users');
        $currencies = collect(explode(',', (string)$this->option('currencies')))
            ->map(fn($c) => strtoupper(trim($c)))
            ->filter()->unique()->values();
        $acctPerUser = (int)$this->option('accounts-per-user');
        $startBalance = (int)$this->option('balance');
        $reset = (bool)$this->option('reset');
        $quietEmails = (bool)$this->option('quiet-emails');

        if ($usersCount < 0 || $acctPerUser < 1) {
            $this->error('Invalid options. --users must be >= 0; --accounts-per-user must be >= 1.');
            return self::FAILURE;
        }

        if ($reset) {
            $this->warn('Resetting demo data…');
            $this->resetDemoData();
        }

        DB::transaction(function () use ($currencies) {
            foreach ($currencies as $currency) {
                DB::table('currencies')->insertOrIgnore([['code' => $currency, 'name' => $currency, 'exponent' => 2]]);
            }
            DB::table('accounts')
                ->where('code', 'like', Account::SYSTEM_CASHIN_PREFIX . '%')
                ->update(['is_system' => true]);
        });

        $this->info("Creating {$usersCount} users * {$acctPerUser} account(s) per currency...");

        for ($i = 1; $i <= $usersCount; $i++) {
            $userAttrs = [
                'name' => 'Demo User ' . $i,
                'email' => $quietEmails ? "user+{$i}@example.test" : (fake()->unique()->safeEmail()),
                'age' => 25 + ($i % 15),
                'password' => bcrypt('secret'),
            ];
            /** @var User $user */
            $user = User::firstOrCreate(['email' => $userAttrs['email']], Arr::except($userAttrs, ['email']));

            foreach ($currencies as $currency) {
                for ($k = 1; $k <= $acctPerUser; $k++) {
                    $code = "user:{$user->id}:" . strtolower($currency) . ":{$k}";

                    /** @var Account $acct */
                    $acct = $user->accounts()->firstOrCreate(
                        ['code' => $code],
                        ['currency_code' => $currency]
                    );

                    if ($startBalance > 0) {
                        $idem = "demo:seed:{$acct->id}:{$currency}:{$startBalance}";
                        try {
                            $ledger->deposit(
                                to: $acct,
                                amountMinor: $startBalance,
                                currency: $currency,
                                idem: $idem
                            );
                        } catch (\Throwable $e) {
                            $this->warn("Deposit skipped or failed for {$code}: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $this->line('');
        $this->info('Done');

        return self::SUCCESS;
    }

    private function resetDemoData(): void
    {
        DB::statement('TRUNCATE TABLE ledger_entries RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE account_balances RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE payment_intents RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE idempotency_keys RESTART IDENTITY CASCADE');

        DB::table('accounts')->where('is_system', false)->delete();

        DB::table('users')->delete();
    }
}
