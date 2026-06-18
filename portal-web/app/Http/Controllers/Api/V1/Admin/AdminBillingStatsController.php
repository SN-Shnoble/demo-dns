<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\QueryLogIngestBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class AdminBillingStatsController
{
    public function overview(): JsonResponse
    {
        $totalUsers = User::count();

        // 从 query_log_ingest_batches 获取拦截统计
        $todayQueries = (int) QueryLogIngestBatch::where('received_at', '>=', now()->startOfDay())->sum('item_count');
        $totalQueries = (int) QueryLogIngestBatch::sum('item_count');
        $todayBatches = QueryLogIngestBatch::where('received_at', '>=', now()->startOfDay())->count();

        // 套餐分布（从用户 plan_code 统计）
        $planDistribution = User::selectRaw("COALESCE(plan_code, 'free') as plan, COUNT(*) as count")
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        // 模拟财务概览（从 subscription 等衍生）
        $freeUsers = $planDistribution['free'] ?? 0;
        $proUsers = ($planDistribution['pro'] ?? 0) + ($planDistribution['pro_monthly'] ?? 0) + ($planDistribution['pro_yearly'] ?? 0);
        $businessUsers = $planDistribution['business'] ?? 0;

        return response()->json([
            'data' => [
                'intercepts' => [
                    'today_queries' => $todayQueries,
                    'today_blocked' => 0,
                    'total_queries' => $totalQueries,
                    'total_blocked' => 0,
                    'today_batches' => $todayBatches,
                ],
                'usage' => [
                    'total_users' => $totalUsers,
                    'free_users' => $freeUsers,
                    'pro_users' => $proUsers,
                    'business_users' => $businessUsers,
                ],
                'billing' => [
                    'total_revenue_minor' => 0,
                    'pending_invoices' => 0,
                    'overdue_invoices' => 0,
                    'monthly_new_users' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'plans' => [
                    [
                        'code' => 'free',
                        'name' => 'Free',
                        'users' => $freeUsers,
                        'monthly_limit' => 300000,
                    ],
                    [
                        'code' => 'pro',
                        'name' => 'Pro',
                        'users' => $proUsers,
                        'monthly_limit' => null,
                    ],
                    [
                        'code' => 'business',
                        'name' => 'Business',
                        'users' => $businessUsers,
                        'monthly_limit' => null,
                    ],
                ],
            ],
        ]);
    }
}
