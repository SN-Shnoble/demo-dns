<template>
    <ListPage
        :title="$t('admin.auditLogs.title') || '审计日志'"
        :desc="$t('admin.auditLogs.desc') || '查看管理员操作与系统安全事件'"
        i18n-key="admin.auditLogs"
        icon-name="Document"
        :total="meta?.total ?? 0"
        :current-page="page"
        :page-size="perPage"
        :show-pagination="!!meta && (meta?.total > perPage)"
        @refresh="fetchLogs"
        @page-change="(p) => { page = p; fetchLogs() }"
        @size-change="(s) => { perPage = s; page = 1; fetchLogs() }"
    >
        <template #filters>
            <el-input
                v-model="filters.action"
                :placeholder="$t('admin.auditLogs.searchAction') || '搜索操作'"
                style="width:220px"
                size="small"
                clearable
                @clear="fetchLogs"
                @keyup.enter="fetchLogs"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-input
                v-model="filters.actor_id"
                placeholder="Actor ID"
                style="width:220px"
                size="small"
                clearable
                @clear="fetchLogs"
                @keyup.enter="fetchLogs"
            />
            <el-button size="small" type="primary" @click="fetchLogs">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('admin.auditLogs.query') || '查询' }}</span>
            </el-button>
            <el-button size="small" @click="handleReset">
                <el-icon class="el-icon--left"><RefreshLeft /></el-icon>
                <span>{{ $t('common.reset') || '重置' }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" type="success" :loading="exporting" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('admin.auditLogs.export') || '导出' }}</span>
            </el-button>
            <el-button
                size="small"
                type="danger"
                plain
                :disabled="selected.length === 0"
                @click="handleBatchDelete"
            >
                <span>{{ $t('admin.auditLogs.batchDelete') || '批量删除' }} ({{ selected.length }})</span>
            </el-button>
        </template>

        <el-table :data="logs" stripe v-loading="loading" @selection-change="onSelectionChange">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Document /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') || '暂无审计日志' }}</p>
                    <p class="empty-desc">{{ $t('admin.auditLogs.emptyDesc') || 'Administrator operation records will be displayed here.' }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="created_at" :label="$t('admin.auditLogs.time') || '时间'" width="170">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="actor_id" :label="$t('admin.auditLogs.actor') || '操作者'" width="220" show-overflow-tooltip />
            <el-table-column prop="action" :label="$t('admin.auditLogs.action') || '操作'" width="260">
                <template #default="{ row }">
                    <el-tag size="small" effect="light">{{ row.action }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column prop="target_type" :label="$t('admin.auditLogs.resourceType') || '资源类型'" width="140" />
            <el-table-column prop="target_id" :label="$t('admin.auditLogs.resourceId') || '资源ID'" min-width="240" show-overflow-tooltip />
            <el-table-column prop="ip_hash" :label="$t('admin.auditLogs.ip') || 'IP'" width="160" show-overflow-tooltip />
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Document, Search, RefreshLeft, Download } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const logs = ref([])
const meta = ref({})
const selected = ref([])
const exporting = ref(false)
const loading = ref(false)
const filters = ref({ action: '', actor_id: '' })
const page = ref(1)
const perPage = ref(20)

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const onSelectionChange = (rows) => { selected.value = rows }

const handleReset = () => {
    filters.value = { action: '', actor_id: '' }
    page.value = 1
    fetchLogs()
}

const fetchLogs = async () => {
    loading.value = true
    try {
        const params = { page: page.value, per_page: perPage.value }
        if (filters.value.action) params.action = filters.value.action
        if (filters.value.actor_id) params.actor_id = filters.value.actor_id
        const { data } = await client.get('/admin/audit-logs', { params }).catch(() => ({ data: { data: [], meta: { total: 0, per_page: 20, page: 1 } } }))
        logs.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
    } finally {
        loading.value = false
    }
}

const handleExport = async () => {
    exporting.value = true
    try {
        const params = {}
        if (filters.value.action) params.action = filters.value.action
        if (filters.value.actor_id) params.actor_id = filters.value.actor_id
        const response = await client.get('/admin/audit-logs/export', {
            params,
            responseType: 'blob',
        })
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('download', `audit-logs-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-')}.ndjson`)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success(t('admin.auditLogs.exportSuccess') || 'Export started')
    } catch (err) {
        ElMessage.error(t('admin.auditLogs.exportFailed') || 'Export failed')
    } finally {
        exporting.value = false
    }
}

const handleBatchDelete = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.auditLogs.batchDeleteConfirm', { count: selected.value.length }),
            t('common.confirm'),
            { type: 'warning' },
        )
        const ids = selected.value.map((l) => l.id)
        const { data } = await client.post('/admin/audit-logs/batch-destroy', { ids })
        ElMessage.success(t('admin.auditLogs.batchDeleted', { count: data.data.deleted }))
        await fetchLogs()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.auditLogs.batchDeleteFailed'))
    }
}

onMounted(() => fetchLogs())
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
