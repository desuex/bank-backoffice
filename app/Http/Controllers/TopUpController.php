<?php

namespace App\Http\Controllers;

use App\Http\Requests\TopUpRequest;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class TopUpController extends Controller
{
    public function store(TopUpRequest $request, LedgerService $ledgerService): JsonResponse
    {
        $data = $request->validated();

        /** @var Account $account */
        $account = Account::query()->whereKey($data['account_id'])->firstOrFail();

        $amountMinor = (int) $data['amount'];
        $currency    = strtoupper($data['currency_code']);
        $idempotency = Arr::get($data, 'idempotency_key');

        try {
            $txnId = $ledgerService->deposit(
                to: $account,
                amountMinor: $amountMinor,
                currency: $currency,
                idem: $idempotency
            );

            return response()->json([
                'status' => 'ok',
                'txn_id' => $txnId,
            ], 201);

        } catch (QueryException $e) {
            $sqlState = $e->errorInfo[0] ?? null;
            $code     = $e->errorInfo[1] ?? null;

            Log::warning('TopUp QueryException', ['sqlstate' => $sqlState, 'code' => $code, 'msg' => $e->getMessage()]);

            if ($sqlState === '23505') { //unique_violation
                return response()->json($this->problem(
                    status: 409,
                    title: 'Conflict',
                    detail: 'Duplicate request (idempotency conflict).'
                ), 409);
            }

            return response()->json($this->problem(
                status: 409,
                title: 'Conflict',
                detail: $this->detail($e)
            ), 409);

        } catch (\Throwable $e) {
            Log::error('TopUp failed', ['exception' => $e]);

            return response()->json($this->problem(
                status: 500,
                title: 'Internal Server Error',
                detail: $this->detail($e)
            ), 500);
        }
    }

    private function problem(int $status, string $title, ?string $detail = null): array
    {
        return array_filter([
            'status' => $status,
            'title'  => $title,
            'detail' => $detail,
        ], fn ($v) => $v !== null);
    }

    private function detail(\Throwable $e): ?string
    {
        return App::isProduction() ? null : $e->getMessage();
    }
}
