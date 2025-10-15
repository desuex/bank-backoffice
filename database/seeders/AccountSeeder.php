<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $systemEur = Account::firstOrCreate(
            ['code' => Account::SYSTEM_CASHIN_PREFIX . 'eur'],
            [
                'currency_code' => 'EUR',
                'is_system' => true,
            ]
        );

        $systemUsd = Account::firstOrCreate(
            ['code' => Account::SYSTEM_CASHIN_PREFIX . 'usd'],
            [
                'currency_code' => 'USD',
                'is_system' => true,
            ]
        );

    }
}
