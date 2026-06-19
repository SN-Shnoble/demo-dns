<template>
    <Layout>
        <el-button @click="$router.push('/user/profiles')" style="margin-bottom:16px">← {{ $t('profileDetail.back') }}</el-button>

        <el-card v-if="profile">
            <template #header>
                <span>{{ profile.name }}</span>
                <el-tag :type="profile.status === 'active' ? 'success' : 'info'" style="margin-left:8px">
                    {{ profile.status }}
                </el-tag>
                <el-button size="small" type="success" style="float:right" :loading="publishing" @click="handlePublish">
                    {{ $t('profileDetail.publish') }}
                </el-button>
            </template>

            <div style="margin-bottom:16px">
                <span style="color:#64748b;font-size:13px">{{ $t('profileDetail.status') }}:</span>
                <el-tag :type="profile.status === 'active' ? 'success' : 'info'" size="small" style="margin-left:4px">{{ profile.status }}</el-tag>
            </div>
            <el-descriptions :column="2" border>
                <el-descriptions-item :label="$t('profileDetail.domain')">{{ profile.domain }}</el-descriptions-item>
                <el-descriptions-item :label="$t('profileDetail.matchType')">{{ profile.match_type }}</el-descriptions-item>
            </el-descriptions>

            <div class="section-header" style="margin-top:24px">
                <h3>{{ $t('profileDetail.addRule') }}</h3>
                <div>
                    <el-button size="small" type="danger" plain :disabled="selectedRules.length === 0" @click="handleBatchDeleteRules">
                        {{ $t('profileDetail.delete') }} ({{ selectedRules.length }})
                    </el-button>
                    <el-button size="small" type="primary" @click="showAddRuleDialog = true">{{ $t('profileDetail.addRule') }}</el-button>
                </div>
            </div>
            <el-table :data="profileRules" stripe @selection-change="onRulesSelectionChange" :empty-text="$t('profileDetail.noRules')">
                <el-table-column type="selection" width="48" />
                <el-table-column prop="domain" :label="$t('profileDetail.domain')" />
                <el-table-column prop="list_type" :label="$t('profileDetail.action')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.list_type === 'deny' ? 'danger' : 'success'" size="small">{{ row.list_type === 'deny' ? $t('profileDetail.blocked') : $t('profileDetail.allowed') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="match_type" :label="$t('profileDetail.matchType')" width="100" />
                <el-table-column :label="$t('profileDetail.enabled')" width="80">
                    <template #default="{ row }">
                        <el-tag v-if="row.enabled" type="success" size="small">{{ $t('common.yes') }}</el-tag>
                        <el-tag v-else type="info" size="small">{{ $t('common.no') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('profileDetail.actions')" width="160">
                    <template #default="{ row }">
                        <el-button size="small" @click="openEditRuleDialog(row)">{{ $t('profileDetail.edit') }}</el-button>
                        <el-button size="small" type="danger" @click="handleDeleteRule(row.id, row.profile_id)">{{ $t('profileDetail.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-alert v-else-if="!loading" :title="$t('profileDetail.noProfile')" type="warning" show-icon />

        <el-dialog v-model="showAddRuleDialog" :title="$t('profileDetail.addRule')" width="500">
            <el-form ref="ruleFormRef" :model="ruleForm" label-position="top">
                <el-form-item :label="$t('profileDetail.domain')" prop="domain" :rules="[{ required: true }]">
                    <el-input v-model="ruleForm.domain" :placeholder="$t('profileDetail.domainPlaceholder')" />
                </el-form-item>
                <el-form-item :label="$t('profileDetail.matchType')">
                    <el-select v-model="ruleForm.match_type">
                        <el-option :label="$t('profileDetail.exact')" value="exact" />
                        <el-option :label="$t('profileDetail.suffix')" value="suffix" />
                        <el-option :label="$t('profileDetail.wildcard')" value="wildcard" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('profileDetail.action')">
                    <el-radio-group v-model="ruleForm.list_type">
                        <el-radio value="allow">{{ $t('profileDetail.allowed') }}</el-radio>
                        <el-radio value="deny">{{ $t('profileDetail.blocked') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddRuleDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="ruleSaving" @click="handleAddRule">{{ $t('common.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="showEditRuleDialog" :title="$t('profileDetail.edit')" width="500">
            <el-form ref="editRuleFormRef" :model="editRuleForm" label-position="top">
                <el-form-item :label="$t('profileDetail.domain')" prop="domain" :rules="[{ required: true }]">
                    <el-input v-model="editRuleForm.domain" />
                </el-form-item>
                <el-form-item :label="$t('profileDetail.matchType')">
                    <el-select v-model="editRuleForm.match_type">
                        <el-option :label="$t('profileDetail.exact')" value="exact" />
                        <el-option :label="$t('profileDetail.suffix')" value="suffix" />
                        <el-option :label="$t('profileDetail.wildcard')" value="wildcard" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('profileDetail.enabled')">
                    <el-switch v-model="editRuleForm.enabled" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEditRuleDialog = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="editRuleSaving" @click="handleEditRuleSave">{{ $t('common.save') }}</el-button>
            </template>
        </el-dialog>
    </Layout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'
import Layout from '@/components/Layout.vue'

const { t } = useI18n()
const route = useRoute()
const profile = ref(null)
const profileRules = ref([])
const loading = ref(true)
const publishing = ref(false)
const showAddRuleDialog = ref(false)
const showEditRuleDialog = ref(false)
const ruleSaving = ref(false)
const editRuleSaving = ref(false)
const selectedRules = ref([])
const ruleFormRef = ref(null)
const editRuleFormRef = ref(null)
const ruleForm = ref({ domain: '', match_type: 'exact', list_type: 'deny' })
const editRuleForm = ref({ id: null, profile_id: null, domain: '', match_type: 'exact', enabled: true })

const fetchData = async () => {
    try {
        const id = route.params.id
        const [profileRes, rulesRes] = await Promise.all([
            client.get(`/user/profiles/${id}`),
            client.get(`/user/profiles/${id}/rules`),
        ])
        profile.value = profileRes.data.data
        profileRules.value = rulesRes.data.data ?? []
    } catch {
        ElMessage.error(t('common.loadFailed'))
    } finally {
        loading.value = false
    }
}

const handlePublish = async () => {
    publishing.value = true
    try {
        await client.post(`/user/profiles/${route.params.id}/publish`)
        ElMessage.success(t('common.saved'))
        await fetchData()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        publishing.value = false
    }
}

const onRulesSelectionChange = (rows) => { selectedRules.value = rows }

const handleAddRule = async () => {
    const valid = await ruleFormRef.value.validate().catch(() => false)
    if (!valid) return
    ruleSaving.value = true
    try {
        await client.post(`/user/profiles/${route.params.id}/rules`, ruleForm.value)
        ElMessage.success(t('profileDetail.ruleAdded'))
        showAddRuleDialog.value = false
        ruleForm.value = { domain: '', match_type: 'exact', list_type: 'deny' }
        await fetchData()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        ruleSaving.value = false
    }
}

const handleDeleteRule = async (ruleId, profileId) => {
    try {
        await ElMessageBox.confirm(t('profileDetail.deleteConfirm'), t('common.confirm'))
        await client.delete(`/user/profiles/${profileId || route.params.id}/rules/${ruleId}`)
        ElMessage.success(t('profileDetail.ruleDeleted'))
        await fetchData()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed'))
    }
}

const openEditRuleDialog = (row) => {
    editRuleForm.value = { id: row.id, profile_id: row.profile_id, domain: row.domain, match_type: row.match_type, enabled: !!row.enabled }
    showEditRuleDialog.value = true
}

const handleEditRuleSave = async () => {
    const valid = await editRuleFormRef.value.validate().catch(() => false)
    if (!valid) return
    editRuleSaving.value = true
    try {
        await client.put(`/user/profiles/${editRuleForm.value.profile_id || route.params.id}/rules/${editRuleForm.value.id}`, {
            domain: editRuleForm.value.domain, match_type: editRuleForm.value.match_type, enabled: editRuleForm.value.enabled,
        })
        ElMessage.success(t('common.saved'))
        showEditRuleDialog.value = false
        await fetchData()
    } catch {
        ElMessage.error(t('common.saveFailed'))
    } finally {
        editRuleSaving.value = false
    }
}

const handleBatchDeleteRules = async () => {
    if (selectedRules.value.length === 0) return
    try {
        await ElMessageBox.confirm(t('profileDetail.deleteConfirm'), t('common.confirm'), { type: 'warning' })
        const ids = selectedRules.value.map((r) => r.id)
        await client.post(`/user/profiles/${route.params.id}/rules/batch-delete`, { ids })
        ElMessage.success(t('profileDetail.ruleDeleted'))
        await fetchData()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('common.deleteFailed'))
    }
}

onMounted(fetchData)
</script>
