<template>
    <ListPage
        :title="$t('admin.securityData.title')"
        i18n-key="admin.securityData"
        icon-name="Lock"
        :total="total"
        :show-pagination="false"
        @refresh="fetchItems"
    >
        <template #filters>
            <el-input
                v-model="filter.search"
                :placeholder="$t('admin.securityData.searchPlaceholder')"
                style="width:280px"
                size="small"
                clearable
                @keyup.enter="fetchItems"
            >
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
            <el-button size="small" type="primary" @click="fetchItems">
                <el-icon class="el-icon--left"><Search /></el-icon>
                <span>{{ $t('common.search') }}</span>
            </el-button>
        </template>

        <template #actions>
            <el-button size="small" @click="openImportDialog">
                <el-icon class="el-icon--left"><Upload /></el-icon>
                <span>{{ $t('admin.securityData.batchImport') }}</span>
            </el-button>
            <el-button size="small" type="primary" @click="openCreateDialog">
                <el-icon class="el-icon--left"><Plus /></el-icon>
                <span>{{ $t('admin.securityData.addItem') }}</span>
            </el-button>
        </template>

        <el-table v-loading="loading" :data="filteredItems" stripe>
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><Lock /></el-icon>
                    <p class="empty-title">{{ $t('dashboard.noData') }}</p>
                    <p class="empty-desc">{{ $t('admin.securityData.emptyDesc') }}</p>
                </div>
            </template>
            <el-table-column prop="value" :label="$t('admin.securityData.value')" min-width="280" />
            <el-table-column prop="note" :label="$t('admin.securityData.note')" min-width="200" show-overflow-tooltip />
            <el-table-column :label="$t('admin.securityData.enabled')" width="100" align="center">
                <template #default="{ row }">
                    <el-tag size="small" :type="row.enabled ? 'success' : 'info'">
                        {{ row.enabled ? $t('common.yes') : $t('common.no') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.securityData.actions')" width="80" fixed="right">
                <template #default="{ row }">
                    <el-popconfirm
                        :title="$t('admin.securityData.confirmDelete')"
                        @confirm="handleDelete(row)"
                    >
                        <template #reference>
                            <el-button size="small" text type="danger">
                                <el-icon><Delete /></el-icon>
                            </el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>
    </ListPage>

    <el-dialog v-model="showCreate" :title="$t('admin.securityData.addItem')" width="500">
        <el-form :model="form" label-position="top">
            <el-form-item :label="$t('admin.securityData.value')" required>
                <el-input v-model="form.value" maxlength="255" />
            </el-form-item>
            <el-form-item :label="$t('admin.securityData.note')">
                <el-input v-model="form.note" type="textarea" :rows="2" maxlength="500" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showCreate = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="saving" @click="handleCreate">{{ $t('common.save') }}</el-button>
        </template>
    </el-dialog>

    <el-dialog v-model="showImport" :title="$t('admin.securityData.batchImport')" width="500">
        <el-form label-position="top">
            <el-form-item :label="$t('admin.securityData.importHint')">
                <el-input v-model="importText" type="textarea" :rows="10" :placeholder="$t('admin.securityData.importPlaceholder')" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="showImport = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="primary" :loading="importing" @click="handleImport">{{ $t('common.import') }}</el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Lock, Search, Plus, Delete, Upload } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const route = useRoute()
const group = computed(() => String(route.params.group || ''))

const items = ref([])
const loading = ref(false)
const saving = ref(false)
const importing = ref(false)
const total = ref(0)
const filter = reactive({ search: '' })

const showCreate = ref(false)
const showImport = ref(false)
const importText = ref('')
const form = reactive({ value: '', note: '' })

const filteredItems = computed(() => {
    if (!filter.search) return items.value
    const s = filter.search.toLowerCase()
    return items.value.filter((i) => String(i.value).toLowerCase().includes(s) || String(i.note || '').toLowerCase().includes(s))
})

const fetchItems = async () => {
    if (!group.value) return
    loading.value = true
    try {
        const { data } = await client.get(`/admin/security-data/${group.value}`)
        items.value = data.data ?? []
        total.value = data.meta?.total ?? items.value.length
    } catch {
        items.value = []
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const openCreateDialog = () => {
    form.value = ''
    form.note = ''
    showCreate.value = true
}

const handleCreate = async () => {
    if (!form.value) return ElMessage.warning(t('admin.securityData.valueRequired') || 'Value is required')
    saving.value = true
    try {
        await client.post(`/admin/security-data/${group.value}`, form)
        ElMessage.success(t('common.created') || 'Created')
        showCreate.value = false
        await fetchItems()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleDelete = async (row) => {
    try {
        await client.delete(`/admin/security-data/${group.value}/${row.id}`)
        ElMessage.success(t('common.deleted') || 'Deleted')
        await fetchItems()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.deleteFailed') || 'Delete failed')
    }
}

const openImportDialog = () => {
    importText.value = ''
    showImport.value = true
}

const handleImport = async () => {
    if (!importText.value.trim()) return ElMessage.warning(t('admin.securityData.importEmpty') || 'Empty input')
    const values = importText.value.split(/[\n,]+/).map((s) => s.trim()).filter(Boolean)
    importing.value = true
    try {
        const { data } = await client.post(`/admin/security-data/${group.value}/import`, { values })
        ElMessage.success(t('admin.securityData.imported', { count: data.data?.imported ?? values.length }) || 'Imported')
        showImport.value = false
        await fetchItems()
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.importFailed') || 'Import failed')
    } finally {
        importing.value = false
    }
}

onMounted(fetchItems)
</script>

<style scoped>
.empty-state { padding: 40px 0; text-align: center; color: #64748b; }
.empty-icon { font-size: 48px; color: #cbd5e1; margin-bottom: 12px; }
.empty-title { font-size: 16px; font-weight: 600; color: #475569; margin: 0 0 4px; }
.empty-desc { font-size: 13px; color: #94a3b8; margin: 0; }
</style>
