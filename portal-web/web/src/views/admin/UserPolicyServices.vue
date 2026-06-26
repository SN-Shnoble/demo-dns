<template>
    <ListPage
        :title="$t('admin.userPolicyServices.title')"
        :desc="$t('admin.userPolicyServices.desc')"
        i18n-key="admin.userPolicyServices"
        icon-name="Tickets"
        :total="meta?.total ?? users.length"
        :show-pagination="true"
        :current-page="userPage"
        :page-size="perPage"
        :total-pages="totalPages"
        @refresh="fetchUsers"
        @page-change="handlePageChange"
    >
        <template #actions>
            <el-button @click="fetchPlans">{{ $t('common.refresh') }}</el-button>
        </template>

        <!-- 套餐 Tab 切换 -->
        <el-tabs v-model="activePlanCode" type="card" class="plan-tabs" @tab-change="onTabChange">
            <el-tab-pane
                v-for="plan in plans"
                :key="plan.code"
                :label="plan.name"
                :name="plan.code"
            >
                <template #label>
                    <span class="tab-label">
                        {{ plan.name }}
                        <el-tag size="small" type="success" class="tab-count">{{ plan.user_count }}</el-tag>
                    </span>
                </template>
            </el-tab-pane>
        </el-tabs>

        <!-- 用户表格 -->
        <el-table v-loading="userLoading" :data="users" stripe>
            <template #empty>
                <div class="empty-state">
                    <el-icon class="empty-icon"><User /></el-icon>
                    <p class="empty-title">{{ $t('admin.userPolicyServices.noUsers') }}</p>
                    <p class="empty-desc">{{ $t('admin.userPolicyServices.emptyUsersDesc') }}</p>
                </div>
            </template>
            <el-table-column :label="$t('admin.userPolicyServices.userId')" width="80">
                <template #default="{ row }">{{ row.uid }}</template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.username')" prop="username" min-width="160" show-overflow-tooltip />
            <el-table-column :label="$t('admin.userPolicyServices.email')" prop="email" min-width="220" show-overflow-tooltip />
            <el-table-column :label="$t('admin.userPolicyServices.plan')" width="140">
                <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.plan_code }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.status')" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.status === 'active'" type="success" size="small" effect="light">
                        {{ $t('admin.userPolicyServices.active') }}
                    </el-tag>
                    <el-tag v-else type="info" size="small" effect="plain">
                        {{ row.status }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="$t('admin.userPolicyServices.createdAt')" width="170">
                <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
        </el-table>
    </ListPage>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Tickets, User } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'
import { formatDateTime } from '@/composables/useDateFormat'

const { t } = useI18n()

const plans = ref([])
const users = ref([])
const meta = reactive({})
const loading = ref(false)
const userLoading = ref(false)
const activePlanCode = ref('')
const userPage = ref(1)
const perPage = ref(20)
const totalPages = ref(1)

const formatTime = (ts) => formatDateTime(ts)

const fetchPlans = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/policy/plans')
        const list = data.data ?? []
        plans.value = list
        Object.assign(meta, data.meta ?? {})

        // 默认选中第一个有用户的套餐
        if (list.length > 0) {
            const firstWithUsers = list.find((p) => p.user_count > 0) || list[0]
            activePlanCode.value = firstWithUsers.code
            userPage.value = 1
            await fetchUsers()
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load plans')
    } finally {
        loading.value = false
    }
}

const fetchUsers = async () => {
    if (!activePlanCode.value) return
    userLoading.value = true
    try {
        const { data } = await client.get('/admin/users', {
            params: {
                plan_code: activePlanCode.value,
                per_page: perPage.value,
                page: userPage.value,
            },
        })
        users.value = data.data ?? []
        const m = data.meta ?? {}
        totalPages.value = Math.ceil((m.total || 0) / (m.per_page || perPage.value))
    } catch (err) {
        ElMessage.error(err.response?.data?.message || err.message || 'Failed to load users')
        users.value = []
    } finally {
        userLoading.value = false
    }
}

const onTabChange = async (tabName) => {
    activePlanCode.value = tabName
    userPage.value = 1
    await fetchUsers()
}

const handlePageChange = (page) => {
    userPage.value = page
    fetchUsers()
}

onMounted(() => {
    fetchPlans()
})
</script>

<style scoped>
.plan-tabs {
    margin-bottom: 16px;
}
.tab-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.tab-count {
    font-size: 11px;
    padding: 0 6px;
    line-height: 18px;
    min-height: 18px;
}
.empty-state {
    padding: 40px 0;
    text-align: center;
    color: #64748b;
}
.empty-icon {
    font-size: 48px;
    color: #cbd5e1;
    margin-bottom: 12px;
}
.empty-title {
    font-size: 16px;
    font-weight: 600;
    color: #475569;
    margin: 0 0 4px;
}
.empty-desc {
    font-size: 13px;
    color: #94a3b8;
    margin: 0;
}
</style>
