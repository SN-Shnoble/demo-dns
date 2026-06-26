<template>
    <ListPage
        :title="$t('admin.securityData.title')"
        i18n-key="admin.securityData"
        icon-name="Lock"
        :total="0"
        :show-pagination="false"
    >
        <el-row :gutter="16" v-loading="loading">
            <el-col v-for="card in cards" :key="card.path" :xs="24" :sm="12" :md="8" :lg="6">
                <el-card shadow="hover" class="group-card" @click="goTo(card.path)">
                    <div class="card-body">
                        <el-icon class="card-icon" :color="card.color"><component :is="card.icon" /></el-icon>
                        <div class="card-info">
                            <div class="card-title">{{ $t(card.titleKey) }}</div>
                            <div class="card-count">{{ card.count }} {{ $t('admin.securityData.items') }}</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </ListPage>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Lock, Position, Box, ChatDotRound, Folder, Film } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()
const router = useRouter()

const loading = ref(false)
const summary = ref({})

const cards = ref([
    { path: 'dynamic-dns',    titleKey: 'admin.securityData.groupDdns',         icon: 'Position',      color: '#67c23a', count: 0 },
    { path: 'parked-domains', titleKey: 'admin.securityData.groupParkedDomains', icon: 'Box',          color: '#a0c4ff', count: 0 },
    { path: 'tld-blacklist',  titleKey: 'admin.securityData.groupTldBlacklist',  icon: 'ChatDotRound',  color: '#f56c6c', count: 0 },
    { path: 'allow-lists',    titleKey: 'admin.securityData.groupAllowLists',    icon: 'Folder',        color: '#67c23a', count: 0 },
    { path: 'block-lists',    titleKey: 'admin.securityData.groupBlockLists',    icon: 'Film',          color: '#e6a23c', count: 0 },
])

const fetchSummary = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/security-data/summary')
        summary.value = data.data || {}
        cards.value.forEach((c) => { c.count = summary.value[c.path] || 0 })
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const goTo = (path) => {
    router.push({ name: 'AdminSecurityDataItem', params: { group: path } })
}

onMounted(fetchSummary)
</script>

<style scoped>
.group-card { margin-bottom: 16px; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
.group-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
.card-body { display: flex; align-items: center; gap: 16px; }
.card-icon { font-size: 36px; }
.card-info { flex: 1; }
.card-title { font-size: 16px; font-weight: 600; color: #1f2937; margin-bottom: 4px; }
.card-count { font-size: 13px; color: #6b7280; }
</style>
