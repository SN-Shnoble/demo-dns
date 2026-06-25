<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Support\SystemConfigValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * UI.md #53 — 支付中心。
 *
 * V1: 仅支持 Stripe Checkout Session。
 * 支付金额来源: `Order.payable_amount_minor`，前端禁止直接支付金额。
 *
 * 流程: 支付先写 `payment_transactions` (pending) → Stripe 回调 →
 *       PaymentService.handleSuccess() → OrderService.markPaid()。
 */
final class PaymentService
{
    private const PAYMENT_METHOD_LABELS = [
        'card' => '信用卡',
        'wechat_pay' => '微信支付',
        'alipay' => '支付宝',
    ];

    /**
     * 创建一个支付会话 (Stripe Checkout Session)。
     *
     * 安全约束：
     *  - 当 Stripe SDK + secret 都可用时，必须真的调用 Stripe API 拿到真实 session。
     *    SDK 抛异常 → 抛回 RuntimeException，禁止 fallback 成占位 session。
     *  - 没有 SDK / secret 时：仅允许 fake 模式（仅在非 production）使用占位 session。
     */
    public function createCheckout(Order $order, ?string $paymentMethod = null): PaymentTransaction
    {
        $paymentMethodTypes = $this->paymentMethodsForCheckout($paymentMethod);
        $secret = $this->stripeSecret();
        $appUrl = rtrim((string) config('app.url'), '/');
        $successUrl = $appUrl . '/user/order?status=success&order_id=' . $order->id . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = $appUrl . '/user/order?status=cancel&order_id=' . $order->id;
        $useFake = $this->useFakeCheckout();

        if (app()->environment('production') && $useFake) {
            throw new RuntimeException('Fake Stripe checkout is forbidden in production.');
        }

        $existing = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('provider', 'stripe')
            ->where('status', PaymentTransaction::STATUS_PENDING)
            ->latest('id')
            ->first();
        $existingMethod = is_array($existing?->raw_payload)
            ? ($existing->raw_payload['payment_method'] ?? null)
            : null;
        if ($existing instanceof PaymentTransaction && ($paymentMethod === null || $existingMethod === $paymentMethod)) {
            return $existing;
        }

        $hasStripe = $secret !== '' && class_exists(\Stripe\StripeClient::class) && ! $useFake;
        if (! $hasStripe && ! $useFake) {
            // 没有 Stripe SDK 也未启用 fake：拒绝创建 pending 假流水
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET or enable STRIPE_FAKE for local dev.');
        }

        $sessionId = 'cs_test_' . Str::random(24);
        $redirectUrl = "https://checkout.stripe.com/c/pay/{$sessionId}";

        if ($hasStripe) {
            try {
                /** @var \Stripe\StripeClient $stripe */
                $stripe = new \Stripe\StripeClient($secret);
                $payload = [
                    'mode' => 'payment',
                    'payment_method_types' => $paymentMethodTypes,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower((string) $order->currency),
                            'unit_amount' => (int) $order->payable_amount_minor,
                            'product_data' => ['name' => 'Order #' . $order->order_no],
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'user_id' => (string) $order->user_id,
                    ],
                ];

                if (in_array('wechat_pay', $paymentMethodTypes, true)) {
                    $payload['payment_method_options'] = [
                        'wechat_pay' => ['client' => 'web'],
                    ];
                }

                $session = $stripe->checkout->sessions->create($payload);
                $sessionId = $session->id;
                $redirectUrl = (string) $session->url;
            } catch (\Throwable $e) {
                // Stripe API 失败：直接抛回，不创建占位 pending 交易
                throw new RuntimeException('Stripe checkout session creation failed: ' . $e->getMessage(), 0, $e);
            }
        }

        return PaymentTransaction::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_session_id' => $sessionId,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $order->payable_amount_minor,
            'currency' => $order->currency,
            'raw_payload' => [
                'redirect_url' => $redirectUrl,
                'payment_method' => $paymentMethodTypes[0] ?? 'card',
                'payment_method_types' => $paymentMethodTypes,
            ],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function paymentMethodOptions(): array
    {
        return array_map(
            fn (string $method): array => [
                'value' => $method,
                'label' => self::PAYMENT_METHOD_LABELS[$method] ?? $method,
            ],
            $this->configuredPaymentMethods(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function configuredPaymentMethods(): array
    {
        return $this->normalizePaymentMethods(
            SystemConfigValue::field('payment', 'payment_methods', ['card']),
        );
    }

    /**
     * @return array<int, string>
     */
    private function paymentMethodsForCheckout(?string $selected): array
    {
        $configured = $this->configuredPaymentMethods();
        $selected = is_string($selected) ? trim($selected) : '';

        if ($selected === '') {
            return $configured;
        }

        if (! in_array($selected, $configured, true)) {
            throw new RuntimeException('Selected payment method is not enabled.');
        }

        return [$selected];
    }

    /**
     * @param mixed $methods
     * @return array<int, string>
     */
    private function normalizePaymentMethods(mixed $methods): array
    {
        if (is_string($methods)) {
            $methods = array_map('trim', explode(',', $methods));
        }

        if (! is_array($methods)) {
            return ['card'];
        }

        $allowed = array_keys(self::PAYMENT_METHOD_LABELS);
        $normalized = [];
        foreach ($methods as $method) {
            $method = trim((string) $method);
            if ($method !== '' && in_array($method, $allowed, true)) {
                $normalized[] = $method;
            }
        }

        return array_values(array_unique($normalized)) ?: ['card'];
    }

    private function stripeSecret(): string
    {
        $configured = (string) SystemConfigValue::field('payment', 'secret_key', '');
        if ($configured !== '' && $configured !== '********') {
            return $configured;
        }

        return (string) config('services.stripe.secret', '');
    }

    public function stripePublishableKey(): string
    {
        $configured = (string) SystemConfigValue::field('payment', 'publishable_key', '');
        if ($configured !== '' && $configured !== '********') {
            return $configured;
        }

        return (string) config('services.stripe.publishable', '');
    }

    public function isFakeMode(): bool
    {
        return $this->useFakeCheckout();
    }

    private function useFakeCheckout(): bool
    {
        $configured = SystemConfigValue::field('payment', 'fake', null);
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('services.stripe.fake', false);
    }

    /**
     * Stripe webhook → success
     */
    public function handleSuccess(string $sessionId, ?string $paymentIntentId = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_session_id', $sessionId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx; // 幂等
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_SUCCESS,
            'provider_payment_intent_id' => $paymentIntentId,
            'updated_at' => Carbon::now(),
        ]);
        if ($tx->order_id) {
            (new OrderService())->markPaid((string) $tx->order_id, $paymentIntentId);
        }
        return $tx;
    }

    public function handleFailure(string $sessionId, ?string $reason = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_session_id', $sessionId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_FAILED,
            'failure_message' => $reason,
            'updated_at' => Carbon::now(),
        ]);
        return $tx;
    }

    /**
     * 用于 payment_intent.* 事件，ID 形如 pi_xxx。
     * 必须按 provider_payment_intent_id 查找，不能与 checkout session id (cs_xxx) 混用。
     */
    public function handleFailureByPaymentIntent(string $paymentIntentId, ?string $reason = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_payment_intent_id', $paymentIntentId)->first();
        if (! $tx instanceof PaymentTransaction) {
            // payment_intent 事件先于 webhook 落库的兜底：记录失败但不抛 500
            throw new \RuntimeException("No payment transaction found for payment_intent [{$paymentIntentId}]");
        }
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_FAILED,
            'failure_message' => $reason,
            'updated_at' => Carbon::now(),
        ]);
        return $tx;
    }

    /**
     * payment_intent.succeeded 事件成功处理（用于 Elements/二维码支付）
     */
    public function handleSuccessByPaymentIntent(string $paymentIntentId): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_payment_intent_id', $paymentIntentId)->first();
        if (! $tx instanceof PaymentTransaction) {
            throw new \RuntimeException("No payment transaction found for payment_intent [{$paymentIntentId}]");
        }
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_SUCCESS,
            'updated_at' => Carbon::now(),
        ]);
        if ($tx->order_id) {
            (new OrderService())->markPaid((string) $tx->order_id, $paymentIntentId);
        }
        return $tx;
    }

    public function refund(PaymentTransaction $tx): PaymentTransaction
    {
        if ($tx->status !== PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update(['status' => PaymentTransaction::STATUS_REFUNDED]);
        if ($tx->order_id) {
            (new OrderService())->markRefunded((string) $tx->order_id);
        }
        return $tx;
    }

    /**
     * 创建 PaymentIntent（用于 Stripe Elements 信用卡支付）。
     *
     * @return array{client_secret: string, payment_intent_id: string}
     */
    public function createPaymentIntent(Order $order): array
    {
        $secret = $this->stripeSecret();
        $useFake = $this->useFakeCheckout();

        if (app()->environment('production') && $useFake) {
            throw new RuntimeException('Fake Stripe is forbidden in production.');
        }

        $hasStripe = $secret !== '' && class_exists(\Stripe\StripeClient::class) && ! $useFake;
        if (! $hasStripe && ! $useFake) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $paymentIntentId = 'pi_test_' . Str::random(24);
        $clientSecret = $paymentIntentId . '_secret_' . Str::random(16);

        if ($hasStripe) {
            try {
                $stripe = new \Stripe\StripeClient($secret);
                $intent = $stripe->paymentIntents->create([
                    'amount' => (int) $order->payable_amount_minor,
                    'currency' => strtolower((string) $order->currency),
                    'payment_method_types' => ['card'],
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'user_id' => (string) $order->user_id,
                    ],
                ]);
                $paymentIntentId = $intent->id;
                $clientSecret = $intent->client_secret;
            } catch (\Throwable $e) {
                throw new RuntimeException('Stripe PaymentIntent creation failed: ' . $e->getMessage(), 0, $e);
            }
        }

        $tx = PaymentTransaction::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_session_id' => $paymentIntentId,
            'provider_payment_intent_id' => $paymentIntentId,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $order->payable_amount_minor,
            'currency' => $order->currency,
            'raw_payload' => [
                'payment_method' => 'card',
                'payment_intent_id' => $paymentIntentId,
                'client_secret' => $clientSecret,
            ],
        ]);

        return [
            'client_secret' => $clientSecret,
            'payment_intent_id' => $paymentIntentId,
            'payment_transaction_id' => (string) $tx->id,
        ];
    }

    /**
     * 创建微信/支付宝支付（返回二维码 URL 等信息）。
     *
     * @return array{qr_code_url: string, payment_intent_id: string, client_secret: string}
     */
    public function createQrPayment(Order $order, string $paymentMethod): array
    {
        $secret = $this->stripeSecret();
        $useFake = $this->useFakeCheckout();

        if (! in_array($paymentMethod, ['wechat_pay', 'alipay'], true)) {
            throw new RuntimeException('Invalid payment method for QR payment.');
        }

        if (app()->environment('production') && $useFake) {
            throw new RuntimeException('Fake Stripe is forbidden in production.');
        }

        $hasStripe = $secret !== '' && class_exists(\Stripe\StripeClient::class) && ! $useFake;
        if (! $hasStripe && ! $useFake) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $paymentIntentId = 'pi_test_' . Str::random(24);
        $clientSecret = $paymentIntentId . '_secret_' . Str::random(16);
        $qrCodeUrl = '';

        if ($hasStripe) {
            try {
                $stripe = new \Stripe\StripeClient($secret);

                $paymentIntentData = [
                    'amount' => (int) $order->payable_amount_minor,
                    'currency' => strtolower((string) $order->currency),
                    'payment_method_types' => [$paymentMethod],
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'user_id' => (string) $order->user_id,
                    ],
                ];

                if ($paymentMethod === 'wechat_pay') {
                    $paymentIntentData['payment_method_options'] = [
                        'wechat_pay' => ['client' => 'web'],
                    ];
                }

                $intent = $stripe->paymentIntents->create($paymentIntentData);
                $paymentIntentId = $intent->id;
                $clientSecret = $intent->client_secret;

                $qrCodeUrl = $intent->next_action?->wechat_pay_handle_qr_code?->qr_code_url
                    ?? $intent->next_action?->alipay_handle_redirect?->url
                    ?? '';
            } catch (\Throwable $e) {
                throw new RuntimeException('Stripe QR payment creation failed: ' . $e->getMessage(), 0, $e);
            }
        } else {
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=fake_' . $paymentMethod . '_' . $order->order_no;
        }

        $tx = PaymentTransaction::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_session_id' => $paymentIntentId,
            'provider_payment_intent_id' => $paymentIntentId,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $order->payable_amount_minor,
            'currency' => $order->currency,
            'raw_payload' => [
                'payment_method' => $paymentMethod,
                'payment_intent_id' => $paymentIntentId,
                'client_secret' => $clientSecret,
                'qr_code_url' => $qrCodeUrl,
            ],
        ]);

        return [
            'qr_code_url' => $qrCodeUrl,
            'payment_intent_id' => $paymentIntentId,
            'client_secret' => $clientSecret,
            'payment_transaction_id' => (string) $tx->id,
        ];
    }

    /**
     * 查询支付交易状态。
     */
    public function getTransactionStatus(string $transactionId): ?PaymentTransaction
    {
        return PaymentTransaction::find($transactionId);
    }

    /**
     * 使用钱包余额支付订单。
     *
     * @return array{status: string, balance_after_minor: int}
     */
    public function payWithWallet(Order $order): array
    {
        throw new \RuntimeException('Member orders must be paid through Stripe.');
    }
}
