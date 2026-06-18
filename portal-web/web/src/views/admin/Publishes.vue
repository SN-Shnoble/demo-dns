<template>
    <ListPage
        :title="t('admin.publishes.title')"
        :desc="t('admin.publishes.desc')"
        i18n-key="admin.publishes"
        icon-name="Upload"
        :total="meta?.total ?? 0"
        :show-pagination="false"
        @refresh="fetchTasks"
    >
        <template #actions>
            <el-button type="primary" size="small" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ t('admin.publishes.create') }}</span>
            </el-button>
            <el-button
                type="warning"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchRetry"
            >
                <span>{{ t('admin.publishes.batchRetry') }} ({{ selected.length }})</span>
            </el-button>
            <el-button
                type="danger"
                plain
                size="small"
                :disabled="selected.length === 0"
                @click="handleBatchCancel"
            >
                <span>{{ t('admin.publishes.batchCancel') }} ({{ selected.length }})</span>
            </el-button>
            <el-button type="info" plain size="small" @click="handleCleanup">
                <el-icon class="el-icon--left"><Delete /></el-icon>
                <span>{{ t('admin.publishes.cleanup') }}</span>
            </el-button>
        </template>

        <el-row :gutter="16" class="stat-row">
            <el-col :span="6" v-for="s in summaries" :key="s.label">
                <div class="stat-card" :class="`stat-${s.tone}`">
                    <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </div>
            </el-col>
        </el-row>

        <el-table :data="tasks" stripe v-loading="loading" @selection-change="onSelectionChange" style="margin-top:12px">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Upload /></el-icon>
                    <p class="empty-title">{{ t('admin.publishes.noTasks') }}</p>
                    <p class="empty-desc">{{ t('admin.publishes.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column type="selection" width="48" />
            <el-table-column prop="message" :label="t('admin.publishes.message')" min-width="280" />
            <el-table-column :label="t('admin.publishes.status')" width="120">
                <template #default="{ row }">
                    <el-tag :type="row.status === 'completed' ? 'success' : row.status === 'failed' ? 'danger' : row.status === 'cancelled' ? 'info' : 'warning'" size="small" effect="light">{{ row.status }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.publishes.progress')" width="140">
                <template #default="{ row }">
                    <el-progress :percentage="row.target_node_count > 0 ? Math.round(row.applied_node_count / row.target_node_count * 100) : 0" :status="row.status === 'failed' ? 'exception' : row.status === 'completed' ? 'success' : ''" :width="80" type="circle" />
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.publishes.nodes')" width="100">
                <template #default="{ row }">{{ row.applied_node_count }}/{{ row.target_node_count }}</template>
            </el-table-column>
            <el-table-column :label="t('admin.publishes.errors')" width="80">
                <template #default="{ row }">
                    <el-tag v-if="row.failed_node_count > 0" type="danger" size="small" effect="light">{{ row.failed_node_count }}</el-tag>
                    <span v-else style="color:#909399">-</span>
                </template>
            </el-table-column>
            <el-table-column :label="t('admin.publishes.time')" width="170">
                <template #default="{ row }">{{ formatTime(row.queued_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('admin.publishes.actions')" width="220" fixed="right">
                <template #default="{ row }">
                    <el-button v-if="row.status === 'failed'" size="small" type="warning" plain :loading="retrying === row.id" @click="handleRetry(row.id)">{{ t('admin.publishes.retry') }}</el-button>
                    <el-button v-if="['queued', 'in_progress'].includes(row.status)" size="small" type="danger" plain @click="handleCancel(row.id)">{{ t('admin.publishes.cancel') }}</el-button>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <!-- Create Task Dialog -->
    <el-dialog v-model="showCreateDialog" :title="t('admin.publishes.create')" width="600">
        <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-position="top">
            <el-form-item :label="t('admin.publishes.message')" prop="message">
                <el-input v-model="createForm.message" type="textarea" :rows="3" maxlength="500" show-word-limit :placeholder="t('admin.publishes.messagePlaceholder')" />
            </el-form-item>
            <el-form-item :label="t('admin.publishes.targetScope')">
                <el-select v-model="createForm.target_scope" style="width:100%">
                    <el-option :label="t('admin.publishes.allNodes')" value="all_nodes" />
                    <el-option :label="t('admin.publishes.specificNodes')" value="specific_nodes" />
                    <el-option :label="t('admin.publishes.allProfiles')" value="all_profiles" />
                </el-select>
            </el-form-item>
            <el-form-item v-if="createForm.target_scope === 'specific_nodes'" :label="t('admin.publishes.selectNodes')">
                <el-select v-model="createForm.target_node_ids" multiple filterable style="width:100%">
                    <el-option v-for="n in availableNodes" :key="n.id" :label="n.node_name" :value="n.id" />
                </el-select>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showCreateDialog = false">{{ t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="creating" @click="handleCreate">{{ t('common.save') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Delete, Plus, Upload } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const tasks = ref([])
const meta = ref({})
const retrying = ref(null)
const selected = ref([])
const showCreateDialog = ref(false)
const creating = ref(false)
const loading = ref(false)
const createFormRef = ref(null)
const availableNodes = ref([])

const createForm = reactive({
    message: '',
    config_version_id: '',
    target_scope: 'all_nodes',
    target_node_ids: [],
})

const createRules = {
    message: [{ required: true, message: t('admin.publishes.messageRequired') || 'Task description is required', trigger: 'blur' }],
}

const summaries = computed(() => [
    { label: t('admin.publishes.total'), value: meta.value?.total ?? 0, color: '#0f172a', tone: 'primary' },
    { label: t('admin.publishes.pending'), value: meta.value?.pending ?? 0, color: '#e6a23c', tone: 'warning' },
    { label: t('admin.publishes.completed'), value: meta.value?.completed ?? 0, color: '#67c23a', tone: 'success' },
    { label: t('admin.publishes.failed'), value: meta.value?.failed ?? 0, color: '#f56c6c', tone: 'danger' },
])

const formatTime = (ts) => {
    if (!ts) return '-'
    return new Date(ts).toLocaleString()
}

const onSelectionChange = (rows) => { selected.value = rows }

const handleRetry = async (taskId) => {
    retrying.value = taskId
    try {
        const { data } = await client.post(`/admin/publishes/${taskId}/retry`)
        ElMessage.success(data.data?.message || t('admin.publishes.retrySuccess'))
        await fetchTasks()
    } catch {
        ElMessage.error(t('admin.publishes.retryFailed'))
    } finally {
        retrying.value = null
    }
}

const handleCancel = async (taskId) => {
    try {
        await ElMessageBox.confirm(t('admin.publishes.confirmCancel'), t('common.confirm'), { type: 'warning' })
        await client.post(`/admin/publishes/${taskId}/cancel`)
        ElMessage.success(t('admin.publishes.cancelled'))
        await fetchTasks()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.publishes.cancelFailed'))
    }
}

const handleBatchRetry = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.publishes.confirmBatchRetry', { count: selected.value.length }),
            t('admin.publishes.confirmBatchRetryTitle'),
            { type: 'warning' },
        )
        const ids = selected.value.map((t) => t.id)
        const { data } = await client.post('/admin/publishes/batch-retry', { ids })
        ElMessage.success(t('admin.publishes.batchRetried', { count: data.data.retried }))
        await fetchTasks()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.publishes.batchRetryFailed'))
    }
}

const handleBatchCancel = async () => {
    if (selected.value.length === 0) return
    try {
        await ElMessageBox.confirm(
            t('admin.publishes.confirmBatchCancel', { count: selected.value.length }),
            t('admin.publishes.confirmBatchCancelTitle'),
            { type: 'warning' },
        )
        const ids = selected.value.map((t) => t.id)
        const { data } = await client.post('/admin/publishes/batch-cancel', { ids })
        ElMessage.success(t('admin.publishes.batchCancelled', { count: data.data.cancelled }))
        await fetchTasks()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.publishes.batchCancelFailed'))
    }
}

const handleCleanup = async () => {
    try {
        await ElMessageBox.confirm(
            t('admin.publishes.confirmCleanup'),
            t('admin.publishes.confirmCleanupTitle'),
            { type: 'warning' },
        )
        const { data } = await client.post('/admin/publishes/cleanup-completed', { older_than_days: 30 })
        ElMessage.success(t('admin.publishes.cleanupCompleted', { count: data.data.deleted }))
        await fetchTasks()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('admin.publishes.cleanupFailed'))
    }
}

const fetchTasks = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/publishes')
        tasks.value = data.data ?? []
        meta.value = data.meta ?? {}
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const fetchAvailableNodes = async () => {
    try {
        const { data } = await client.get('/admin/nodes')
        availableNodes.value = data.data ?? []
    } catch {
        availableNodes.value = []
    }
}

const openCreateDialog = () => {
    createForm.message = ''
    createForm.config_version_id = crypto.randomUUID()
    createForm.target_scope = 'all_nodes'
    createForm.target_node_ids = []
    showCreateDialog.value = true
}

const handleCreate = async () => {
    const valid = await createFormRef.value.validate().catch(() => false)
    if (!valid) return

    creating.value = true
    try {
        const payload = {
            message: createForm.message,
            config_version_id: createForm.config_version_id,
            target_scope: createForm.target_scope,
        }
        if (createForm.target_scope === 'specific_nodes' && createForm.target_node_ids.length > 0) {
            payload.target_node_ids = createForm.target_node_ids
        }
        await client.post('/admin/publishes', payload)
        ElMessage.success(t('admin.publishes.createSuccess') || 'Task created successfully')
        showCreateDialog.value = false
        await fetchTasks()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('admin.publishes.createFailed') || 'Failed to create task')
    } finally {
        creating.value = false
    }
}

onMounted(() => {
    fetchTasks()
    fetchAvailableNodes()
})
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }

.stat-row { margin-bottom: 16px; }
.stat-card {
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #edf2f7;
    padding: 18px 16px;
    text-align: center;
    transition: all 0.2s;
}
.stat-card:hover { box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); transform: translateY(-1px); }
.stat-card.stat-danger { background: linear-gradient(135deg, #fef2f2, #fff5f5); border-color: #fecaca; }
.stat-card.stat-warning { background: linear-gradient(135deg, #fffbeb, #fff7e6); border-color: #fde68a; }
.stat-card.stat-success { background: linear-gradient(135deg, #f0fdf4, #f7fee7); border-color: #bbf7d0; }
.stat-card.stat-primary { background: linear-gradient(135deg, #eff6ff, #f8fafc); border-color: #bfdbfe; }
.stat-value { font-size: 28px; font-weight: 800; line-height: 1.1; }
.stat-label { font-size: 13px; color: #64748b; margin-top: 4px; }
</style>
