<template>
    <Layout>
        <div class="account-page">
            <div class="page-header">
                <h1 class="page-title">{{ $t('account.title') }}</h1>
                <p class="page-desc">{{ $t('account.desc') }}</p>
            </div>

            <div class="account-grid">
                <div class="card quota-card">
                    <div class="card-header">
                        <el-icon class="card-icon"><Coin /></el-icon>
                        <h3>{{ $t('account.quota.title') }}</h3>
                    </div>
                    <div class="card-body">
                        <p class="quota-desc">{{ $t('account.quota.desc') }}</p>
                        <el-progress :percentage="quotaPercentage" :stroke-width="12" :color="quotaColor" />
                        <div class="quota-text">
                            <span>{{ $t('account.quota.used', { used: usageUsedLabel, total: usageTotalLabel }) }}</span>
                            <span v-if="usageData.is_unlimited" class="quota-unlimited">{{ $t('account.quota.unlimited') }}</span>
                        </div>
                        <div class="quota-footer">
                            <div class="current-plan">
                                <span>{{ $t('account.subscription.plan') }}</span>
                                <strong>{{ currentSubscription?.plan_name || currentPlanCode }}</strong>
                            </div>
                            <el-button type="primary" @click="openSubscribeDialog">
                                {{ $t('account.subscription.subscribeBtn') }}
                            </el-button>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <el-icon class="card-icon"><Wallet /></el-icon>
                        <h3>{{ $t('account.balance.title') }}</h3>
                    </div>
                    <div class="card-body">
                        <div class="balance-item">
                            <span class="balance-label">{{ $t('account.balance.available') }}</span>
                            <span class="balance-value">{{ walletBalanceLabel }}</span>
                        </div>
                        <p class="balance-note">{{ $t('account.balance.note') }}</p>
                    </div>
                </div>

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

            <el-dialog v-model="showSubscribeDialog" :title="$t('account.subscription.subscribeTitle')" width="800px" top="6vh">
                <div v-if="selectedPlan" class="subscribe-summary">
                    <div class="summary-row">
                        <span class="summary-label">{{ $t('account.subscription.summaryPlan') }}</span>
                        <span class="summary-value">{{ selectedPlan.name }}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">{{ $t('account.subscription.summaryCycle') }}</span>
                        <span class="summary-value">{{ billingCycleLabel(selectedBillingCycle) }}</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">{{ $t('account.subscription.summaryTotal') }}</span>
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
                                {{ $t('account.subscription.current') }}
                            </el-tag>
                            <el-tag v-else-if="plan.is_featured" type="primary" size="small">
                                {{ $t('account.subscription.recommended') }}
                            </el-tag>
                        </div>
                        <div class="plan-price">{{ formatPrice(plan) }}</div>
                        <ul class="plan-features">
                            <li v-for="feature in (plan.features || [])" :key="feature">{{ feature }}</li>
                        </ul>
                    </div>
                </div>

                <div v-if="selectedPlan" class="billing-cycle-section">
                    <h4>{{ $t('account.subscription.billingCycle') }}</h4>
                    <el-radio-group v-model="selectedBillingCycle" class="billing-options">
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
                        {{ $t('account.subscription.confirmSubscribe') }}
                    </el-button>
                </template>
            </el-dialog>

            <el-dialog v-model="showPayDialog" :title="$t('account.pay.title')" width="520px" top="8vh" :close-on-click-modal="false">
                <div v-if="pendingOrder" class="pay-summary">
                    <p class="pay-tip">{{ $t('account.pay.tip') }}</p>
                    <div class="pay-row">
                        <span class="pay-label">{{ $t('account.pay.orderNo') }}</span>
                        <span class="pay-value">{{ pendingOrder.order_no }}</span>
                    </div>
                    <div class="pay-row">
                        <span class="pay-label">{{ $t('account.pay.amount') }}</span>
                        <span class="pay-value pay-amount">{{ pendingOrder.amount_label }}</span>
                    </div>
                    <div class="pay-methods">
                        <div class="pay-method-label">{{ $t('account.pay.paymentMethod') }}</div>
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
                    </div>
                </div>
                <template #footer>
                    <el-button @click="cancelPay">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="paying" :disabled="!selectedPaymentMethod" @click="confirmPay">
                        {{ $t('account.pay.goPay') }}
                    </el-button>
                </template>
            </el-dialog>

            <el-dialog v-model="showEmailDialog" :title="$t('account.email.title')" width="400px">
                <el-form :model="emailForm" label-position="top">
                    <el-form-item :label="$t('account.email.newEmail')">
                        <el-input v-model="emailForm.email" type="email" />
                    </el-form-item>
                    <el-form-item :label="$t('account.email.password')">
                        <el-input v-model="emailForm.password" type="password" show-password />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button @click="showEmailDialog = false">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="updatingEmail" @click="handleUpdateEmail">
                        {{ $t('common.confirm') }}
                    </el-button>
                </template>
            </el-dialog>

            <el-dialog v-model="showPasswordDialog" :title="$t('account.password.title')" width="400px">
                <el-form :model="passwordForm" label-position="top">
                    <el-form-item :label="$t('account.password.current')">
                        <el-input v-model="passwordForm.currentPassword" type="password" show-password />
                    </el-form-item>
                    <el-form-item :label="$t('account.password.new')">
                        <el-input v-model="passwordForm.newPassword" type="password" show-password />
                    </el-form-item>
                    <el-form-item :label="$t('account.password.confirm')">
                        <el-input v-model="passwordForm.confirmPassword" type="password" show-password />
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
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Coin, Wallet, Message, Lock } from '@element-plus/icons-vue'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()

const loading = ref(false)
const userInfo = ref({ email: '', username: '' })
const usageData = ref({
    queries_used: 0,
    queries_total: 300000,
    is_unlimited: false,
    upgrade_price: 'US$3.99',
    quota_status: 'normal',
    plan_code: 'free',
})
const walletBalance = ref({ balance_minor: 0, currency: 'USD' })
const currentSubscription = ref(null)
const currentPlanCode = ref('free')
const plans = ref([])

const showEmailDialog = ref(false)
const showPasswordDialog = ref(false)
const showSubscribeDialog = ref(false)
const showPayDialog = ref(false)
const updatingEmail = ref(false)
const updatingPassword = ref(false)
const subscribing = ref(false)
const paying = ref(false)

const paymentMethodLabel = (method) => {
    const labels = {
        card: t('account.pay.card'),
        wechat_pay: t('account.pay.wechatPay'),
        alipay: t('account.pay.alipay'),
    }
    return labels[method] && labels[method] !== `account.pay.${method}` ? labels[method] : method
}

const normalizePaymentMethods = (methods) => {
    if (!Array.isArray(methods) || methods.length === 0) {
        return [{ value: 'card', label: paymentMethodLabel('card') }]
    }
    return methods
        .filter((method) => method?.value)
        .map((method) => ({ ...method, label: paymentMethodLabel(method.value) || method.label || method.value }))
}

const paymentMethods = ref([{ value: 'card', label: paymentMethodLabel('card') }])
const selectedPaymentMethod = ref('card')
const pendingOrder = ref(null)

const selectedPlan = ref(null)
const selectedBillingCycle = ref('monthly')
const emailForm = ref({ email: '', password: '' })
const passwordForm = ref({ currentPassword: '', newPassword: '', confirmPassword: '' })

const money = (minor, currency = 'USD') => {
    const code = String(currency || 'USD').toUpperCase()
    const amount = Number(minor || 0) / 100
    if (code === 'USD') {
        return `US$${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
    }
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: code,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount)
}

const walletBalanceLabel = computed(() => money(walletBalance.value.balance_minor, walletBalance.value.currency || 'USD'))
const quotaPercentage = computed(() => {
    if (usageData.value.is_unlimited) return 0
    const total = Number(usageData.value.queries_total || 0)
    if (total <= 0) return 0
    return Math.min(100, Math.round((Number(usageData.value.queries_used || 0) / total) * 100))
})
const usageUsedLabel = computed(() => formatCount(usageData.value.queries_used))
const usageTotalLabel = computed(() => usageData.value.is_unlimited ? '∞' : formatCount(usageData.value.queries_total))
const quotaColor = computed(() => {
    if (quotaPercentage.value >= 90) return '#ef4444'
    if (quotaPercentage.value >= 70) return '#f59e0b'
    return '#22c55e'
})
const summaryAmount = computed(() => {
    if (!selectedPlan.value) return ''
    const price = activePrices(selectedPlan.value).find((p) => p.billing_cycle === selectedBillingCycle.value)
        || activePrices(selectedPlan.value)[0]
    return price ? money(price.amount_minor, price.currency) : ''
})

const formatCount = (value) => new Intl.NumberFormat().format(Number(value || 0))
const activePrices = (plan) => (plan?.prices || []).filter((p) => p.status === 'active')
const billingCycleLabel = (cycle) => cycle === 'yearly' ? t('account.subscription.yearly') : t('account.subscription.monthly')
const formatPrice = (plan) => {
    const price = activePrices(plan)[0] || plan?.prices?.[0]
    return price ? money(price.amount_minor, price.currency) : 'US$0.00'
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
        const methods = data?.data?.methods || []
        paymentMethods.value = normalizePaymentMethods(methods)
        selectedPaymentMethod.value = data?.data?.default || paymentMethods.value[0]?.value || 'card'
    } catch {
        paymentMethods.value = [{ value: 'card', label: paymentMethodLabel('card') }]
        selectedPaymentMethod.value = 'card'
    }
    ensureSelectedPaymentMethod()
}

const loadAccountData = async () => {
    loading.value = true
    try {
        await loadPaymentMethods()

        const { data: meData } = await client.get('/user/me')
        userInfo.value = meData.data || {}

        const requests = [
            client.get('/user/usage').then(({ data }) => { if (data.data) usageData.value = data.data }).catch(() => {}),
            client.get('/user/wallet').then(({ data }) => { if (data.data) walletBalance.value = data.data }).catch(() => {}),
            client.get('/user/subscription').then(({ data }) => { currentSubscription.value = data.data || null }).catch(() => {}),
            client.get('/user/membership').then(({ data }) => {
                if (data.data) {
                    plans.value = data.data.plans || []
                    currentPlanCode.value = data.data.plan || 'free'
                }
            }).catch(() => {}),
            client.get('/user/orders').then(({ data }) => {
                const pending = (data.data || []).find((order) => order.status === 'pending')
                if (pending) {
                    pendingOrder.value = { ...pending, amount_label: money(pending.payable_amount_minor, pending.currency) }
                    showPayDialog.value = true
                }
            }).catch(() => {}),
        ]
        await Promise.all(requests)
    } catch (err) {
        console.error('Failed to load account data:', err)
    } finally {
        loading.value = false
    }
}

const openSubscribeDialog = async () => {
    selectedPlan.value = null
    selectedBillingCycle.value = 'monthly'
    await loadPaymentMethods()
    if (plans.value.length === 0) {
        await loadAccountData()
    }
    showSubscribeDialog.value = true
}

const selectPlan = (plan) => {
    if (plan.code === currentPlanCode.value) return
    selectedPlan.value = plan
    selectedBillingCycle.value = activePrices(plan)[0]?.billing_cycle || 'monthly'
}

const handleSubscribe = async () => {
    if (!selectedPlan.value) return
    const price = activePrices(selectedPlan.value).find((p) => p.billing_cycle === selectedBillingCycle.value)
        || activePrices(selectedPlan.value)[0]
    if (!price) {
        ElMessage.error(t('account.subscription.subscribeFailed'))
        return
    }

    subscribing.value = true
    try {
        const idempotencyKey = `sub-${selectedPlan.value.code}-${selectedBillingCycle.value}-${Date.now()}`
        const { data: orderRes } = await client.post('/user/orders', {
            plan_code: selectedPlan.value.code,
            billing_cycle: selectedBillingCycle.value,
            description: `${selectedPlan.value.name} ${billingCycleLabel(selectedBillingCycle.value)}`,
            meta: {
                billing_cycle: selectedBillingCycle.value,
                source: 'account_subscribe_dialog',
            },
        }, { headers: { 'Idempotency-Key': idempotencyKey } })

        const order = orderRes.data || orderRes
        pendingOrder.value = { ...order, amount_label: money(order.payable_amount_minor, order.currency) }
        showSubscribeDialog.value = false
        showPayDialog.value = true
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.subscription.subscribeFailed'))
    } finally {
        subscribing.value = false
    }
}

const confirmPay = async () => {
    if (!pendingOrder.value) return
    paying.value = true
    try {
        const { data } = await client.post(`/user/orders/${pendingOrder.value.id}/checkout`, {
            payment_method: selectedPaymentMethod.value,
        })
        const redirectUrl = data?.data?.redirect_url
        if (!redirectUrl) {
            throw new Error(t('account.pay.stripeNotConfigured'))
        }
        window.location.href = redirectUrl
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.pay.failed'))
    } finally {
        paying.value = false
    }
}

const cancelPay = () => {
    showPayDialog.value = false
    pendingOrder.value = null
}

const handleUpdateEmail = async () => {
    if (!emailForm.value.email || !emailForm.value.password) {
        ElMessage.warning(t('account.email.fillAll'))
        return
    }

    updatingEmail.value = true
    try {
        await client.put('/user/email', {
            email: emailForm.value.email,
            password: emailForm.value.password,
        })
        userInfo.value.email = emailForm.value.email
        showEmailDialog.value = false
        emailForm.value = { email: '', password: '' }
        ElMessage.success(t('account.email.success'))
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || err.message || t('account.email.failed'))
    } finally {
        updatingEmail.value = false
    }
}

const handleUpdatePassword = async () => {
    if (!passwordForm.value.currentPassword || !passwordForm.value.newPassword) {
        ElMessage.warning(t('account.password.fillAll'))
        return
    }
    if (passwordForm.value.newPassword !== passwordForm.value.confirmPassword) {
        ElMessage.warning(t('account.password.mismatch'))
        return
    }

    updatingPassword.value = true
    try {
        await client.put('/user/password', {
            current_password: passwordForm.value.currentPassword,
            new_password: passwordForm.value.newPassword,
        })
        showPasswordDialog.value = false
        passwordForm.value = { currentPassword: '', newPassword: '', confirmPassword: '' }
        ElMessage.success(t('account.password.success'))
    } catch (err) {
        const errors = err?.response?.data?.errors
        ElMessage.error(errors ? Object.values(errors).flat().join('\n') : (err?.response?.data?.message || err.message || t('account.password.failed')))
    } finally {
        updatingPassword.value = false
    }
}

onMounted(loadAccountData)
</script>

<style scoped>
.account-page { padding: 0; }
.page-header { margin-bottom: 24px; }
.page-title { font-size: 24px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
.page-desc { color: #64748b; margin: 0; }
.account-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; }
.card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); border: 1px solid #eef2f7; }
.quota-card { grid-column: 1 / -1; }
.card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
.card-header h3 { font-size: 16px; font-weight: 600; color: #0f172a; margin: 0; }
.card-icon { font-size: 20px; color: #2563eb; }
.card-body { color: #475569; }
.quota-desc { margin: 0 0 16px; font-size: 14px; }
.quota-text { display: flex; justify-content: space-between; margin-top: 8px; font-size: 13px; color: #64748b; }
.quota-unlimited { color: #22c55e; font-weight: 600; }
.quota-footer { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 18px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
.current-plan { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #64748b; }
.current-plan strong { color: #0f172a; font-size: 16px; }
.balance-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.balance-label { color: #64748b; }
.balance-value { font-size: 20px; font-weight: 700; color: #0f172a; }
.balance-note { margin: 12px 0 0; font-size: 12px; color: #94a3b8; }
.setting-row { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.setting-info { flex: 1; min-width: 0; }
.setting-desc { margin: 0 0 4px; font-size: 14px; }
.setting-value { margin: 0; font-size: 16px; font-weight: 500; color: #0f172a; overflow-wrap: anywhere; }
.subscribe-plans { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
.plan-option { padding: 20px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.2s; }
.plan-option:hover { border-color: #cbd5e1; }
.plan-option.selected { border-color: #2563eb; background: rgba(37, 99, 235, 0.04); }
.plan-option.current { cursor: default; opacity: 0.7; }
.plan-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 12px; }
.plan-name { font-size: 16px; font-weight: 700; color: #0f172a; }
.plan-price { font-size: 24px; font-weight: 800; color: #2563eb; margin-bottom: 12px; }
.plan-features { margin: 0; padding: 0; list-style: none; }
.plan-features li { font-size: 13px; color: #64748b; padding: 4px 0; }
.billing-cycle-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
.billing-cycle-section h4 { margin: 0 0 12px; font-size: 14px; color: #475569; }
.billing-options { display: grid; gap: 8px; }
.billing-option { width: 100%; margin: 0 !important; }
.billing-label { font-weight: 500; }
.billing-price { color: #2563eb; font-weight: 700; margin-left: 8px; }
.subscribe-summary { background: rgba(37, 99, 235, 0.04); border: 1px solid rgba(37, 99, 235, 0.12); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; flex-direction: column; gap: 6px; }
.summary-row, .pay-row { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.summary-label, .pay-label { color: #64748b; font-size: 14px; }
.summary-value, .pay-value { color: #0f172a; font-weight: 500; }
.summary-amount, .pay-amount { color: #2563eb; font-size: 18px; font-weight: 700; }
.pay-summary { display: flex; flex-direction: column; gap: 12px; }
.pay-tip { margin: 0 0 4px; font-size: 13px; color: #64748b; line-height: 1.5; }
.pay-row { padding: 8px 0; border-bottom: 1px solid #f1f5f9; }
.pay-methods { margin-top: 8px; }
.pay-method-label { font-weight: 600; color: #0f172a; margin-bottom: 8px; }
.pay-method-group { width: 100%; display: flex; flex-direction: column; gap: 8px; }
.pay-method-option { width: 100%; margin: 0 !important; padding: 12px 14px; }
@media (max-width: 900px) {
    .account-grid, .subscribe-plans { grid-template-columns: 1fr; }
    .quota-footer { align-items: stretch; flex-direction: column; }
}
</style>
