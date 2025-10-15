<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TopUpTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;
    protected string $seeder = DatabaseSeeder::class;

    public function testTopUpAndCreateLedgerEntry()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = $user->accounts()->create([
            'code' => "user:$user->id:eur:1",
            'currency_code' => 'EUR'
        ]);
        $system = Account::where('code', Account::SYSTEM_CASHIN_PREFIX . 'eur')->firstOrFail();

        $payload = [
            'account_id' => $account->id,
            'email' => $user->email,
            'amount' => 1000, // 10.00 EUR in minor
            'currency_code' => 'EUR',
        ];
        $response = $this->post(route('top-up.store'), $payload);

        $response->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['status', 'txn_id'])
            ->assertJson(['status' => 'ok']);

        $txnId = $response->json('txn_id');
        $this->assertNotEmpty($txnId);

        $this->assertDatabaseHas('ledger_entries', [
            'txn_id' => $txnId,
            'account_id' => $system->id,
            'amount_minor' => -1000,
            'currency_code' => 'EUR',
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'txn_id' => $txnId,
            'account_id' => $account->id,
            'amount_minor' => 1000,
            'currency_code' => 'EUR',
        ]);

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $system->id,
            'balance_minor' => -1000,
        ]);

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $account->id,
            'balance_minor' => 1000,
        ]);
    }

    public function testIsIdempotentWithSameKeyAndDoesntCreateDoubleCredit()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $system = Account::where('code', Account::SYSTEM_CASHIN_PREFIX . 'eur')->firstOrFail();
        /** @var Account $account */
        $account = $user->accounts()->create([
            'code' => "user:{$user->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $idem = (string)Str::uuid();

        $payload = [
            'account_id' => $account->id,
            'email' => $user->email,
            'amount' => 1000,
            'currency_code' => 'EUR',
            'idempotency_key' => $idem,
        ];

        $r1 = $this->postJson(route('top-up.store'), $payload, [
            'Idempotency-Key' => $idem,
        ])->assertCreated();

        $txn1 = $r1->json('txn_id');
        $this->assertNotEmpty($txn1);

        $r2 = $this->postJson(route('top-up.store'), $payload, [
            'Idempotency-Key' => $idem,
        ])->assertCreated();

        $txn2 = $r2->json('txn_id');
        $this->assertEquals($txn1, $txn2, 'Idempotent replay should return same txn_id');

        $this->assertDatabaseHas('account_balances', [
            'account_id' => $system->id,
            'balance_minor' => -1000,
        ]);
        $this->assertDatabaseHas('account_balances', [
            'account_id' => $account->id,
            'balance_minor' => 1000,
        ]);

        $this->assertSame(2, \DB::table('ledger_entries')->where('txn_id', $txn1)->count());
    }

    public function testShouldRejectZeroOrNegativeAmount()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = $user->accounts()->create([
            'code' => "user:{$user->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $this->postJson(route('top-up.store'), [
            'account_id' => $account->id,
            'email' => $user->email,
            'amount' => 0,
            'currency_code' => 'EUR',
        ])->assertStatus(422);

        $this->postJson(route('top-up.store'), [
            'account_id' => $account->id,
            'email' => $user->email,
            'amount' => -1,
            'currency_code' => 'EUR',
        ])->assertStatus(422);
    }

    public function testShouldRejectInvalidCurrency()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $userEur = $user->accounts()->create([
            'code' => "user:{$user->id}:eur:1",
            'currency_code' => 'EUR',
        ]);

        $this->postJson(route('top-up.store'), [
            'account_id' => $userEur->id,
            'email' => $user->email,
            'amount' => 1000,
            'currency_code' => 'USD',
        ])->assertStatus(422);
    }
}
