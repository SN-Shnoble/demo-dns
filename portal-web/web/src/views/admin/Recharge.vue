<template>
    <ListPage
        :title="$t('admin.finance.recharge')"
        
        i18n-key="admin.finance.recharge"
        icon-name="CreditCard"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta"
        @refresh="fetchRecharges"
        @page-change="(p) => { page = p; fetchRecharges() }"
        @size-change="(s) => { perPage = s; page = 1; fetchRecharges() }"
    >
        <template #filters>
            <el-input
                v-model="filterUserId"
                :placeholder="$t('admin.finance.userId')"
                size="small"
                style="width:200px"
                clearable
                @keyup.enter="fetchRecharges"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchRecharges">
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

        <el-table v-loading="loading" :data="recharges" stripe style="width: 100%">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><CreditCard /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.finance.userName')" min-width="160" show-overflow-tooltip>
                <template #default="{ row }">
                    <span>{{ row.user_name || row.user_email || '-' }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="user_id" :label="$t('admin.finance.userId')" width="200" show-overflow-tooltip />
            <el-table-column prop="amount_minor" :label="$t('admin.finance.amount')" width="140">
                <template #default="{ row }">
                    <span class="amount-positive">+{{ formatMoney(row.amount_minor, row.currency) }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="payment_method" :label="$t('admin.finance.paymentMethod')" width="140" show-overflow-tooltip />
            <el-table-column prop="transaction_id" :label="$t('admin.finance.transactionId')" min-width="220" show-overflow-tooltip />
            <el-table-column prop="status" :label="$t('admin.finance.status')" width="100">
                <template #default="{ row }">
                    <el-tag :type="getStatusType(row.status)" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="$t('admin.finance.createdAt')" width="160">
                <template #default="{ row }">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '-' }}</template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { CreditCard, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const recharges = ref([])
const meta = ref(null)
const loading = ref(false)
const page = ref(1)
const perPage = ref(20)
const filterUserId = ref('')
const exporting = ref(false)

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
    const map = { completed: 'success', succeeded: 'success', pending: 'warning', failed: 'danger' }
    return map[status] || 'info'
}

const fetchRecharges = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filterUserId.value) params.user_id = filterUserId.value
        const { data } = await client.get('/admin/finance/recharges', { params })
        recharges.value = data.data ?? []
        meta.value = data.meta ?? null
    } catch {
        recharges.value = []
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    filterUserId.value = ''
    perPage.value = 20
    page.value = 1
    fetchRecharges()
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filterUserId.value) params.user_id = filterUserId.value
        const response = await client.get('/admin/finance/recharges/export', { params, responseType: 'blob' })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `recharge-export-${new Date().toISOString().slice(0, 10)}.json`)
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
    fetchRecharges()
})
</script>

<style scoped>
.amount-positive { color: #67c23a; font-weight: 600; }
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
</style>
