<template>
    <ListPage
        :title="$t('admin.userPolicyServices.title') || '用户策略服务管理'"
        :desc="$t('admin.userPolicyServices.desc') || '查看策略分发状态、用户策略快照、节点策略同步'"
        i18n-key="admin.userPolicyServices"
        icon-name="Connection"
        :total="nodes.length"
        :show-pagination="false"
        @refresh="fetchNodes"
    >
        <template #actions>
            <el-button size="small" type="primary" @click="showSnapshotDialog = true">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.userPolicyServices.snapshot') || '生成快照' }}</span>
            </el-button>
        </template>

        <div class="fleet-summary">
            <div class="summary-card">
                <div class="summary-value">{{ meta.latest_published_version ?? 0 }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.latestVersion') || '最新发布版本' }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ meta.total ?? nodes.length }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.fleetTotal') || '总节点数' }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ meta.online ?? 0 }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.fleetOnline') || '在线节点' }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-value">{{ meta.offline ?? 0 }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.fleetOffline') || '离线节点' }}</div>
            </div>
            <div class="summary-card warn">
                <div class="summary-value">{{ meta.out_of_sync ?? 0 }}</div>
                <div class="summary-label">{{ $t('admin.userPolicyServices.fleetOutOfSync') || '未同步节点' }}</div>
            </div>
        </div>

        <el-table v-loading="loading" :data="nodes" stripe style="margin-top:12px">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Connection /></el-icon>
                    <p class="empty-title">{{ $t('admin.userPolicyServices.noData') || '暂无策略节点' }}</p>
                    <p class="empty-desc">{{ $t('admin.userPolicyServices.emptyDesc') || '节点数据将在此显示' }}</p>
                </div>
            </template>
            <el-table-column prop="node_id" :label="$t('admin.userPolicyServices.nodeId') || '节点ID'" min-width="180" show-overflow-tooltip />
            <el-table-column prop="node_name" :label="$t('admin.userPolicyServices.nodeName') || '节点名称'" min-width="160" show-overflow-tooltip />
            <el-table-column prop="region" :label="$t('admin.userPolicyServices.region') || '区域'" width="120" />
            <el-table-column :label="$t('admin.userPolicyServices.status') || '状态'" width="120">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'online'" type="success" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.online') || '在线' }}
                    </el-tag>
                    <el-tag v-else-if="row.status === 'offline'" type="danger" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.offline') || '离线' }}
                    </el-tag>
                    <el-tag v-else type="info" size="small" effect="plain">
                        {{ $t('admin.userPolicyServices.pending') || '待安装' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.policyVersion') || '策略版本'" width="140">
                <template #default="{ row }">v{{ row.policy_version ?? 0 }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.outOfSync') || '是否同步'" width="110">
                <template #default="{ row }">
                    <el-tag v-if="row.out_of_sync" type="warning" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.outOfSyncYes') || '未同步' }}
                    </el-tag>
                    <el-tag v-else type="success" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.outOfSyncNo') || '已同步' }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.lastSync') || '最后同步'" min-width="200">
                <template #default="{ row }">
                    {{ row.last_sync_at ? new Date(row.last_sync_at).toLocaleString() : '—' }}
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showSnapshotDialog" :title="$t('admin.userPolicyServices.snapshot') || '生成快照'" width="480">
        <el-form label-position="top">
            <el-form-item :label="$t('admin.userPolicyServices.snapshotUserPlaceholder') || '用户ID'">
                <el-input v-model="snapshotUserId" :placeholder="$t('admin.userPolicyServices.snapshotUserPlaceholder') || '输入用户ID'" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showSnapshotDialog = false">{{ $t('common.cancel') || '取消' }}</el-button>
            <el-button type="primary" :loading="snapshotting" @click="handleSnapshot">{{ $t('common.confirm') || '确认' }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import { Connection, Plus } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const nodes = ref([])
const meta = reactive({})
const loading = ref(false)
const showSnapshotDialog = ref(false)
const snapshotUserId = ref('')
const snapshotting = ref(false)

const fetchNodes = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/policy/nodes')
        nodes.value = data.data ?? []
        Object.assign(meta, data.meta ?? {})
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load policy nodes')
    } finally {
        loading.value = false
    }
}

const handleSnapshot = async () => {
    if (!snapshotUserId.value) {
        ElMessage.warning(t('admin.userPolicyServices.snapshotUserPlaceholder') || '请输入用户ID')
        return
    }
    snapshotting.value = true
    try {
        await client.post(`/admin/policy/users/${snapshotUserId.value}/snapshot`)
        ElMessage.success(t('admin.userPolicyServices.snapshotSuccess') || '快照已生成')
        showSnapshotDialog.value = false
        snapshotUserId.value = ''
        await fetchNodes()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('admin.userPolicyServices.snapshotFailed') || '快照生成失败')
    } finally {
        snapshotting.value = false
    }
}

onMounted(() => {
    fetchNodes()
})
</script>

<style scoped>
.fleet-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}
.summary-card {
    flex: 1 1 120px;
    min-width: 120px;
    padding: 14px 18px;
    border-radius: 10px;
    background: linear-gradient(180deg, #ffffff, #f8fafc);
    border: 1px solid var(--color-border, #e2e8f0);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.summary-card.warn {
    background: linear-gradient(180deg, #fff7ed, #ffedd5);
    border-color: #fdba74;
}
.summary-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text, #0f172a);
}
.summary-label {
    font-size: 12px;
    color: var(--color-text-muted, #64748b);
}
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
