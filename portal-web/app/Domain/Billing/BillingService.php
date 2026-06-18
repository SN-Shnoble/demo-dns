<?php

namespace App\Domain\Billing;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingService
{
    /**
     * 获取用户余额信息
     */
    public function getBalance(string $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'user_id' => $userId,
            'balance_minor' => (int) ($user->balance_minor ?? 0),
            'currency' => $user->currency ?? 'CNY',
            'plan_code' => $user->plan_code ?? 'free',
            'status' => $user->status,
            'balance_updated_at' => $user->balance_updated_at?->toIso8601String(),
        ];
    }

    /**
     * 充值
     */
    public function charge(string $userId, int $amountMinor, string $description): array
    {
        return DB::transaction(function () use ($userId, $amountMinor, $description): array {
            $user = User::lockForUpdate()->findOrFail($userId);

            $before = (int) ($user->balance_minor ?? 0);
            $after = $before + $amountMinor;
            $currency = $user->currency ?? 'CNY';
            $now = now();

            $user->update([
                'balance_minor' => $after,
                'balance_updated_at' => $now,
            ]);

            $transactionId = DB::table('wallet_transactions')->insertGetId([
                'user_id' => $userId,
                'type' => 'charge',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'description' => $description,
                'status' => 'completed',
                'reference_type' => 'admin_manual',
                'reference_id' => null,
                'meta' => json_encode(['balance_before' => $before, 'balance_after' => $after], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $invoiceNo = 'INV-' . $now->format('YmdHis') . '-' . str_pad((string) $transactionId, 6, '0', STR_PAD_LEFT);
            $invoiceId = DB::table('invoices')->insertGetId([
                'user_id' => $userId,
                'invoice_no' => $invoiceNo,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => 'paid',
                'type' => 'charge',
                'description' => $description,
                'finalized' => true,
                'paid_at' => $now,
                'finalized_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'transaction_id' => (string) $transactionId,
                'invoice_id' => (string) $invoiceId,
                'invoice_no' => $invoiceNo,
                'type' => 'charge',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'status' => 'completed',
                'created_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * 退款
     */
    public function refund(string $userId, int $amountMinor, string $description): array
    {
        return DB::transaction(function () use ($userId, $amountMinor, $description): array {
            $user = User::lockForUpdate()->findOrFail($userId);

            $before = (int) ($user->balance_minor ?? 0);
            if ($before < $amountMinor) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'Insufficient balance for refund.',
                ]);
            }

            $after = $before - $amountMinor;
            $currency = $user->currency ?? 'CNY';
            $now = now();

            $user->update([
                'balance_minor' => $after,
                'balance_updated_at' => $now,
            ]);

            $transactionId = DB::table('wallet_transactions')->insertGetId([
                'user_id' => $userId,
                'type' => 'refund',
                'amount_minor' => -$amountMinor,
                'currency' => $currency,
                'description' => $description,
                'status' => 'completed',
                'reference_type' => 'admin_manual',
                'reference_id' => null,
                'meta' => json_encode(['balance_before' => $before, 'balance_after' => $after], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $invoiceNo = 'INV-' . $now->format('YmdHis') . '-R' . str_pad((string) $transactionId, 5, '0', STR_PAD_LEFT);
            $invoiceId = DB::table('invoices')->insertGetId([
                'user_id' => $userId,
                'invoice_no' => $invoiceNo,
                'amount_minor' => -$amountMinor,
                'currency' => $currency,
                'status' => 'paid',
                'type' => 'refund',
                'description' => $description,
                'finalized' => true,
                'paid_at' => $now,
                'finalized_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'transaction_id' => (string) $transactionId,
                'invoice_id' => (string) $invoiceId,
                'invoice_no' => $invoiceNo,
                'type' => 'refund',
                'amount_minor' => -$amountMinor,
                'currency' => $currency,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'status' => 'completed',
                'created_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * 账单历史
     */
    public function invoices(string $userId, int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('invoices')->orderByDesc('created_at');
        if ($userId !== '') {
            $query->where('user_id', $userId);
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($row): array => [
            'id' => (string) $row->id,
            'user_id' => $row->user_id,
            'invoice_no' => $row->invoice_no,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => $row->currency,
            'status' => $row->status,
            'type' => $row->type,
            'description' => $row->description,
            'finalized' => (bool) $row->finalized,
            'paid_at' => $row->paid_at,
            'finalized_at' => $row->finalized_at,
            'created_at' => $row->created_at,
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
}
