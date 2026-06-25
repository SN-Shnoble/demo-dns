<template>
    <Layout>
    <div class="account-page">
        <div class="page-header">
            <h1 class="page-title">{{ $t('account.title') }}</h1>
            <p class="page-desc">{{ $t('account.desc') }}</p>
        </div>

        <div class="account-grid">
            <!-- 免费额度 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Coin /></el-icon>
                    <h3>{{ $t('account.quota.title') }}</h3>
                </div>
                <div class="card-body">
                    <p class="quota-desc">{{ $t('account.quota.desc') }}</p>
                    <div class="quota-bar">
                        <el-progress
                            :percentage="quotaPercentage"
                            :stroke-width="12"
                            :color="quotaColor"
                        />
                        <div class="quota-text">
                            <span>{{ $t('account.quota.used', { used: usageUsedLabel, total: usageTotalLabel }) }}</span>
                            <span v-if="usageData.is_unlimited" class="quota-unlimited">{{ $t('account.quota.unlimited') }}</span>
                        </div>
                    </div>
                    <div v-if="!usageData.is_unlimited" class="quota-upgrade">
                        <p>{{ $t('account.quota.upgrade', { price: usageData.upgrade_price }) }}</p>
                    </div>
                </div>
            </div>

            <!-- 余额 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Wallet /></el-icon>
                    <h3>{{ $t('account.balance.title') }}</h3>
                </div>
                <div class="card-body">
                    <div class="balance-info">
                        <div class="balance-item">
                            <span class="balance-label">{{ $t('account.balance.available') }}</span>
                            <span class="balance-value">US${{ walletBalance.balance }}</span>
                        </div>
                        <p class="balance-note">{{ $t('account.balance.note') }}</p>
                    </div>
                    <div class="balance-actions">
                        <el-button @click="showRechargeDialog = true">{{ $t('account.balance.recharge') }}</el-button>
                    </div>
                </div>
            </div>

            <!-- 订阅 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Tickets /></el-icon>
                    <h3>{{ $t('account.subscription.title') }}</h3>
                </div>
                <div class="card-body">
                    <div v-if="currentSubscription" class="subscription-info">
                        <div class="sub-item">
                            <span class="sub-label">{{ $t('account.subscription.plan') }}</span>
                            <span class="sub-value">{{ currentSubscription.plan_name }}</span>
                        </div>
                        <div class="sub-item">
                            <span class="sub-label">{{ $t('account.subscription.status') }}</span>
                            <el-tag :type="getStatusType(currentSubscription.status)">
                                {{ getStatusLabel(currentSubscription.status) }}
                            </el-tag>
                        </div>
                        <div class="sub-item">
                            <span class="sub-label">{{ $t('account.subscription.expiresAt') }}</span>
                            <span class="sub-value">{{ formatDate(currentSubscription.expires_at) }}</span>
                        </div>
                    </div>
                    <div v-else class="no-subscription">
                        <p>{{ $t('account.subscription.none') }}</p>
                    </div>
                    <el-button type="primary" class="subscribe-btn" @click="openSubscribeDialog">
                        {{ $t('account.subscription.subscribeBtn') }}
                    </el-button>
                </div>
            </div>

            <!-- 电子邮件地址 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Message /></el-icon>
                    <h3>{{ $t('account.email.title') }}</h3>
                </div>
                <div class="card-body">
                    <div class="setting-row">
                        <div class="setting-info">
                            <p class="setting-desc">{{ $t('account.email.desc') }}</p>
                            <p class="setting-value">{{ userInfo.email }}</p>
                        </div>
                        <el-button @click="showEmailDialog = true">{{ $t('common.edit') }}</el-button>
                    </div>
                </div>
            </div>

            <!-- 密码 -->
            <div class="card">
                <div class="card-header">
                    <el-icon class="card-icon"><Lock /></el-icon>
                    <h3>{{ $t('account.password.title') }}</h3>
                </div>
                <div class="card-body">
                    <div class="setting-row">
                        <div class="setting-info">
                            <p class="setting-desc">{{ $t('account.password.desc') }}</p>
                        </div>
                        <el-button @click="showPasswordDialog = true">{{ $t('common.change') }}</el-button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 充值弹窗 -->
        <el-dialog v-model="showRechargeDialog" :title="$t('account.balance.recharge')" width="400px">
            <el-form :model="rechargeForm" label-position="top">
                <el-form-item :label="$t('account.recharge.amount')">
                    <el-input-number v-model="rechargeForm.amount" :min="1" :step="1" />
                </el-form-item>
                <el-form-item label="支付方式">
                    <el-radio-group v-model="selectedPaymentMethod" class="pay-method-group">
                        <el-radio
                            v-for="method in paymentMethods"
                            :key="method.value"
                            :value="method.value"
                            border
                            class="pay-method-option"
                        >
                            {{ method.label }}
                        </el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showRechargeDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="recharging" @click="handleRecharge">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 订阅套餐弹窗 -->
        <el-dialog v-model="showSubscribeDialog" :title="$t('account.subscription.subscribeTitle') || '选择套餐'" width="800px" top="6vh">
            <div v-if="selectedPlan" class="subscribe-summary">
                <div class="summary-row">
                    <span class="summary-label">{{ $t('account.subscription.summaryPlan') || '套餐' }}</span>
                    <span class="summary-value">{{ selectedPlan.name }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">{{ $t('account.subscription.summaryCycle') || '计费周期' }}</span>
                    <span class="summary-value">{{ billingCycleLabel(selectedBillingCycle) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">{{ $t('account.subscription.summaryTotal') || '应付总额' }}</span>
                    <span class="summary-value summary-amount">{{ summaryAmount }}</span>
                </div>
            </div>

            <div class="subscribe-plans">
                <div
                    v-for="plan in plans"
                    :key="plan.code"
                    class="plan-option"
                    :class="{ selected: selectedPlan?.code === plan.code, current: plan.code === currentPlanCode }"
                    @click="selectPlan(plan)"
                >
                    <div class="plan-header">
                        <span class="plan-name">{{ plan.name }}</span>
                        <el-tag v-if="plan.code === currentPlanCode" type="success" size="small">
                            {{ $t('account.subscription.current') || '当前' }}
                        </el-tag>
                        <el-tag v-else-if="plan.is_featured" type="primary" size="small">
                            {{ $t('account.subscription.recommended') || '推荐' }}
                        </el-tag>
                    </div>
                    <div class="plan-price">{{ formatPrice(plan) }}</div>
                    <ul class="plan-features">
                        <li v-for="feature in (plan.features || [])" :key="feature">{{ feature }}</li>
                    </ul>
                </div>
            </div>
            <div v-if="selectedPlan" class="billing-cycle-section">
                <h4>{{ $t('account.subscription.billingCycle') || '计费周期' }}</h4>
                <el-radio-group v-model="selectedBillingCycle">
                    <el-radio
                        v-for="price in activePrices(selectedPlan)"
                        :key="price.billing_cycle"
                        :value="price.billing_cycle"
                        border
                        class="billing-option"
                    >
                        <span class="billing-label">{{ billingCycleLabel(price.billing_cycle) }}</span>
                        <span class="billing-price">{{ money(price.amount_minor, price.currency) }}</span>
                    </el-radio>
                </el-radio-group>
            </div>
            <template #footer>
                <el-button @click="showSubscribeDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="subscribing"
                    :disabled="!selectedPlan || selectedPlan.code === currentPlanCode"
                    @click="handleSubscribe"
                >
                    {{ $t('account.subscription.confirmSubscribe') || '确认订阅' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 在线支付弹窗 (Stripe) -->
        <el-dialog v-model="showPayDialog" :title="$t('account.pay.title') || '在线支付'" width="520px" top="8vh" :close-on-click-modal="false" :before-close="cancelPay">
            <div v-if="pendingOrder" class="pay-summary">
                <p class="pay-tip">{{ $t('account.pay.tip') || '请完成支付，支付成功后将自动激活套餐。' }}</p>
                <div class="pay-row">
                    <span class="pay-label">{{ $t('account.pay.orderNo') || '订单号' }}</span>
                    <span class="pay-value">{{ pendingOrder.order_no }}</span>
                </div>
                <div class="pay-row">
                    <span class="pay-label">{{ $t('account.pay.amount') || '应付金额' }}</span>
                    <span class="pay-value pay-amount">{{ pendingOrder.amount_label }}</span>
                </div>
                <div class="pay-methods">
                    <div class="pay-method-label">支付方式</div>
                    <el-radio-group v-model="selectedPaymentMethod" class="pay-method-group" @change="handlePaymentMethodChange">
                        <el-radio
                            v-for="method in paymentMethods"
                            :key="method.value"
                            :value="method.value"
                            border
                            class="pay-method-option"
                        >
                            {{ method.label }}
                        </el-radio>
                    </el-radio-group>
                </div>

                <!-- 信用卡支付表单 -->
                <div v-if="selectedPaymentMethod === 'card' && payStep === 'form'" class="card-form-section">
                    <div class="form-label">信用卡信息</div>
                    <div ref="cardElementRef" class="stripe-card-element"></div>
                    <p v-if="cardError" class="card-error">{{ cardError }}</p>
                </div>

                <!-- 二维码支付 -->
                <div v-if="(selectedPaymentMethod === 'wechat_pay' || selectedPaymentMethod === 'alipay') && payStep === 'qrcode'" class="qr-section">
                    <div class="qr-container">
                        <img v-if="qrCodeUrl" :src="qrCodeUrl" class="qr-image" alt="支付二维码" />
                        <div v-else class="qr-loading">
                            <el-icon class="is-loading"><Loading /></el-icon>
                            <span>正在生成二维码...</span>
                        </div>
                    </div>
                    <p class="qr-tip">请使用{{ selectedPaymentMethod === 'wechat_pay' ? '微信' : '支付宝' }}扫码支付</p>
                    <p class="qr-status">
                        <el-icon><Refresh /></el-icon>
                        <span>等待支付中... 请在手机上完成支付</span>
                    </p>
                    <p v-if="isStripeFake" class="qr-test-hint">
                        <el-icon><InfoFilled /></el-icon>
                        <span>测试模式</span>
                    </p>
                    <el-button
                        v-if="isStripeFake"
                        type="primary"
                        size="default"
                        class="mock-pay-btn"
                        @click="mockQrPaySuccess"
                    >
                        模拟支付成功（测试）
                    </el-button>
                </div>

                <!-- 余额支付 -->
                <div v-if="selectedPaymentMethod === 'wallet' && payStep === 'select'" class="wallet-section">
                    <div class="wallet-info">
                        <div class="wallet-label">当前余额</div>
                        <div class="wallet-amount">{{ walletBalanceLabel }}</div>
                    </div>
                    <div v-if="walletInsufficient" class="wallet-insufficient">
                        <el-icon><Warning /></el-icon>
                        <span>余额不足，请先充值或选择其他支付方式</span>
                    </div>
                </div>

                <!-- 支付成功 -->
                <div v-if="payStep === 'success'" class="pay-success">
                    <el-icon class="success-icon"><CircleCheck /></el-icon>
                    <p class="success-text">支付成功！</p>
                    <p class="success-desc">订单已确认，页面即将刷新...</p>
                </div>
            </div>
            <template #footer>
                <el-button v-if="payStep !== 'success'" @click="handleBackOrCancel">
                    {{ payStep === 'form' || payStep === 'qrcode' ? '返回' : $t('common.cancel') }}
                </el-button>
                <el-button
                    v-if="payStep === 'select' && selectedPaymentMethod === 'wallet'"
                    type="primary"
                    :loading="paying"
                    :disabled="walletInsufficient"
                    @click="confirmWalletPay"
                >
                    {{ paying ? '支付中...' : '确认支付' }}
                </el-button>
                <el-button
                    v-if="payStep === 'select' && selectedPaymentMethod !== 'wallet'"
                    type="primary"
                    :loading="paying"
                    @click="confirmPay"
                >
                    {{ $t('account.pay.goPay') || '确认支付' }}
                </el-button>
                <el-button
                    v-if="selectedPaymentMethod === 'card' && payStep === 'form'"
                    type="primary"
                    :loading="paying"
                    :disabled="!stripeReady"
                    @click="confirmCardPay"
                >
                    {{ paying ? '支付中...' : '确认支付' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 修改邮箱弹窗 -->
        <el-dialog v-model="showEmailDialog" :title="$t('account.email.title')" width="400px">
            <el-form :model="emailForm" label-position="top">
                <el-form-item :label="$t('account.email.newEmail')">
                    <el-input v-model="emailForm.email" type="email" />
                </el-form-item>
                <el-form-item :label="$t('account.email.password')">
                    <el-input v-model="emailForm.password" type="password" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEmailDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="updatingEmail" @click="handleUpdateEmail">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 修改密码弹窗 -->
        <el-dialog v-model="showPasswordDialog" :title="$t('account.password.title')" width="400px">
            <el-form :model="passwordForm" label-position="top">
                <el-form-item :label="$t('account.password.current')">
                    <el-input v-model="passwordForm.currentPassword" type="password" />
                </el-form-item>
                <el-form-item :label="$t('account.password.new')">
                    <el-input v-model="passwordForm.newPassword" type="password" />
                </el-form-item>
                <el-form-item :label="$t('account.password.confirm')">
                    <el-input v-model="passwordForm.confirmPassword" type="password" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPasswordDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="updatingPassword" @click="handleUpdatePassword">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
    </Layout>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Coin, Wallet, Tickets, Message, Lock, Loading, Refresh, CircleCheck, Warning, InfoFilled } from '@element-plus/icons-vue'
import { loadStripe } from '@stripe/stripe-js'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

// 加载状态
const loading = ref(false)

// 用户信息
const userInfo = ref({
    email: '',
    username: ''
})

// 使用量数据
const usageData = ref({
    queries_used: 0,
    queries_total: 300000,
    is_unlimited: false,
    upgrade_price: 'US$3.99',
    quota_status: 'normal',
    plan_code: 'free'
})

// 钱包余额
const walletBalance = ref({
    balance: '0.00'
})

// 当前订阅
const currentSubscription = ref(null)

// 弹窗状态
const showRechargeDialog = ref(false)
const showEmailDialog = ref(false)
const showPasswordDialog = ref(false)
const showSubscribeDialog = ref(false)
const showPayDialog = ref(false)
const recharging = ref(false)
const updatingEmail = ref(false)
const updatingPassword = ref(false)
const subscribing = ref(false)
const paying = ref(false)

// 支付相关
const pendingOrder = ref(null)
const paymentMethods = ref([{ value: 'wallet', label: '余额支付' }, { value: 'card', label: '信用卡' }])
const selectedPaymentMethod = ref('wallet')
const payStep = ref('select')
const cardElementRef = ref(null)
const stripeReady = ref(false)
const cardError = ref('')
const qrCodeUrl = ref('')
const paymentTransactionId = ref('')
const isStripeFake = ref(false)
let stripeInstance = null
let cardElement = null
let currentClientSecret = ''
let paymentPollTimer = null

const walletInsufficient = computed(() => {
    if (!pendingOrder.value) return false
    if (!billingData.value?.balance_minor) return true
    return Number(billingData.value.balance_minor) < Number(pendingOrder.value.payable_amount_minor)
})

const walletBalanceLabel = computed(() => {
    if (!billingData.value?.balance_minor) return '$0.00'
    return money(billingData.value.balance_minor, billingData.value.currency || 'USD')
})

// 套餐相关
const currentPlanCode = ref('free')
const plans = ref([])
const selectedPlan = ref(null)
const selectedBillingCycle = ref('monthly')

// 表单数据
const rechargeForm = ref({ amount: 10 })
const emailForm = ref({ email: '', password: '' })
const passwordForm = ref({ currentPassword: '', newPassword: '', confirmPassword: '' })

// 计算配额百分比
const quotaPercentage = computed(() => {
    if (usageData.value.is_unlimited) return 0
    const total = Number(usageData.value.queries_total || 0)
    if (total <= 0) return 0
    return Math.min(100, Math.round((Number(usageData.value.queries_used || 0) / total) * 100))
})

const usageUsedLabel = computed(() => formatCount(usageData.value.queries_used))
const usageTotalLabel = computed(() => usageData.value.is_unlimited ? '∞' : formatCount(usageData.value.queries_total))

// 配额颜色
const quotaColor = computed(() => {
    const pct = quotaPercentage.value
    if (pct >= 90) return '#ef4444'
    if (pct >= 70) return '#f59e0b'
    return '#22c55e'
})

const formatCount = (value) => {
    const n = Number(value || 0)
    return new Intl.NumberFormat().format(n)
}

const ensureSelectedPaymentMethod = () => {
    const enabled = paymentMethods.value.map((method) => method.value)
    if (!enabled.includes(selectedPaymentMethod.value)) {
        selectedPaymentMethod.value = enabled[0] || 'card'
    }
}

const loadPaymentMethods = async () => {
    try {
        const { data } = await client.get('/user/payment-methods')
        const stripeMethods = data?.data?.methods || []
        if (Array.isArray(stripeMethods) && stripeMethods.length > 0) {
            const walletMethod = { value: 'wallet', label: '余额支付' }
            paymentMethods.value = [walletMethod, ...stripeMethods]
            selectedPaymentMethod.value = data?.data?.default || stripeMethods[0].value
            ensureSelectedPaymentMethod()
        } else {
            paymentMethods.value = [{ value: 'wallet', label: '余额支付' }]
            selectedPaymentMethod.value = 'wallet'
        }

        try {
            const stripeConfig = await client.get('/user/stripe-config')
            isStripeFake.value = stripeConfig?.data?.data?.is_fake || false
        } catch {}
    } catch {
        paymentMethods.value = [{ value: 'wallet', label: '余额支付' }, { value: 'card', label: '信用卡' }]
        selectedPaymentMethod.value = 'wallet'
    }
}

// 加载账户数据
const loadAccountData = async () => {
    loading.value = true
    try {
        await loadPaymentMethods()

        // 加载用户信息
        const { data: meData } = await client.get('/user/me')
        userInfo.value = meData.data || {}

        // 加载使用量
        try {
            const { data: usageRes } = await client.get('/user/usage')
            if (usageRes.data) {
                usageData.value = usageRes.data
            }
        } catch {}

        // 加载钱包余额
        try {
            const { data: walletRes } = await client.get('/user/wallet')
            if (walletRes.data) {
                walletBalance.value = walletRes.data
            }
        } catch {}

        // 加载订阅信息
        try {
            const { data: subRes } = await client.get('/user/subscription')
            if (subRes.data) {
                currentSubscription.value = subRes.data
            }
        } catch {}

        // 检查是否有未支付的订单，恢复支付流程
        try {
            const { data: ordersRes } = await client.get('/user/orders')
            const pendingOrderData = (ordersRes.data || []).find(
                (o) => o.status === 'pending'
            )
            if (pendingOrderData) {
                const amountLabel = money(pendingOrderData.payable_amount_minor, pendingOrderData.currency)
                pendingOrder.value = { ...pendingOrderData, amount_label: amountLabel }
                showPayDialog.value = true
            }
        } catch {}

        // 加载套餐列表
        try {
            const { data: planRes } = await client.get('/user/membership')
            if (planRes.data) {
                plans.value = planRes.data.plans || []
                currentPlanCode.value = planRes.data.plan || 'free'
            }
        } catch {}
    } catch (err) {
        console.error('Failed to load account data:', err)
    } finally {
        loading.value = false
    }
}

// 打开订阅弹窗
const openSubscribeDialog = async () => {
    selectedPlan.value = null
    selectedBillingCycle.value = 'monthly'
    await loadPaymentMethods()
    
    // 如果套餐列表为空，重新加载
    if (plans.value.length === 0) {
        try {
            const { data } = await client.get('/user/membership')
            if (data.data) {
                plans.value = data.data.plans || []
                currentPlanCode.value = data.data.plan || 'free'
            }
        } catch {}
    }
    
    showSubscribeDialog.value = true
}

// 选择套餐
const selectPlan = (plan) => {
    if (plan.code === currentPlanCode.value) return
    selectedPlan.value = plan
    // 默认选中第一个计费周期
    const activePrices = (plan?.prices || []).filter((p) => p.status === 'active')
    if (activePrices.length > 0) {
        selectedBillingCycle.value = activePrices[0].billing_cycle
    }
}

// 获取有效价格
const activePrices = (plan) => (plan?.prices || []).filter((p) => p.status === 'active')

// 格式化价格
const formatPrice = (plan) => {
    const price = (plan?.prices || []).find((p) => p.status === 'active') || plan?.prices?.[0]
    if (!price) return '$0.00'
    return money(price.amount_minor, price.currency)
}

// 计费周期标签
const billingCycleLabel = (cycle) => {
    return cycle === 'yearly' ? t('account.subscription.yearly') || '年付' : t('account.subscription.monthly') || '月付'
}

// 应付金额（按当前选择计费周期）
const summaryAmount = computed(() => {
    if (!selectedPlan.value) return ''
    const price = (activePrices(selectedPlan.value) || []).find(
        (p) => p.billing_cycle === selectedBillingCycle.value
    ) || (activePrices(selectedPlan.value) || [])[0]
    if (!price) return ''
    return money(price.amount_minor, price.currency)
})

// 格式化金额
const money = (minor, currency = 'USD') => {
    const amount = Number(minor || 0) / 100
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
    }).format(amount)
}

// 处理订阅：先创建订单，再弹出在线支付弹窗
const handleSubscribe = async () => {
    if (!selectedPlan.value) return

    const price = (activePrices(selectedPlan.value) || []).find(
        (p) => p.billing_cycle === selectedBillingCycle.value
    ) || (activePrices(selectedPlan.value) || [])[0]

    if (!price) {
        ElMessage.error(t('account.subscription.subscribeFailed') || '订阅失败')
        return
    }

    subscribing.value = true
    try {
        // 1) 创建订单（带幂等 key，避免重复点击产生多笔订单）
        const idempotencyKey = `sub-${selectedPlan.value.code}-${selectedBillingCycle.value}-${Date.now()}`
        const { data: orderRes } = await client.post(
            '/user/orders',
            {
                plan_code: selectedPlan.value.code,
                description: `${selectedPlan.value.name} ${billingCycleLabel(selectedBillingCycle.value)}`,
                meta: {
                    billing_cycle: selectedBillingCycle.value,
                    source: 'account_subscribe_dialog',
                },
            },
            { headers: { 'Idempotency-Key': idempotencyKey } }
        )

        const order = orderRes.data || orderRes
        const amountLabel = money(order.payable_amount_minor, order.currency)

        // 2) 关闭订阅弹窗，打开在线支付弹窗
        pendingOrder.value = { ...order, amount_label: amountLabel }
        showSubscribeDialog.value = false
        showPayDialog.value = true
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.subscription.subscribeFailed') || '订阅失败')
    } finally {
        subscribing.value = false
    }
}

// 确认支付：根据支付方式走不同流程
const confirmPay = async () => {
    if (!pendingOrder.value) return

    paying.value = true
    try {
        if (selectedPaymentMethod.value === 'card') {
            await initCardPayment()
        } else if (selectedPaymentMethod.value === 'wechat_pay' || selectedPaymentMethod.value === 'alipay') {
            await initQrPayment()
        } else if (selectedPaymentMethod.value === 'wallet') {
            await confirmWalletPay()
        }
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.pay.failed') || '支付失败')
    } finally {
        paying.value = false
    }
}

// 余额支付
const confirmWalletPay = async () => {
    if (!pendingOrder.value) return

    if (walletInsufficient.value) {
        ElMessage.warning('余额不足，请先充值或选择其他支付方式')
        return
    }

    paying.value = true
    try {
        const { data } = await client.post(`/user/orders/${pendingOrder.value.id}/pay-with-wallet`)
        if (data?.data?.status === 'paid') {
            handlePaymentSuccess()
        } else {
            ElMessage.error(data?.message || '支付失败')
        }
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || '支付失败')
    } finally {
        paying.value = false
    }
}

// 模拟二维码支付成功（测试模式）
const mockQrPaySuccess = async () => {
    if (!paymentTransactionId.value) return

    try {
        const { data } = await client.post(`/user/payment-transactions/${paymentTransactionId.value}/mock-success`)
        if (data?.data?.status === 'success') {
            handlePaymentSuccess()
        }
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || '模拟支付失败')
    }
}

// 初始化信用卡支付（Stripe Elements）
const initCardPayment = async () => {
    if (!pendingOrder.value) return

    const { data } = await client.post(`/user/orders/${pendingOrder.value.id}/payment-intent`)
    const result = data?.data || data

    if (!result?.publishable_key) {
        throw new Error('Stripe publishable key not configured')
    }

    paymentTransactionId.value = result.payment_transaction_id
    currentClientSecret = result.client_secret

    stripeInstance = await loadStripe(result.publishable_key)
    if (!stripeInstance) {
        throw new Error('Failed to load Stripe')
    }

    const elements = stripeInstance.elements({
        clientSecret: result.client_secret,
    })

    await nextTick()

    cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#424770',
                '::placeholder': {
                    color: '#aab7c4',
                },
            },
            invalid: {
                color: '#9e2146',
            },
        },
    })

    cardElement.mount(cardElementRef.value)
    cardElement.on('change', (event) => {
        cardError.value = event.error?.message || ''
        stripeReady.value = event.complete
    })

    payStep.value = 'form'
}

// 确认信用卡支付
const confirmCardPay = async () => {
    if (!stripeInstance || !cardElement || !currentClientSecret) return

    paying.value = true
    cardError.value = ''

    try {
        const { error, paymentIntent } = await stripeInstance.confirmCardPayment(
            currentClientSecret,
            {
                payment_method: {
                    card: cardElement,
                },
            }
        )

        if (error) {
            cardError.value = error.message || '支付失败'
            return
        }

        if (paymentIntent && paymentIntent.status === 'succeeded') {
            handlePaymentSuccess()
        } else {
            startPaymentPolling()
        }
    } catch (err) {
        cardError.value = err?.message || '支付失败'
    } finally {
        paying.value = false
    }
}

// 初始化二维码支付（微信/支付宝）
const initQrPayment = async () => {
    if (!pendingOrder.value) return

    qrCodeUrl.value = ''
    payStep.value = 'qrcode'

    const { data } = await client.post(`/user/orders/${pendingOrder.value.id}/qr-payment`, {
        payment_method: selectedPaymentMethod.value,
    })
    const result = data?.data || data

    qrCodeUrl.value = result.qr_code_url
    paymentTransactionId.value = result.payment_transaction_id

    startPaymentPolling()
}

// 开始轮询支付状态
const startPaymentPolling = () => {
    if (paymentPollTimer) {
        clearInterval(paymentPollTimer)
    }

    paymentPollTimer = setInterval(async () => {
        if (!paymentTransactionId.value) return

        try {
            const { data } = await client.get(`/user/payment-transactions/${paymentTransactionId.value}/status`)
            const status = data?.data?.status

            if (status === 'success') {
                handlePaymentSuccess()
            } else if (status === 'failed') {
                clearInterval(paymentPollTimer)
                paymentPollTimer = null
                ElMessage.error(data.data.failure_message || '支付失败')
            }
        } catch {}
    }, 3000)
}

// 支付成功处理
const handlePaymentSuccess = () => {
    if (paymentPollTimer) {
        clearInterval(paymentPollTimer)
        paymentPollTimer = null
    }

    payStep.value = 'success'
    ElMessage.success(t('account.pay.success') || '支付成功')

    setTimeout(() => {
        showPayDialog.value = false
        pendingOrder.value = null
        payStep.value = 'select'
        qrCodeUrl.value = ''
        paymentTransactionId.value = ''
        if (cardElement) {
            cardElement.destroy()
            cardElement = null
        }
        stripeInstance = null
        currentClientSecret = ''
        stripeReady.value = false
        cardError.value = ''
        refreshSubscriptionData()
    }, 2000)
}

// 返回或取消
const handleBackOrCancel = () => {
    if (payStep.value === 'form' || payStep.value === 'qrcode') {
        if (paymentPollTimer) {
            clearInterval(paymentPollTimer)
            paymentPollTimer = null
        }
        if (cardElement) {
            cardElement.destroy()
            cardElement = null
        }
        stripeInstance = null
        currentClientSecret = ''
        stripeReady.value = false
        cardError.value = ''
        qrCodeUrl.value = ''
        paymentTransactionId.value = ''
        payStep.value = 'select'
    } else {
        cancelPay()
    }
}

const handlePaymentMethodChange = () => {
    if (payStep.value !== 'select') {
        payStep.value = 'select'
        if (cardElement) {
            cardElement.destroy()
            cardElement = null
        }
        stripeInstance = null
        currentClientSecret = ''
        stripeReady.value = false
        cardError.value = ''
        qrCodeUrl.value = ''
        paymentTransactionId.value = ''
        if (paymentPollTimer) {
            clearInterval(paymentPollTimer)
            paymentPollTimer = null
        }
    }
}

const cancelPay = () => {
    if (paymentPollTimer) {
        clearInterval(paymentPollTimer)
        paymentPollTimer = null
    }
    if (cardElement) {
        cardElement.destroy()
        cardElement = null
    }
    stripeInstance = null
    currentClientSecret = ''
    stripeReady.value = false
    cardError.value = ''
    payStep.value = 'select'
    qrCodeUrl.value = ''
    paymentTransactionId.value = ''
    showPayDialog.value = false
    pendingOrder.value = null
}

// 刷新订阅/使用量数据
const refreshSubscriptionData = async () => {
    try {
        const { data: subRes } = await client.get('/user/subscription')
        if (subRes.data) {
            currentSubscription.value = subRes.data
        }
    } catch {}
    try {
        const { data: usageRes } = await client.get('/user/usage')
        if (usageRes.data) {
            usageData.value = usageRes.data
        }
    } catch {}
    try {
        const { data: planRes } = await client.get('/user/membership')
        if (planRes.data?.plan) {
            currentPlanCode.value = planRes.data.plan
        }
    } catch {}
}

// 充值
const handleRecharge = async () => {
    recharging.value = true
    try {
        const { data } = await client.post('/user/wallet/recharge', {
            amount: rechargeForm.value.amount,
            payment_method: selectedPaymentMethod.value
        })
        const payload = data?.data || data
        const order = payload.order || payload

        if (order?.id) {
            const amountLabel = money(order.payable_amount_minor, order.currency)
            pendingOrder.value = { ...order, amount_label: amountLabel }
            showRechargeDialog.value = false
            showPayDialog.value = true
            return
        }

        await loadAccountData()
        ElMessage.success(t('account.recharge.success') || '充值请求已提交')
        showRechargeDialog.value = false
    } catch (err) {
        const errors = err?.response?.data?.errors
        if (errors && typeof errors === 'object') {
            ElMessage.error(Object.values(errors).flat().join('\n'))
        } else {
            ElMessage.error(err?.response?.data?.message || err.message || t('account.recharge.failed') || '充值失败')
        }
    } finally {
        recharging.value = false
    }
}

// 修改邮箱
const handleUpdateEmail = async () => {
    if (!emailForm.value.email || !emailForm.value.password) {
        ElMessage.warning(t('account.email.fillAll') || '请填写完整')
        return
    }
    
    updatingEmail.value = true
    try {
        await client.put('/user/email', {
            email: emailForm.value.email,
            password: emailForm.value.password
        })
        userInfo.value.email = emailForm.value.email
        showEmailDialog.value = false
        emailForm.value = { email: '', password: '' }
        ElMessage.success(t('account.email.success') || '邮箱已更新')
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.email.failed') || '更新失败')
    } finally {
        updatingEmail.value = false
    }
}

// 修改密码
const handleUpdatePassword = async () => {
    if (!passwordForm.value.currentPassword || !passwordForm.value.newPassword) {
        ElMessage.warning(t('account.password.fillAll') || '请填写完整')
        return
    }
    
    if (passwordForm.value.newPassword !== passwordForm.value.confirmPassword) {
        ElMessage.warning(t('account.password.mismatch') || '两次密码不一致')
        return
    }
    
    updatingPassword.value = true
    try {
        await client.put('/user/password', {
            current_password: passwordForm.value.currentPassword,
            new_password: passwordForm.value.newPassword
        })
        showPasswordDialog.value = false
        passwordForm.value = { currentPassword: '', newPassword: '', confirmPassword: '' }
        ElMessage.success(t('account.password.success') || '密码已更新')
    } catch (err) {
        const errors = err?.response?.data?.errors
        if (errors && typeof errors === 'object') {
            ElMessage.error(Object.values(errors).flat().join('\n'))
        } else {
            ElMessage.error(err?.response?.data?.message || err.message || t('account.password.failed') || '更新失败')
        }
    } finally {
        updatingPassword.value = false
    }
}

// 格式化日期
const formatDate = (date) => {
    if (!date) return '-'
    return new Date(date).toLocaleDateString()
}

// 获取状态类型
const getStatusType = (status) => {
    const map = {
        active: 'success',
        trialing: 'warning',
        past_due: 'danger',
        canceled: 'info',
        suspended: 'danger'
    }
    return map[status] || 'info'
}

// 获取状态标签
const getStatusLabel = (status) => {
    return t(`account.subscription.status_${status}`) || status
}

onMounted(() => {
    loadAccountData()
})
</script>

<style scoped>
.account-page {
    padding: 0;
}

.page-header {
    margin-bottom: 24px;
}

.page-title {
    font-size: 24px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
}

.page-desc {
    color: #64748b;
    margin: 0;
}

.account-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    margin: 0;
}

.card-icon {
    font-size: 20px;
    color: #2563eb;
}

.card-body {
    color: #475569;
}

/* 配额 */
.quota-desc {
    margin: 0 0 16px;
    font-size: 14px;
}

.quota-bar {
    margin-bottom: 16px;
}

.quota-text {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}

.quota-unlimited {
    color: #22c55e;
    font-weight: 600;
}

.quota-upgrade {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: rgba(37, 99, 235, 0.06);
    border-radius: 8px;
}

.quota-upgrade p {
    margin: 0;
    font-size: 14px;
}

/* 余额 */
.balance-info {
    margin-bottom: 16px;
}

.balance-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
}

.balance-label {
    color: #64748b;
}

.balance-value {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.balance-insufficient {
    color: #ef4444;
}

.balance-tip {
    font-size: 12px;
    font-weight: 400;
    color: #ef4444;
    margin-left: 4px;
}

.balance-note {
    margin: 12px 0 0;
    font-size: 12px;
    color: #94a3b8;
}

.balance-actions {
    display: flex;
    gap: 12px;
}

/* 订阅 */
.subscription-info {
    margin-bottom: 16px;
}

.sub-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.sub-label {
    color: #64748b;
    font-size: 14px;
}

.sub-value {
    font-weight: 500;
}

.no-subscription p {
    margin: 0 0 16px;
    color: #64748b;
}

.subscribe-btn {
    width: 100%;
}

/* 设置行 */
.setting-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.setting-info {
    flex: 1;
}

.setting-desc {
    margin: 0 0 4px;
    font-size: 14px;
}

.setting-value {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    color: #0f172a;
}

@media (max-width: 768px) {
    .account-grid {
        grid-template-columns: 1fr;
    }
}

/* 套餐选择弹窗 */
.subscribe-plans {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}

.plan-option {
    padding: 20px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.plan-option:hover {
    border-color: #cbd5e1;
}

.plan-option.selected {
    border-color: #2563eb;
    background: rgba(37, 99, 235, 0.04);
}

.plan-option.current {
    cursor: default;
    opacity: 0.7;
}

.plan-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.plan-name {
    font-size: 16px;
    font-weight: 700;
    color: #0f172a;
}

.plan-price {
    font-size: 24px;
    font-weight: 800;
    color: #2563eb;
    margin-bottom: 12px;
}

.plan-features {
    margin: 0;
    padding: 0;
    list-style: none;
}

.plan-features li {
    font-size: 13px;
    color: #64748b;
    padding: 4px 0;
}

.billing-cycle-section {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
}

.billing-cycle-section h4 {
    margin: 0 0 12px;
    font-size: 14px;
    color: #475569;
}

.billing-option {
    width: 100%;
    margin-bottom: 8px !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.billing-label {
    font-weight: 500;
}

.billing-price {
    color: #2563eb;
    font-weight: 700;
}

/* 订阅摘要 */
.subscribe-summary {
    background: rgba(37, 99, 235, 0.04);
    border: 1px solid rgba(37, 99, 235, 0.12);
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
}
.summary-label {
    color: #64748b;
}
.summary-value {
    color: #0f172a;
    font-weight: 500;
}
.summary-amount {
    color: #2563eb;
    font-size: 18px;
    font-weight: 700;
}

/* 在线支付弹窗 */
.pay-summary {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.pay-tip {
    margin: 0 0 4px;
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
}
.pay-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
}
.pay-row:last-of-type {
    border-bottom: none;
}
.pay-label {
    color: #64748b;
    font-size: 14px;
}
.pay-value {
    font-size: 14px;
    color: #0f172a;
    font-weight: 500;
}
.pay-amount {
    color: #2563eb;
    font-size: 18px;
    font-weight: 700;
}
.stripe-only-method {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #eff6ff;
}
.pay-methods {
    margin-top: 8px;
}
.pay-method-group {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.pay-method-option {
    width: 100%;
    margin: 0 !important;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.pay-method-label {
    font-weight: 600;
    color: #0f172a;
    margin-right: 8px;
}
.pay-method-desc {
    color: #64748b;
    font-size: 13px;
}

/* 余额支付 */
.wallet-section {
    margin-top: 24px;
}
.wallet-info {
    padding: 20px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 12px;
    color: #fff;
}
.wallet-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 8px;
}
.wallet-amount {
    font-size: 28px;
    font-weight: 700;
}
.wallet-insufficient {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    padding: 10px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 8px;
    color: #dc2626;
    font-size: 13px;
}

/* 信用卡表单 */
.card-form-section {
    margin-top: 24px;
}
.form-label {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 12px;
}
.stripe-card-element {
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    min-height: 44px;
}
.card-error {
    color: #ef4444;
    font-size: 13px;
    margin-top: 8px;
}

/* 二维码支付 */
.qr-section {
    margin-top: 24px;
    text-align: center;
}
.qr-container {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-bottom: 16px;
}
.qr-image {
    width: 200px;
    height: 200px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px;
    background: #fff;
}
.qr-loading {
    width: 200px;
    height: 200px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 8px;
    color: #64748b;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #f8fafc;
}
.qr-tip {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
    margin: 0 0 8px;
}
.qr-status {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 14px;
    color: #64748b;
    margin: 0;
}
.qr-status .el-icon {
    animation: spin 2s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.qr-test-hint {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 13px;
    color: #f59e0b;
    margin: 12px 0 0;
}
.mock-pay-btn {
    margin-top: 16px;
    width: 100%;
}

/* 支付成功 */
.pay-success {
    text-align: center;
    padding: 32px 0;
}
.success-icon {
    font-size: 64px;
    color: #22c55e;
    margin-bottom: 16px;
}
.success-text {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 8px;
}
.success-desc {
    font-size: 14px;
    color: #64748b;
    margin: 0;
}
</style>
