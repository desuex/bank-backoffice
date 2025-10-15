<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'EUR', 'exponent' => 2, 'name' => 'Euro'],
            ['code' => 'USD', 'exponent' => 2, 'name' => 'US Dollar'],
        ];

        foreach ($rows as $row) {
            DB::table('currencies')->updateOrInsert(['code' => $row['code']], $row);
        }
    }
}
