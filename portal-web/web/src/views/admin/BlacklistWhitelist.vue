<template>
    <ListPage
        :title="$t('admin.blacklistWhitelist.title')"
        :desc="$t('admin.blacklistWhitelist.desc')"
        icon-name="List"
        :total="rows.length"
        :show-pagination="false"
        @refresh="fetchAll"
    >
        <template #actions>
            <el-radio-group v-model="filter.type" @change="fetchAll">
                <el-radio-button value="all">{{ $t('admin.blacklistWhitelist.all') }}</el-radio-button>
                <el-radio-button value="deny">{{ $t('admin.blacklistWhitelist.deny') }}</el-radio-button>
                <el-radio-button value="allow">{{ $t('admin.blacklistWhitelist.allow') }}</el-radio-button>
            </el-radio-group>
            <el-input
                v-model="filter.keyword"
                :placeholder="$t('admin.blacklistWhitelist.searchPlaceholder')"
                clearable
                style="width: 240px"
                @keyup.enter="fetchAll"
            />
            <el-button @click="fetchAll">{{ $t('common.search') }}</el-button>
        </template>

        <el-table v-loading="loading" :data="filteredRows" stripe style="margin-top:12px">
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><List /></el-icon>
                    <p class="empty-title">{{ $t('admin.blacklistWhitelist.noData') }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.blacklistWhitelist.type')" width="100" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.action === 'deny' ? 'danger' : 'success'" size="small" effect="light">
                        {{ row.action === 'deny' ? ($t('admin.blacklistWhitelist.deny')) : ($t('admin.blacklistWhitelist.allow')) }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.domain')" min-width="240" show-overflow-tooltip>
                <template #default="{ row }"><code>{{ row.domain }}</code></template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.matchType')" width="120">
                <template #default="{ row }">{{ row.match_type || 'exact' }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.owner')" min-width="200" show-overflow-tooltip>
                <template #default="{ row }">
                    <div class="user-cell">
                        <span class="cell-primary">{{ row.username || row.user_email || '—' }}</span>
                        <span v-if="row.profile_id" class="cell-sub">profile: {{ row.profile_id }}</span>
                    </div>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.enabled')" width="80" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.enabled ? 'success' : 'info'" size="small" effect="plain">
                        {{ row.enabled ? ($t('common.yes')) : ($t('common.no')) }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.blacklistWhitelist.createdAt')" min-width="180">
                <template #default="{ row }">
                    {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                </template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { List } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const loading = ref(false)
const rows = ref([])
const filter = reactive({ type: 'all', keyword: '' })

const fetchAll = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/blacklist-whitelist', { params: { type: filter.type, keyword: filter.keyword } })
        rows.value = data.data ?? []
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load blacklist/whitelist')
    } finally {
        loading.value = false
    }
}

const filteredRows = computed(() => rows.value)

onMounted(fetchAll)
</script>
