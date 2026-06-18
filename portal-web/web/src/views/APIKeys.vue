<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('apiKeys.title') }}</h2>
                <p>{{ $t('apiKeys.desc') }}</p>
            </div>
        </div>

        <el-alert
            v-if="newKey"
            :title="$t('apiKeys.keyCreated')"
            type="success"
            show-icon
            :closable="false"
            class="key-alert"
        >
            <template #default>
                <p>{{ $t('apiKeys.keyCreatedDesc') }}</p>
                <div class="code-row">
                    <code class="code">{{ newKey.plaintext_key }}</code>
                    <el-button size="small" type="primary" @click="copyNewKey">{{ $t('apiKeys.copy') }}</el-button>
                </div>
            </template>
        </el-alert>

        <el-card shadow="never" style="margin-top:24px">
            <template #header>
                <div class="card-header">
                    <span>{{ $t('apiKeys.list') }} ({{ keys.length }})</span>
                    <el-button type="primary" size="small" @click="showCreate = true">
                        {{ $t('apiKeys.create') }}
                    </el-button>
                </div>
            </template>
            <el-table :data="keys" stripe :empty-text="$t('apiKeys.noKeys')">
                <el-table-column prop="name" :label="$t('apiKeys.name')" min-width="180" />
                <el-table-column :label="$t('apiKeys.prefix')" width="140">
                    <template #default="{ row }">
                        <code>{{ row.key_prefix }}...</code>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('apiKeys.scopes')" min-width="200">
                    <template #default="{ row }">
                        <el-tag v-for="s in (row.scopes ?? [])" :key="s" size="small" style="margin-right:4px">{{ s }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('apiKeys.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('apiKeys.createdAt')" width="160">
                    <template #default="{ row }">
                        <span>{{ row.created_at ? new Date(row.created_at).toLocaleDateString() : '-' }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('apiKeys.actions')" width="100">
                    <template #default="{ row }">
                        <el-button type="danger" size="small" text @click="handleRevoke(row)">
                            {{ $t('apiKeys.revoke') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- Create Dialog -->
        <el-dialog v-model="showCreate" :title="$t('apiKeys.create')" width="480px" :close-on-click-modal="false">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top">
                <el-form-item :label="$t('apiKeys.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="$t('apiKeys.namePlaceholder')" />
                </el-form-item>
                <el-form-item :label="$t('apiKeys.scopes')">
                    <el-checkbox-group v-model="form.scopes">
                        <el-checkbox value="dns:query" :label="$t('apiKeys.scopesDnsQuery')" />
                        <el-checkbox value="logs:read" :label="$t('apiKeys.scopesLogsRead')" />
                        <el-checkbox value="stats:read" :label="$t('apiKeys.scopesStatsRead')" />
                    </el-checkbox-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">{{ $t('apiKeys.cancel') }}</el-button>
                <el-button type="primary" :loading="creating" @click="handleCreate">{{ $t('apiKeys.confirm') }}</el-button>
            </template>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const keys = ref([])
const newKey = ref(null)
const showCreate = ref(false)
const creating = ref(false)
const formRef = ref(null)
const { t } = useI18n()
const form = reactive({
    name: '',
    scopes: ['dns:query', 'logs:read', 'stats:read'],
})

const rules = {
    name: [{ required: true, message: t('apiKeys.nameRequired'), trigger: 'blur' }],
}

const fetchKeys = async () => {
    try {
        const { data } = await client.get('/member/api-keys')
        keys.value = data.data ?? []
    } catch {
        keys.value = []
    }
}

const handleCreate = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    creating.value = true
    try {
        const { data } = await client.post('/member/api-keys', {
            name: form.name,
            scopes: form.scopes,
        })
        newKey.value = data.data
        showCreate.value = false
        form.name = ''
        form.scopes = ['dns:query', 'logs:read', 'stats:read']
        await fetchKeys()
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('apiKeys.failedToCreate'))
    } finally {
        creating.value = false
    }
}

const copyNewKey = () => {
    navigator.clipboard.writeText(newKey.value.plaintext_key).then(() => {
        ElMessage.success(t('apiKeys.copied'))
    }).catch(() => {
        ElMessage.warning(t('apiKeys.copyFailed'))
    })
}

const handleRevoke = async (row) => {
    try {
        await ElMessageBox.confirm(
            t('apiKeys.revokeConfirm').replace('{name}', row.name),
            t('common.confirm'),
            { confirmButtonText: t('apiKeys.revoke'), cancelButtonText: t('common.cancel'), type: 'warning' }
        )
        await client.delete(`/member/api-keys/${row.id}`)
        ElMessage.success(t('apiKeys.apiKeyRevoked'))
        await fetchKeys()
    } catch { /* cancelled */ }
}

onMounted(fetchKeys)
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: #303133;
}
.page-header-text p {
    margin: 0;
    color: #909399;
    font-size: 14px;
}
.key-alert {
    margin-bottom: 20px;
    border-radius: 12px;
}
.key-alert p {
    margin: 8px 0;
    font-size: 14px;
}
.code-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
}
.code {
    flex: 1;
    padding: 8px 12px;
    background: #f1f5f9;
    border-radius: 8px;
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    color: #0f172a;
    word-break: break-all;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
