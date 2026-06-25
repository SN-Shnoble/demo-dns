<template>
    <div class="list-page">
        <div class="page-header">
            <h2 class="page-title">{{ $t('admin.billing.title') || 'Billing & Usage' }}</h2>
            <p class="page-desc">{{ $t('admin.billing.desc') || 'Usage statistics, billing and financial management' }}</p>
        </div>

        <el-card shadow="never" class="list-card">
            <template #header>
                <div class="card-header">
                    <div class="card-title">
                        <el-icon class="title-icon is-warning"><List /></el-icon>
                        <span class="title-text">{{ $t('admin.billing.transactions') || 'Transactions' }} ({{ billMeta?.total ?? 0 }})</span>
                    </div>
                    <div class="card-actions">
                        <el-input v-model="billFilter.user_id" :placeholder="$t('admin.billing.userId') || 'User ID'" size="default" style="width:180px" clearable @keyup.enter="fetchBills">
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                        <el-button size="default" @click="fetchBills">{{ $t('common.search') }}</el-button>
                        <el-button size="default" @click="handleResetBill">{{ $t('common.reset') }}</el-button>
                        <el-button size="default" type="success" :loading="exporting" @click="handleExport">
                            <el-icon class="el-icon--left"><Download /></el-icon>
                            <span>{{ $t('common.export') }}</span>
                        </el-button>
                        <el-button size="default" type="primary" @click="showCharge = true">{{ $t('admin.billing.charge') }}</el-button>
                        <el-button size="default" type="danger" @click="showRefund = true">{{ $t('admin.billing.refund') }}</el-button>
                    </div>
                </div>
            </template>

            <el-table :data="transactions" stripe :empty-text="$t('common.noData')" style="width: 100%">
                <el-table-column prop="type" :label="$t('admin.billing.type') || 'Type'" width="110">
                    <template #default="{ row }">
                        <el-tag :type="isPositiveTransaction(row.type) ? 'success' : 'danger'" size="small" effect="light">{{ transactionTypeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.billing.userName') || 'User'" min-width="180" show-overflow-tooltip>
                    <template #default="{ row }">
                        <span>{{ row.user_name || row.user_email || row.user_id || '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="amount_minor" :label="$t('admin.billing.amount') || 'Amount'" width="140">
                    <template #default="{ row }">
                        <span :style="{ color: isPositiveTransaction(row.type) ? '#67c23a' : '#f56c6c' }">
                            {{ isPositiveTransaction(row.type) ? '+' : '-' }}{{ formatMoney(row.amount_minor, row.currency) }}
                        </span>
                    </template>
                </el-table-column>
                <el-table-column prop="description" :label="$t('admin.billing.description') || 'Description'" min-width="260" show-overflow-tooltip />
                <el-table-column prop="status" :label="$t('admin.billing.status') || 'Status'" width="100">
                    <template #default="{ row }">
                        <el-tag :type="['completed', 'succeeded', 'paid'].includes(row.status) ? 'success' : 'warning'" size="small" effect="light">{{ transactionStatusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('admin.billing.time') || 'Time'" width="180">
                    <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
                </el-table-column>
            </el-table>

            <div v-if="billMeta?.total > billPageSize" class="pagination-bar">
                <div class="pagination-total">
                    {{ $t('common.totalPrefix') }} <strong>{{ billMeta.total ?? 0 }}</strong> {{ $t('common.itemsSuffix') }}
                </div>
                <el-pagination
                    v-model:current-page="billPage"
                    :page-size="billPageSize"
                    :total="billMeta.total ?? 0"
                    layout="sizes, prev, pager, next"
                    background
                    @size-change="billPageSize = $event; billPage = 1; fetchBills()"
                    @current-change="fetchBills"
                />
            </div>
        </el-card>
    </div>

    <el-dialog v-model="showCharge" :title="$t('admin.billing.charge') || 'Charge'" width="520px">
        <el-form ref="chargeForm" :model="chargeData" label-position="top">
            <el-form-item :label="$t('admin.billing.userId') || 'User'" prop="user_id" :rules="[{ required: true, message: '请选择用户' }]">
                <el-select v-model="chargeData.user_id" filterable remote :remote-method="searchUsers" :placeholder="$t('common.search')" style="width:100%" clearable :loading="searching">
                    <el-option v-for="u in userOptions" :key="u.id" :label="`${u.username} (${u.email})`" :value="u.id" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('admin.billing.amount') || 'Amount (CNY)'" prop="amount_minor" :rules="[{ required: true }]">
                <el-input-number v-model="chargeAmount" :min="1" :precision="2" style="width:100%" />
            </el-form-item>
            <el-form-item :label="$t('admin.billing.description') || 'Description'">
                <el-input v-model="chargeData.description" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showCharge = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="charging" @click="handleCharge">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="showRefund" :title="$t('admin.billing.refund') || 'Refund'" width="520px">
        <el-form ref="refundForm" :model="refundData" label-position="top">
            <el-form-item :label="$t('admin.billing.userId') || 'User'" prop="user_id" :rules="[{ required: true, message: '请选择用户' }]">
                <el-select v-model="refundData.user_id" filterable remote :remote-method="searchUsers" :placeholder="$t('common.search')" style="width:100%" clearable :loading="searching">
                    <el-option v-for="u in userOptions" :key="u.id" :label="`${u.username} (${u.email})`" :value="u.id" />
                </el-select>
            </el-form-item>
            <el-form-item :label="$t('admin.billing.amount') || 'Amount (CNY)'" prop="amount_minor" :rules="[{ required: true }]">
                <el-input-number v-model="refundAmount" :min="1" :precision="2" style="width:100%" />
            </el-form-item>
            <el-form-item :label="$t('admin.billing.description') || 'Description'">
                <el-input v-model="refundData.description" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showRefund = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="danger" :loading="refunding" @click="handleRefund">{{ $t('common.confirm') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Search, Download, List } from '@element-plus/icons-vue'
import client from '@/api/client'

const { t } = useI18n()

const transactions = ref([])
const billMeta = ref(null)
const billPage = ref(1)
const billPageSize = ref(20)
const billFilter = reactive({ user_id: '' })
const exporting = ref(false)
const showCharge = ref(false)
const showRefund = ref(false)
const charging = ref(false)
const refunding = ref(false)
const chargeAmount = ref(100)
const refundAmount = ref(100)
const chargeForm = ref(null)
const refundForm = ref(null)

const chargeData = reactive({ user_id: '', description: '' })
const refundData = reactive({ user_id: '', description: '' })
const userOptions = ref([])
const searching = ref(false)

const searchUsers = async (query) => {
    if (!query || query.length < 1) {
        userOptions.value = []
        return
    }
    searching.value = true
    try {
        const { data } = await client.get('/admin/users', { params: { email: query, per_page: 20 } })
        userOptions.value = (data.data ?? []).map(u => ({ id: u.id || u.uid, username: u.username, email: u.email }))
    } catch {
        userOptions.value = []
    } finally {
        searching.value = false
    }
}

const currencySymbol = (currency) => {
    if ((currency || 'USD').toUpperCase() === 'USD') return 'US$'
    const map = { CNY: '¥', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[(currency || '').toUpperCase()] || `${currency || 'USD'} `
}

const formatMoney = (minor, currency = 'USD') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currencySymbol(currency)}${(Number(minor) / 100).toFixed(2)}`
}

const isPositiveTransaction = (type) => ['charge', 'wallet_topup', 'manual', 'payment', 'order'].includes(type)

const transactionTypeLabel = (type) => {
    const map = {
        charge: t('admin.billing.typeCharge'),
        wallet_topup: t('admin.billing.typeWalletTopup'),
        manual: t('admin.billing.typeWalletTopup'),
        refund: t('admin.billing.typeRefund'),
        payment: t('admin.billing.typePayment'),
        order: t('admin.billing.typeOrder'),
        deduction: t('admin.billing.typeDeduction'),
        adjust: t('admin.billing.typeAdjust'),
    }
    return map[type] || type || '-'
}

const transactionStatusLabel = (status) => {
    const map = {
        completed: t('admin.billing.statusCompleted'),
        succeeded: t('admin.billing.statusCompleted'),
        paid: t('admin.finance.statusPaid') || t('admin.billing.statusCompleted'),
        pending: t('admin.billing.statusPending'),
        failed: t('admin.billing.statusFailed'),
        canceled: t('admin.billing.statusCanceled'),
    }
    return map[status] || status || '-'
}

const handleCharge = async () => {
    charging.value = true
    try {
        await client.post('/admin/billing/charge', {
            user_id: chargeData.user_id,
            amount_minor: Math.round(chargeAmount.value * 100),
            description: chargeData.description || 'Admin charge',
        })
        ElMessage.success(t('admin.billing.chargeSuccess'))
        showCharge.value = false
        await fetchBills()
        chargeData.user_id = ''; chargeData.description = ''; chargeAmount.value = 100
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.billing.chargeFailed'))
    } finally {
        charging.value = false
    }
}

const handleRefund = async () => {
    refunding.value = true
    try {
        await client.post('/admin/billing/refund', {
            user_id: refundData.user_id,
            amount_minor: Math.round(refundAmount.value * 100),
            description: refundData.description || 'Admin refund',
        })
        ElMessage.success(t('admin.billing.refundSuccess'))
        showRefund.value = false
        await fetchBills()
        refundData.user_id = ''; refundData.description = ''; refundAmount.value = 100
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.billing.refundFailed'))
    } finally {
        refunding.value = false
    }
}

const fetchBills = async () => {
    try {
        const params = { page: billPage.value, per_page: billPageSize.value }
        if (billFilter.user_id) params.user_id = billFilter.user_id
        const { data } = await client.get('/admin/billing/bills', { params })
        transactions.value = data.data ?? []
        billMeta.value = data.meta ?? null
    } catch {
        transactions.value = []
    }
}

const handleResetBill = () => {
    billFilter.user_id = ''
    billPageSize.value = 20
    billPage.value = 1
    fetchBills()
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (billFilter.user_id) params.user_id = billFilter.user_id
        const response = await client.get('/admin/billing/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `billing-export-${new Date().toISOString().slice(0, 10)}.json`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('admin.billing.exportSuccess') || 'Export started')
    } catch {
        ElMessage.error(t('admin.billing.exportFailed') || 'Export failed')
    } finally {
        exporting.value = false
    }
}

onMounted(() => {
    fetchBills()
})
</script>

<style scoped>
.list-page {
    display: flex;
    flex-direction: column;
}
</style>
