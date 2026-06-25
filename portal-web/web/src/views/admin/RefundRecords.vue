<template>
    <ListPage
        :title="$t('admin.finance.refundRecords')"
        
        i18n-key="admin.finance.refundRecords"
        icon-name="Wallet"
        :total="meta?.total ?? 0"
        :current-page="currentPage"
        :page-size="pageSize"
        :show-pagination="!!meta && (meta?.total > pageSize)"
        @refresh="fetchRefunds"
        @page-change="(p) => { currentPage = p; fetchRefunds() }"
        @size-change="(s) => { pageSize = s; currentPage = 1; fetchRefunds() }"
    >
        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId')"
                size="small"
                style="width:200px"
                clearable
                @keyup.enter="fetchRefunds"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-select
                v-model="filterStatus"
                size="small"
                style="width:120px"
                clearable
                :placeholder="$t('admin.finance.status')"
                @change="fetchRefunds"
            >
                <el-option value="pending" :label="$t('admin.finance.statusPending')" />
                <el-option value="succeeded" :label="$t('admin.finance.statusSucceeded')" />
                <el-option value="failed" :label="$t('admin.finance.statusFailed')" />
                <el-option value="canceled" :label="$t('admin.finance.statusCanceled')" />
            </el-select>
            <el-button size="small" type="primary" @click="fetchRefunds">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('common.export') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="refunds" stripe style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Wallet /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                </div>
            </template>
            <el-table-column prop="refund_no" :label="$t('admin.finance.refundNo')" width="210" show-overflow-tooltip />
            <el-table-column prop="user_id" :label="$t('admin.finance.userId')" width="220" show-overflow-tooltip />
            <el-table-column prop="payment_id" :label="$t('admin.finance.paymentId')" min-width="240" show-overflow-tooltip />
            <el-table-column prop="amount_minor" :label="$t('admin.finance.refundAmount')" width="150">
                <template #default="{ row }">
                    <span class="amount-negative">-{{ formatMoney(row.amount_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="currency" :label="$t('admin.finance.currency')" width="80" />
            <el-table-column prop="status" :label="$t('admin.finance.status')" width="120">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="reason" :label="$t('admin.finance.reason')" min-width="180" show-overflow-tooltip />
            <el-table-column prop="created_at" :label="$t('admin.finance.createdAt')" width="180">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.finance.actions')" width="190" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" text type="primary" @click="showDetail(row)">{{ $t('common.detail') }}</el-button>
                    <el-button v-if="row.status === 'pending'" size="small" text type="success" @click="handleApprove(row)">{{ $t('common.approve') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showRefundDetail" :title="$t('admin.finance.refundDetail')" width="550px">
        <el-descriptions v-if="selectedRefund" :column="2" border>
            <el-descriptions-item :label="$t('admin.finance.refundNo')">{{ selectedRefund.refund_no }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.userId')">{{ selectedRefund.user_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.paymentId')">{{ selectedRefund.payment_id }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.refundAmount')">
                <span class="amount-negative">-{{ formatMoney(selectedRefund.amount_minor, selectedRefund.currency) }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.currency')">{{ selectedRefund.currency }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.status')">
                <el-tag :type="getStatusType(selectedRefund.status)" size="small">{{ selectedRefund.status }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.reason')" :span="2">{{ selectedRefund.reason || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.createdAt')">{{ selectedRefund.created_at ? new Date(selectedRefund.created_at).toLocaleString() : '-' }}</el-descriptions-item>
            <el-descriptions-item :label="$t('admin.finance.refundedAt')">{{ selectedRefund.refunded_at ? new Date(selectedRefund.refunded_at).toLocaleString() : '-' }}</el-descriptions-item>
        </el-descriptions>
        <template #footer>
            <el-button @click="showRefundDetail = false">{{ $t('common.close') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Wallet, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const refunds = ref([])
const meta = ref(null)
const loading = ref(false)
const currentPage = ref(1)
const pageSize = ref(20)
const filterUserId = ref('')
const filterStatus = ref('')
const exporting = ref(false)
const showRefundDetail = ref(false)
const selectedRefund = ref(null)

const currencySymbol = (currency) => {
    if ((currency || 'USD').toUpperCase() === 'USD') return 'US$'
    const map = { CNY: '¥', EUR: '€', GBP: '£', JPY: '¥', KRW: '₩' }
    return map[(currency || '').toUpperCase()] || ((currency || 'USD') + ' ')
}

const formatMoney = (minor, currency = 'USD') => {
    if (minor === null || minor === undefined || Number.isNaN(Number(minor))) return '-'
    return `${currencySymbol(currency)}${(Number(minor) / 100).toFixed(2)}`
}

const getStatusType = (status) => {
    const map = { pending: 'warning', succeeded: 'success', failed: 'danger', canceled: 'info' }
    return map[status] || 'info'
}

const fetchRefunds = async () => {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: pageSize.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterStatus.value) params.status = filterStatus.value
        const { data } = await client.get('/admin/finance/refunds', { params })
        refunds.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        refunds.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    filterStatus.value = ''
    pageSize.value = 20
    currentPage.value = 1
    fetchRefunds()
}

const showDetail = (row) => {
    selectedRefund.value = row
    showRefundDetail.value = true
}

const handleApprove = async (row) => {
    try {
        await client.post(`/admin/finance/refunds/${row.id}/approve`)
        ElMessage.success(t('admin.finance.approveSuccess'))
        fetchRefunds()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.finance.approveFailed'))
    }
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filterUserId.value) params.user_id = filterUserId.value
        if (filterStatus.value) params.status = filterStatus.value
        const response = await client.get('/admin/finance/refunds/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `refund-export-${new Date().toISOString().slice(0, 10)}.json`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('admin.finance.exportSuccess'))
    } catch {
        ElMessage.error(t('admin.finance.exportFailed'))
    } finally {
        exporting.value = false
    }
}

onMounted(() => {
    fetchRefunds()
})
</script>

<style scoped>
.amount-negative { color:#f56c6c; font-weight:600; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
