<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LedgerEntry;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LedgerService
{
    /**
     * Post a deposit from the system cash-in account to a user account.
     * Returns the txn_id.
     *
     * @throws InvalidArgumentException|RuntimeException|Throwable
     */
    public function deposit(Account $to, int $amountMinor, string $currency, ?string $idem = null): string
    {
        $currency = strtoupper($currency);

        $system = $this->getSystemCashInAccount($currency);
        $this->assertTransferIsValid($system, $to, $amountMinor, $currency);

        return $this->performTransaction($idem, $system, $to, $amountMinor, $currency);
    }

    /**
     * Transfer between two user accounts
     * Returns the txn_id.
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function transfer(Account $from, Account $to, int $amountMinor, string $currency, ?string $idem = null): string
    {
        $currency = strtoupper($currency);

        if ($from->is_system) {
            throw new InvalidArgumentException('System accounts cannot be used as the source for user transfers.');
        }

        $this->assertTransferIsValid($from, $to, $amountMinor, $currency);

        return $this->performTransaction($idem, $from, $to, $amountMinor, $currency);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function assertTransferIsValid(Account $from, Account $to, int $amountMinor, string $currency): void
    {
        if ($from->id === $to->id) {
            throw new InvalidArgumentException('Cannot transfer to the same account.');
        }
        if ($from->currency_code !== $currency || $to->currency_code !== $currency) {
            throw new InvalidArgumentException('Currency mismatch.');
        }
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Amount must be a positive integer (minor units).');
        }
    }

    /**
     * @throws RuntimeException
     */
    protected function getSystemCashInAccount(string $currency): Account
    {
        $acct = Account::where('code', Account::SYSTEM_CASHIN_PREFIX . strtolower($currency))->first();

        if (!$acct) {
            throw new RuntimeException("Missing system cash-in account for {$currency}.");
        }
        if (!$acct->is_system) {
            throw new RuntimeException("Cash-in account for {$currency} is not marked as system.");
        }

        return $acct;
    }

    /**
     * Idempotency gate: insert-first to avoid races.
     * - If key is new: create a new txn_id, run producer.
     * - If key exists: return its txn_id.
     * - If the producer fails: delete the key.
     *
     * @throws Throwable
     */
    private function guardIdempotency(?string $idem, Closure $producer): string
    {
        $key = $idem ?: (string)Str::uuid();
        $txnId = (string)Str::uuid();

        return DB::transaction(function () use ($key, $txnId, $producer) {

            $reserved = DB::selectOne(
                'INSERT INTO idempotency_keys ("key","txn_id","created_at")
             VALUES (?, ?, now())
             ON CONFLICT ("key") DO NOTHING
             RETURNING "txn_id"',
                [$key, $txnId]
            );

            if ($reserved && ($reserved->txn_id === $txnId)) {
                $producer($txnId);
                return $txnId;
            }

            $existing = DB::table('idempotency_keys')->where('key', $key)->value('txn_id');

            return $existing ? (string)$existing : $txnId;
        }, 3);
    }

    /**
     * @throws Throwable
     */
    private function performTransaction(?string $idem, Account $from, Account $to, int $amountMinor, string $currency): string
    {
        return $this->guardIdempotency($idem, function (string $txnId) use ($from, $to, $amountMinor, $currency) {
            $eff = now()->toImmutable()->utc();

            LedgerEntry::create([
                'txn_id' => $txnId,
                'account_id' => $from->id,
                'amount_minor' => -$amountMinor,
                'currency_code' => $currency,
                'effective_at' => $eff,
                'idempotency_key' => $txnId . ':debit',
            ]);

            LedgerEntry::create([
                'txn_id' => $txnId,
                'account_id' => $to->id,
                'amount_minor' => $amountMinor,
                'currency_code' => $currency,
                'effective_at' => $eff,
                'idempotency_key' => $txnId . ':credit',
            ]);
        });
    }
}
