<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTransferRequest;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TransferController extends Controller
{
    public function store(CreateTransferRequest $request, LedgerService $ledgerService): JsonResponse
    {
        $data = $request->validated();
        $amount = (int)$data['amount'];
        $currency = strtoupper($data['currency_code']);
        $idem = $request->header('Idempotency-Key')
            ?? Arr::get($data, 'idempotency_key');

        $from = Account::findOrFail($data['from_account_id']);
        $to = Account::findOrFail($data['to_account_id']);

        try {
            $txnId = $ledgerService->transfer(
                from: $from,
                to: $to,
                amountMinor: $amount,
                currency: $currency,
                idem: $idem
            );

            return response()->json([
                'status' => 'ok',
                'txn_id' => $txnId,
            ], 201);

        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Invalid transfer request.',
                'errors' => ['transfer' => [$this->detail($e)]],
            ], 422);

        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;

            Log::warning('Transfer query exception', ['sqlstate' => $sqlState, 'msg' => $e->getMessage()]);

            if ($sqlState === '23514') {
                return response()->json([
                    'message' => 'Transfer failed due to business rule violation.',
                    'errors' => ['balance' => ['Insufficient funds or constraint violation.']],
                ], 409);
            }

            if ($sqlState === '23505') {
                return response()->json([
                    'message' => 'Duplicate request.',
                    'errors' => ['idempotency' => ['This transfer was already processed.']],
                ], 409);
            }

            return response()->json([
                'message' => 'Transfer failed.',
                'errors' => ['db' => [$this->detail($e)]],
            ], 409);

        } catch (\Throwable $e) {
            Log::error('Transfer failed', ['exception' => $e]);

            return response()->json([
                'message' => 'Internal Server Error.',
                'errors' => ['exception' => [$this->detail($e)]],
            ], 500);
        }
    }

    private function detail(\Throwable $e): ?string
    {
        return App::isProduction() ? null : $e->getMessage();
    }
}
