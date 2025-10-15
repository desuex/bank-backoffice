<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferFundsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testHappyPathCreatesBalancedLedgerEntries()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->actingAs($alice);

        $from = $alice->accounts()->create([
            'code' => "user:{$alice->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $to = $bob->accounts()->create([
            'code' => "user:{$bob->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        DB::table('account_balances')->insert([
            'account_id' => $from->id,
            'balance_minor' => 5000, // 50.00 EUR
            'updated_at' => now(),
        ]);

        $payload = [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 1000, // 10.00 EUR
            'currency_code' => 'EUR',
        ];

        $res = $this->postJson(route('transfers.store'), $payload);

        $res->assertCreated()
            ->assertJsonStructure(['status', 'txn_id'])
            ->assertJson(['status' => 'ok']);

        $txnId = $res->json('txn_id');
        $this->assertNotEmpty($txnId);

        $this->assertDatabaseHas('ledger_entries', [
            'txn_id' => $txnId,
            'account_id' => $from->id,
            'amount_minor' => -1000,
            'currency_code' => 'EUR',
        ]);
        $this->assertDatabaseHas('ledger_entries', [
            'txn_id' => $txnId,
            'account_id' => $to->id,
            'amount_minor' => 1000,
            'currency_code' => 'EUR',
        ]);
        $this->assertSame(2, DB::table('ledger_entries')->where('txn_id', $txnId)->count());

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $from->id,
            'balance_minor' => 4000, // 5000 - 1000
        ]);
        $this->assertDatabaseHas('account_balances', [
            'account_id' => $to->id,
            'balance_minor' => 1000,
        ]);
    }

    public function testIdempotencyReturnsSameTxnAndDoesNotDoubleCharge()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice);

        $from = $alice->accounts()->create([
            'code' => "user:{$alice->id}:eur:1",
            'currency_code' => 'EUR',
        ]);
        $to = $bob->accounts()->create([
            'code' => "user:{$bob->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        DB::table('account_balances')->insert([
            'account_id' => $from->id,
            'balance_minor' => 5000,
            'updated_at' => now(),
        ]);

        $idem = (string)Str::uuid();

        $payload = [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 1000,
            'currency_code' => 'EUR',
            'idempotency_key' => $idem,
        ];

        $r1 = $this->postJson(route('transfers.store'), $payload, [
            'Idempotency-Key' => $idem,
        ])->assertCreated();

        $r2 = $this->postJson(route('transfers.store'), $payload, [
            'Idempotency-Key' => $idem,
        ])->assertCreated();

        $txn1 = $r1->json('txn_id');
        $txn2 = $r2->json('txn_id');
        $this->assertEquals($txn1, $txn2, 'Idempotent replays must return the same txn_id');

        $this->assertSame(2, DB::table('ledger_entries')->where('txn_id', $txn1)->count());

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $from->id,
            'balance_minor' => 4000,
        ]);
        $this->assertDatabaseHas('account_balances', [
            'account_id' => $to->id,
            'balance_minor' => 1000,
        ]);
    }

    public function testInsufficientFundsReturnsConflict409()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice);

        $from = $alice->accounts()->create([
            'code' => "user:{$alice->id}:eur:1",
            'currency_code' => 'EUR',
        ]);
        $to = $bob->accounts()->create([
            'code' => "user:{$bob->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $payload = [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 1000,
            'currency_code' => 'EUR',
        ];

        $this->postJson(route('transfers.store'), $payload)
            ->assertStatus(409)
            ->assertJson(['message' => 'Transfer failed due to business rule violation.']);
    }

    public function testZeroAndNegativeAmountsReturn422()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice);

        $from = $alice->accounts()->create([
            'code' => "user:{$alice->id}:eur:1",
            'currency_code' => 'EUR',
        ]);
        $to = $bob->accounts()->create([
            'code' => "user:{$bob->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $this->postJson(route('transfers.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 0,
            'currency_code' => 'EUR',
        ])->assertStatus(422);

        $this->postJson(route('transfers.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => -1,
            'currency_code' => 'EUR',
        ])->assertStatus(422);
    }

    public function testSameAccountTransferReturns422()
    {
        $alice = User::factory()->create();
        $this->actingAs($alice);

        $acct = $alice->accounts()->create([
            'code' => "user:{$alice->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $this->postJson(route('transfers.store'), [
            'from_account_id' => $acct->id,
            'to_account_id' => $acct->id,
            'amount' => 100,
            'currency_code' => 'EUR',
        ])->assertStatus(422);
    }

    public function testCurrencyMismatchReturns422()
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $this->actingAs($alice);

        $from = $alice->accounts()->create([
            'code' => "user:{$alice->id}:eur:1",
            'currency_code' => 'EUR',
        ]);
        $to = $bob->accounts()->create([
            'code' => "user:{$bob->id}:usd:1",
            'currency_code' => 'USD',
        ]);

        $this->postJson(route('transfers.store'), [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 100,
            'currency_code' => 'EUR',
        ])->assertStatus(422);
    }
}
