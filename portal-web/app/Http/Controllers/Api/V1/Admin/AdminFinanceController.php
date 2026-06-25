<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin Finance controller (balances, recharges, bills, refunds).
 */
final class AdminFinanceController
{
    /** GET /admin/finance/balances — all user balances (SSOT: `dns_wallets`) */
    public function balances(): JsonResponse
    {
        // SSOT 余额在 `dns_wallets` 表
        $prefix = DB::getTablePrefix();
        $rows = DB::table('users as u')
            ->leftJoin('wallets as w', 'w.user_id', '=', 'u.uid')
            ->select([
                'u.uid as id',
                'u.username',
                'u.email',
                'u.plan_code',
                'u.status',
                DB::raw("COALESCE({$prefix}w.balance_minor, 0) as balance_minor"),
                DB::raw("COALESCE({$prefix}w.currency, 'USD') as currency"),
                DB::raw("{$prefix}w.updated_at as balance_updated_at"),
                'u.created_at',
            ])
            ->orderBy('u.created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** GET /admin/finance/recharges */
    public function recharges(Request $request): JsonResponse
    {
        return response()->json($this->listTransactions($request, 'credit', ['topup', 'manual']));
    }

    /** GET /admin/finance/recharges/export */
    public function rechargeExport(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exportTransactions($request, 'credit', ['topup', 'manual'])]);
    }

    /** GET /admin/finance/bills */
    public function bills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $service = new \App\Domain\Billing\BillingService();
        $result = $service->bills(
            $validated['user_id'] ?? '',
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 20),
            $validated['status'] ?? '',
        );

        return response()->json($result);
    }

    /** GET /admin/finance/bills/export */
    public function billExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $service = new \App\Domain\Billing\BillingService();
        $result = $service->bills($validated['user_id'] ?? '', 1, (int) ($validated['limit'] ?? 1000), $validated['status'] ?? '');

        return response()->json(['data' => $result['data'] ?? []]);
    }

    /** GET /admin/finance/refunds */
    public function refunds(Request $request): JsonResponse
    {
        return response()->json($this->listTransactions($request, 'refund'));
    }

    /** POST /admin/finance/refunds/{id}/approve */
    public function approveRefund(string $id): JsonResponse
    {
        $refund = DB::table('wallet_transactions')->where('id', $id)->where('type', 'refund')->first();
        if ($refund === null) {
            abort(404, 'Refund not found.');
        }

        if ($refund->status !== 'pending') {
            return response()->json([
                'data' => [
                    'id' => (string) $refund->id,
                    'status' => $refund->status,
                    'approved' => false,
                ],
            ]);
        }

        DB::table('wallet_transactions')->where('id', $id)->update([
            'status' => 'succeeded',
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'status' => 'succeeded',
                'approved' => true,
            ],
        ]);
    }

    /** GET /admin/finance/refunds/export */
    public function refundExport(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exportTransactions($request, 'refund')]);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function listTransactions(Request $request, string $type, string|array|null $source = null): array
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $query = DB::table('wallet_transactions as wt')
            ->leftJoin('users as u', 'u.uid', '=', 'wt.user_id')
            ->where('wt.type', $type)
            ->orderByDesc('wt.created_at')
            ->select([
                'wt.*',
                'u.username as user_name',
                'u.email as user_email',
            ]);
        if ($source !== null) {
            if (is_array($source)) {
                $query->whereIn('wt.source', $source);
            } else {
                $query->where('wt.source', $source);
            }
        }
        if (! empty($validated['user_id'])) {
            $query->where('wt.user_id', $validated['user_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('wt.status', $validated['status']);
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($row): array => [
            'id' => (string) $row->id,
            'user_id' => $row->user_id,
            'username' => $row->user_name,
            'user_name' => $row->user_name,
            'user_email' => $row->user_email,
            'type' => $row->type,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => $row->currency,
            'description' => $row->description,
            'status' => $row->status,
            'source' => $row->source,
            'payment_method' => $row->source === 'manual' ? 'admin' : $row->source,
            'billing_id' => $row->billing_id,
            'transaction_no' => $row->transaction_no,
            'transaction_id' => $row->transaction_no ?: (string) $row->id,
            'balance_after_minor' => (int) $row->balance_after_minor,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportTransactions(Request $request, string $type, string|array|null $source = null): array
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = DB::table('wallet_transactions as wt')
            ->leftJoin('users as u', 'u.uid', '=', 'wt.user_id')
            ->where('wt.type', $type)
            ->orderByDesc('wt.created_at')
            ->select([
                'wt.*',
                'u.username as user_name',
                'u.email as user_email',
            ]);
        if ($source !== null) {
            if (is_array($source)) {
                $query->whereIn('wt.source', $source);
            } else {
                $query->where('wt.source', $source);
            }
        }
        if (! empty($validated['user_id'])) {
            $query->where('wt.user_id', $validated['user_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('wt.status', $validated['status']);
        }

        return $query->limit((int) ($validated['limit'] ?? 1000))->get()->map(fn ($row): array => [
            'id' => (string) $row->id,
            'user_id' => $row->user_id,
            'username' => $row->user_name,
            'user_name' => $row->user_name,
            'user_email' => $row->user_email,
            'type' => $row->type,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => $row->currency,
            'description' => $row->description,
            'status' => $row->status,
            'source' => $row->source,
            'payment_method' => $row->source === 'manual' ? 'admin' : $row->source,
            'billing_id' => $row->billing_id,
            'transaction_no' => $row->transaction_no,
            'transaction_id' => $row->transaction_no ?: (string) $row->id,
            'balance_after_minor' => (int) $row->balance_after_minor,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();
    }
}
