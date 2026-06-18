<template>
    <Layout>
        <div class="page-header">
            <div class="page-header-text">
                <h2>{{ $t('membership.title') }}</h2>
                <p>{{ $t('membership.desc') }}</p>
            </div>
        </div>

        <!-- Current Plan -->
        <el-row :gutter="20">
            <el-col :span="8">
                <el-card shadow="never" class="plan-card" :class="currentPlan">
                    <div class="plan-badge" v-if="currentPlan === 'free'">Free</div>
                    <div class="plan-badge pro-badge" v-if="currentPlan === 'pro'">Pro</div>
                    <div class="plan-name">{{ $t(`membership.plans.${currentPlan}`) }}</div>
                    <div class="plan-desc">{{ $t(`membership.plans.${currentPlan}Desc`) }}</div>
                    <div class="plan-quota">{{ $t(`membership.plans.${currentPlan}Quota`) }}</div>
                    <div v-if="currentPlan === 'free'" style="margin-top:16px">
                        <el-button type="primary" round @click="upgradeDialog = true">
                            {{ $t('membership.upgrade') }} Pro
                        </el-button>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="16">
                <el-card shadow="never" class="quota-card">
                    <template #header>
                        <span>{{ $t('membership.quotaProgress') }}</span>
                    </template>
                    <div v-if="currentPlan === 'free'">
                        <div class="quota-info">
                            <span>{{ stats?.today_queries ?? 0 }} {{ $t('membership.queriesUsed') }}</span>
                            <span>{{ $t('membership.of') }} 300,000</span>
                        </div>
                        <el-progress :percentage="quotaPercent" :color="quotaPercent > 80 ? '#f56c6c' : '#409eff'" />
                        <el-alert
                            v-if="quotaPercent >= 100"
                            :title="$t('membership.overQuota')"
                            type="warning"
                            :closable="false"
                            style="margin-top:12px"
                        />
                        <el-alert
                            v-else
                            :title="$t('membership.quotaNormal')"
                            type="info"
                            :closable="false"
                            show-icon
                            style="margin-top:12px"
                        />
                    </div>
                    <div v-else>
                        <el-result icon="success" :title="$t('membership.unlimited')" :sub-title="$t('membership.noQuotaRestrictions')">
                            <template #extra>
                                <el-tag type="success" size="large">{{ $t('membership.active') }}</el-tag>
                            </template>
                        </el-result>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- Plan Options -->
        <el-row :gutter="20" style="margin-top:20px">
            <el-col :span="8">
                <el-card shadow="never" class="plan-option-card">
                    <div class="plan-option-name">{{ $t('membership.free') }}</div>
                    <div class="plan-option-price">$0<span class="period">{{ $t('membership.perMonth') }}</span></div>
                    <ul>
                        <li>{{ $t('membership.freeQuota') }}</li>
                        <li>{{ $t('membership.basicSecurity') }}</li>
                        <li>{{ $t('membership.basicPrivacy') }}</li>
                        <li>{{ $t('membership.upTo2Profiles') }}</li>
                    </ul>
                    <el-tag v-if="currentPlan === 'free'" type="success">{{ $t('membership.current') }}</el-tag>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="plan-option-card recommended">
                    <div class="recommended-badge">{{ $t('membership.recommended') }}</div>
                    <div class="plan-option-name">{{ $t('membership.pro') }}</div>
                    <div class="plan-option-price">$3.99<span class="period">{{ $t('membership.perMonth') }}</span></div>
                    <ul>
                        <li>{{ $t('membership.unlimited') }} {{ $t('membership.queries') }}</li>
                        <li>{{ $t('membership.advancedSecurity') }}</li>
                        <li>{{ $t('membership.advancedPrivacy') }}</li>
                        <li>{{ $t('membership.parentalControl') }}</li>
                        <li>{{ $t('membership.unlimitedProfiles') }}</li>
                        <li>{{ $t('membership.queryLogs') }}</li>
                    </ul>
                    <el-button v-if="currentPlan !== 'pro'" type="primary" round @click="upgradeDialog = true">
                        {{ $t('membership.upgrade') }}
                    </el-button>
                    <el-tag v-else type="success">{{ $t('membership.current') }}</el-tag>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="plan-option-card">
                    <div class="plan-option-name">{{ $t('membership.business') }}</div>
                    <div class="plan-option-price">$5<span class="period">{{ $t('membership.perEmployeeMonth') }}</span></div>
                    <ul>
                        <li>{{ $t('membership.everythingInPro') }}</li>
                        <li>{{ $t('membership.teamManagement') }}</li>
                        <li>{{ $t('membership.employeeBlocks') }}</li>
                        <li>{{ $t('membership.prioritySupport') }}</li>
                    </ul>
                    <el-button type="primary" round @click="upgradeDialog = true">{{ $t('membership.buyNow') || 'Buy Now' }}</el-button>
                </el-card>
            </el-col>
        </el-row>

        <!-- Upgrade Dialog -->
        <el-dialog v-model="upgradeDialog" :title="$t('membership.upgradePlan')" width="500">
            <el-radio-group v-model="selectedPlan" style="width:100%">
                <el-radio value="pro_monthly" border style="width:100%;margin-bottom:12px;padding:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;width:100%">
                        <div>
                            <div style="font-weight:600">Pro Monthly</div>
                            <div style="font-size:13px;color:#909399">Unlimited queries, cancel anytime</div>
                        </div>
                        <div style="font-weight:700;color:#409eff">$3.99/mo</div>
                    </div>
                </el-radio>
                <el-radio value="pro_yearly" border style="width:100%;padding:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;width:100%">
                        <div>
                            <div style="font-weight:600">Pro Yearly</div>
                            <div style="font-size:13px;color:#909399">Unlimited queries, save 17%</div>
                        </div>
                        <div style="font-weight:700;color:#409eff">$39.99/yr</div>
                    </div>
                </el-radio>
            </el-radio-group>
            <template #footer>
                <el-button @click="upgradeDialog = false">{{ $t('membership.cancel') }}</el-button>
                <el-button type="primary" :loading="upgrading" @click="handleUpgrade">{{ $t('membership.subscribe') }}</el-button>
            </template>
        </el-dialog>

        <!-- Orders -->
        <el-card shadow="never" style="margin-top:20px;border-radius:12px">
            <template #header><span>{{ $t('membership.orders') }}</span></template>
            <el-table :data="orders" stripe :empty-text="$t('membership.noOrders')">
                <el-table-column prop="created_at" :label="$t('membership.date')" width="160" />
                <el-table-column prop="description" :label="$t('membership.description')" />
                <el-table-column prop="amount" :label="$t('membership.amount')" width="120">
                    <template #default="{ row }">${{ (row.amount_minor / 100).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column prop="status" :label="$t('membership.status') || 'Status'" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'paid' ? 'success' : 'warning'" size="small">
                            {{ row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>
    </Layout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const currentPlan = ref('free')
const stats = ref(null)
const orders = ref([])
const upgradeDialog = ref(false)
const upgrading = ref(false)
const selectedPlan = ref('pro_monthly')

const quotaPercent = computed(() => {
    if (!stats.value) return 0
    const used = stats.value.today_queries || 0
    return Math.min(Math.round((used / 300000) * 100), 100)
})

const fetchMembership = async () => {
    try {
        const { data } = await client.get('/member/membership')
        const d = data.data || {}
        currentPlan.value = d.plan || 'free'
        stats.value = d.stats
        orders.value = d.orders || []
    } catch {}
}

const handleUpgrade = async () => {
    upgrading.value = true
    try {
        await client.post('/member/upgrade', { plan: selectedPlan.value })
        ElMessage.success(t('membership.upgradeSuccessful'))
        upgradeDialog.value = false
        await fetchMembership()
    } catch {
        ElMessage.error(t('membership.upgradeFailed'))
    } finally {
        upgrading.value = false
    }
}

onMounted(fetchMembership)
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
.plan-card {
    border-radius: var(--radius-lg);
    text-align: center;
    padding: 12px;
    position: relative;
    overflow: hidden;
}
.plan-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: var(--color-text-muted);
    color: #fff;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}
.pro-badge {
    background: var(--color-primary);
}
.plan-name {
    font-size: 22px;
    font-weight: 700;
    margin-top: 8px;
    color: var(--color-text);
}
.plan-desc {
    font-size: 14px;
    color: var(--color-text-muted);
    margin: 4px 0;
}
.plan-quota {
    font-size: 18px;
    color: var(--color-primary);
    font-weight: 600;
    margin-top: 8px;
}
.quota-card {
    border-radius: var(--radius-lg);
}
.quota-info {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: var(--color-text-secondary);
    margin-bottom: 8px;
}
.plan-option-card {
    border-radius: var(--radius-lg);
    text-align: center;
    padding: 8px;
    position: relative;
    transition: transform 0.2s;
}
.plan-option-card:hover {
    transform: translateY(-4px);
}
.plan-option-card.recommended {
    border: 2px solid var(--color-primary);
}
.recommended-badge {
    position: absolute;
    top: -1px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--color-primary);
    color: #fff;
    padding: 2px 16px;
    border-radius: 0 0 var(--radius-md) var(--radius-md);
    font-size: 12px;
    font-weight: 600;
}
.plan-option-name {
    font-size: 20px;
    font-weight: 700;
    margin-top: 16px;
    color: var(--color-text);
}
.plan-option-price {
    font-size: 28px;
    font-weight: 700;
    color: var(--color-primary);
    margin: 8px 0;
}
.plan-option-price .period {
    font-size: 14px;
    font-weight: 400;
    color: var(--color-text-muted);
}
.plan-option-card ul {
    list-style: none;
    padding: 0;
    margin: 16px 0;
    text-align: left;
}
.plan-option-card ul li {
    padding: 6px 0;
    font-size: 14px;
    color: var(--color-text-secondary);
}
.plan-option-card ul li::before {
    content: '✓ ';
    color: var(--color-success);
    font-weight: 700;
}
</style>
