<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('analytics.title') }}</h2>
                <p>{{ $t('analytics.desc') }}</p>
            </div>
        </div>

        <el-row :gutter="20">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats?.today_queries ?? 0 }}</div>
                    <div class="stat-label">{{ $t('analytics.todayQueries') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value danger">{{ stats?.today_blocked ?? 0 }}</div>
                    <div class="stat-label">{{ $t('analytics.todayBlocked') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats?.period_queries ?? 0 }}</div>
                    <div class="stat-label">{{ $t('analytics.periodQueries') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ quotaPercent }}%</div>
                    <div class="stat-label">{{ $t('analytics.quotaUsed') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="20" style="margin-top:20px">
            <el-col :span="12">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <span>{{ $t('analytics.topDomains') }}</span>
                    </template>
                    <div v-if="topDomains.length === 0" class="empty-chart">{{ $t('analytics.noData') }}</div>
                    <div v-for="(item, idx) in topDomains.slice(0, 10)" :key="idx" class="rank-row">
                        <span class="rank-num">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count }} {{ $t('analytics.queries') }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="chart-card">
                    <template #header>
                        <span>{{ $t('analytics.topBlocked') }}</span>
                    </template>
                    <div v-if="topBlocked.length === 0" class="empty-chart">{{ $t('analytics.noData') }}</div>
                    <div v-for="(item, idx) in topBlocked.slice(0, 10)" :key="idx" class="rank-row">
                        <span class="rank-num danger">{{ idx + 1 }}</span>
                        <span class="rank-domain">{{ item.domain }}</span>
                        <span class="rank-count">{{ item.count }} {{ $t('analytics.blocked') }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </Layout>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const stats = ref(null)
const topDomains = ref([])
const topBlocked = ref([])
const quotaPercent = computed(() => {
    if (!stats.value) return 0
    const used = stats.value.today_queries || 0
    const limit = 300000
    return Math.min(Math.round((used / limit) * 100), 100)
})

onMounted(async () => {
    try {
        const { data } = await client.get('/member/analytics')
        const d = data.data || {}
        stats.value = d
        topDomains.value = d.top_domains || []
        topBlocked.value = d.top_blocked || []
    } catch {}
})
</script>

<style scoped>
.page-header {
    margin-bottom: 24px;
}
.page-header-text h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: var(--color-text);
}
.page-header-text p {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 14px;
}
.stat-card {
    border-radius: var(--radius-lg);
    text-align: center;
    padding: 12px 0;
}
.stat-value {
    font-size: 36px;
    font-weight: 700;
    color: var(--color-primary);
    line-height: 1.2;
}
.stat-value.danger {
    color: var(--color-danger);
}
.stat-label {
    margin-top: 4px;
    font-size: 14px;
    color: var(--color-text-muted);
}
.chart-card {
    border-radius: var(--radius-lg);
}
.rank-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid var(--color-border);
}
.rank-row:last-child {
    border-bottom: none;
}
.rank-num {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-sm);
    background: var(--color-bg-secondary);
    color: var(--color-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 600;
    margin-right: 12px;
}
.rank-num.danger {
    background: var(--color-bg-secondary);
    color: var(--color-danger);
}
.rank-domain {
    flex: 1;
    font-size: 14px;
    color: var(--color-text);
}
.rank-count {
    font-size: 13px;
    color: var(--color-text-muted);
}
.empty-chart {
    text-align: center;
    color: var(--color-text-muted);
    padding: 40px 0;
    font-size: 14px;
}
</style>
