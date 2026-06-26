<template>
    <ListPage
        :title="$t('admin.protectionPolicies.title')"
        i18n-key="admin.protectionPolicies"
        icon-name="Lock"
        :total="0"
        :show-pagination="false"
    >
        <template #actions>
            <el-button size="small" @click="handleExport">
                <el-icon class="el-icon--left"><Download /></el-icon>
                <span>{{ $t('admin.protectionPolicies.export') }}</span>
            </el-button>
            <el-button size="small" @click="triggerImport">
                <el-icon class="el-icon--left"><Upload /></el-icon>
                <span>{{ $t('admin.protectionPolicies.import') }}</span>
            </el-button>
            <el-button size="small" type="primary" :loading="saving" @click="handleSave">
                <el-icon class="el-icon--left"><Check /></el-icon>
                <span>{{ $t('common.save') }}</span>
            </el-button>
        </template>

        <div v-loading="loading" class="policies-container">
            <!-- DNS Security -->
            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><Connection /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.dnsSecurity') }}</span>
                    </div>
                </template>

                <el-form label-position="left" label-width="220px">
                    <el-form-item :label="$t('admin.protectionPolicies.dnsRebind')">
                        <el-switch v-model="form.dns_rebind.enabled" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.dnsRebindWhitelist')">
                        <el-input
                            v-model="whitelistText"
                            type="textarea"
                            :rows="2"
                            :placeholder="$t('admin.protectionPolicies.dnsRebindWhitelistPlaceholder')"
                        />
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.idnHomograph')">
                        <el-switch v-model="form.idn.enabled" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.typosquatting')">
                        <el-switch v-model="form.typo.enabled" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.typoThreshold')">
                        <el-input-number v-model="form.typo.threshold" :min="1" :max="2" />
                        <span class="form-hint">{{ $t('admin.protectionPolicies.typoThresholdHint') }}</span>
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.dga')">
                        <el-switch v-model="form.dga.enabled" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.dgaEntropy')">
                        <el-input-number v-model="form.dga.entropy_threshold" :min="3.0" :max="5.5" :step="0.1" :precision="1" />
                    </el-form-item>
                    <el-form-item :label="$t('admin.protectionPolicies.dgaDigitRatio')">
                        <el-input-number v-model="form.dga.digit_ratio" :min="0" :max="1" :step="0.05" :precision="2" />
                    </el-form-item>
                </el-form>
            </el-card>

            <!-- Categories -->
            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><Warning /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.threatIntel') }}</span>
                    </div>
                </template>
                <el-checkbox-group v-model="threatSelected" class="cat-grid">
                    <el-checkbox
                        v-for="code in threatCategories"
                        :key="code"
                        :value="code"
                        @change="(val) => toggleCategory(code, val)"
                    >
                        {{ categoryLabel(code) }}
                    </el-checkbox>
                </el-checkbox-group>
            </el-card>

            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><View /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.privacy') }}</span>
                    </div>
                </template>
                <el-checkbox-group v-model="privacySelected" class="cat-grid">
                    <el-checkbox
                        v-for="code in privacyCategories"
                        :key="code"
                        :value="code"
                        @change="(val) => toggleCategory(code, val)"
                    >
                        {{ categoryLabel(code) }}
                    </el-checkbox>
                </el-checkbox-group>
            </el-card>

            <el-card shadow="never" class="policy-card">
                <template #header>
                    <div class="card-header">
                        <el-icon><User /></el-icon>
                        <span>{{ $t('admin.protectionPolicies.family') }}</span>
                    </div>
                </template>
                <el-checkbox-group v-model="familySelected" class="cat-grid">
                    <el-checkbox
                        v-for="code in familyCategories"
                        :key="code"
                        :value="code"
                        @change="(val) => toggleCategory(code, val)"
                    >
                        {{ categoryLabel(code) }}
                    </el-checkbox>
                </el-checkbox-group>
            </el-card>
        </div>
    </ListPage>

    <input ref="fileInput" type="file" accept="application/json" style="display:none" @change="handleImportFile" />
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Lock, Connection, Warning, View, User, Check, Download, Upload } from '@element-plus/icons-vue'
import ListPage from '@/components/ListPage.vue'
import client from '@/api/client'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const fileInput = ref(null)

const threatCategories = ['malware', 'phishing', 'cryptojacking', 'dynamic_dns', 'parked', 'typosquatting', 'dga', 'new_domain']
const privacyCategories = ['tracker', 'analytics', 'telemetry', 'ads']
const familyCategories = ['adult', 'gambling', 'social', 'gaming']

const form = reactive({
    dns_rebind: { enabled: true, whitelist: ['localhost', '*.local'] },
    idn: { enabled: true },
    typo: { enabled: true, threshold: 1 },
    dga: { enabled: true, entropy_threshold: 4.2, digit_ratio: 0.6 },
    categories: {},
})

const whitelistText = ref('')

const threatSelected = computed(() => threatCategories.filter((c) => form.categories[c]?.enabled))
const privacySelected = computed(() => privacyCategories.filter((c) => form.categories[c]?.enabled))
const familySelected = computed(() => familyCategories.filter((c) => form.categories[c]?.enabled))

const categoryLabel = (code) => {
    const map = {
        malware: t('admin.protectionPolicies.catMalware'),
        phishing: t('admin.protectionPolicies.catPhishing'),
        cryptojacking: t('admin.protectionPolicies.catCryptojacking'),
        dynamic_dns: t('admin.protectionPolicies.catDynamicDns'),
        parked: t('admin.protectionPolicies.catParked'),
        typosquatting: t('admin.protectionPolicies.catTyposquatting'),
        dga: t('admin.protectionPolicies.catDga'),
        new_domain: t('admin.protectionPolicies.catNewDomain'),
        tracker: t('admin.protectionPolicies.catTracker'),
        analytics: t('admin.protectionPolicies.catAnalytics'),
        telemetry: t('admin.protectionPolicies.catTelemetry'),
        ads: t('admin.protectionPolicies.catAds'),
        adult: t('admin.protectionPolicies.catAdult'),
        gambling: t('admin.protectionPolicies.catGambling'),
        social: t('admin.protectionPolicies.catSocial'),
        gaming: t('admin.protectionPolicies.catGaming'),
    }
    return map[code] || code
}

const toggleCategory = (code, val) => {
    if (!form.categories[code]) form.categories[code] = { enabled: false }
    form.categories[code].enabled = val
}

const fetchPolicies = async () => {
    loading.value = true
    try {
        const { data } = await client.get('/admin/protection-policies')
        const cfg = data.data || {}
        Object.assign(form.dns_rebind, cfg.dns_rebind || {})
        Object.assign(form.idn, cfg.idn || {})
        Object.assign(form.typo, cfg.typo || {})
        Object.assign(form.dga, cfg.dga || {})
        form.categories = cfg.categories || {}
        whitelistText.value = (form.dns_rebind.whitelist || []).join('\n')
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handleSave = async () => {
    saving.value = true
    try {
        form.dns_rebind.whitelist = whitelistText.value.split(/[\n,]+/).map((s) => s.trim()).filter(Boolean)
        await client.put('/admin/protection-policies', form)
        ElMessage.success(t('common.saveSuccess') || 'Saved')
    } catch (err) {
        ElMessage.error(err.response?.data?.error?.message || t('common.saveFailed') || 'Save failed')
    } finally {
        saving.value = false
    }
}

const handleExport = async () => {
    try {
        const { data } = await client.get('/admin/protection-policies/export')
        const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = `protection-policies-${Date.now()}.json`
        a.click()
        URL.revokeObjectURL(url)
    } catch {
        ElMessage.error(t('common.exportFailed') || 'Export failed')
    }
}

const triggerImport = () => fileInput.value?.click()

const handleImportFile = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    try {
        const text = await file.text()
        const json = JSON.parse(text)
        const config = json.config ?? json
        await client.post('/admin/protection-policies/import', { config })
        ElMessage.success(t('admin.protectionPolicies.importSuccess') || 'Imported')
        await fetchPolicies()
    } catch {
        ElMessage.error(t('common.importFailed') || 'Import failed')
    } finally {
        e.target.value = ''
    }
}

onMounted(fetchPolicies)
</script>

<style scoped>
.policies-container { display: flex; flex-direction: column; gap: 16px; }
.policy-card { border-radius: 8px; }
.card-header { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.cat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px 16px; }
.form-hint { margin-left: 12px; color: #909399; font-size: 12px; }
@media (max-width: 768px) {
    .cat-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
