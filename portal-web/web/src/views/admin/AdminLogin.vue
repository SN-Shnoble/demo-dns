<template>
    <AuthShell
        variant="admin"
        logo="A"
        :brand="$t('admin.title')"
        brand-tagline="Operations and billing control plane"
        eyebrow="Admin Console"
        :title="$t('admin.loginTitle')"
        description="Manage nodes, policies, audit trails, finance and release workflows from one operational cockpit."
        :panel-title="$t('admin.login')"
        panel-subtitle="Restricted to platform operators and finance administrators."
        :highlights="highlights"
    >
        <div class="auth-card">
            <el-alert
                v-if="errorMessage"
                :title="errorMessage"
                type="error"
                show-icon
                :closable="false"
                class="auth-warning"
            />
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="handleLogin">
                <el-form-item :label="$t('admin.email')" prop="email">
                    <el-input v-model="form.email" placeholder="admin@example.com" size="large" class="auth-input">
                        <template #prefix>
                            <el-icon><User /></el-icon>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item :label="$t('auth.password')" prop="password">
                    <el-input v-model="form.password" type="password" show-password size="large" class="auth-input">
                        <template #prefix>
                            <el-icon><Lock /></el-icon>
                        </template>
                    </el-input>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :loading="loading" size="large" class="auth-btn">
                        <span v-if="!loading">{{ $t('admin.loginBtn') }}</span>
                    </el-button>
                </el-form-item>
            </el-form>
            <div class="auth-footer"></div>
        </div>
    </AuthShell>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { User, Lock } from '@element-plus/icons-vue'
import client from '@/api/client'
import AuthShell from '@/components/AuthShell.vue'

const router = useRouter()
const { t } = useI18n()
const formRef = ref(null)
const loading = ref(false)
const errorMessage = ref('')

const form = reactive({
    email: '',
    password: '',
})

const rules = computed(() => ({
    email: [{ required: true, message: t('admin.emailRequired'), trigger: 'blur' }],
    password: [{ required: true, min: 6, message: t('auth.passwordMin'), trigger: 'blur' }],
}))

const highlights = computed(() => ([
    { value: 'Nodes', label: 'Resolver and agent fleet control' },
    { value: 'Audit', label: 'Centralized audit and query visibility' },
    { value: 'Billing', label: 'Operations, recharge and settlement' },
]))

const handleLogin = async () => {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    loading.value = true
    errorMessage.value = ''
    try {
        const response = await client.post('/admin/login', form)

        const token = response.data?.data?.token
        const user = response.data?.data?.user

        if (!token || !user) {
            throw new Error('Invalid server response: missing token or user')
        }

        sessionStorage.setItem('admin_token', token)
        sessionStorage.setItem('admin_user', JSON.stringify(user))
        sessionStorage.setItem('admin_role', user.role)

        ElMessage.success(t('admin.loginSuccess'))
        router.push('/admin')
    } catch (err) {
        errorMessage.value = err.response?.data?.errors?.email
            ? t('admin.loginFailed')
            : err.response?.data?.message
            || err.message
            || t('admin.loginFailed')
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.auth-card {
    display: flex;
    flex-direction: column;
}

.auth-warning {
    margin-bottom: 20px;
    border-radius: 12px;
}

.auth-input :deep(.el-input__wrapper) {
    border-radius: 12px;
    padding: 4px 12px;
    box-shadow: 0 0 0 1px #e2e8f0 inset !important;
    background: #fff;
    transition: box-shadow 0.2s;
}

.auth-input :deep(.el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px #2563eb inset !important;
}

.auth-input :deep(.el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.25) inset !important;
    border-color: #2563eb;
}

.auth-input :deep(.el-input__inner) {
    height: 48px;
    font-size: 15px;
    color: #0f172a;
}

.auth-input :deep(.el-input__prefix) {
    margin-right: 8px;
}

.auth-input :deep(.el-input__prefix-inner) .el-icon {
    color: #94a3b8;
    font-size: 18px;
}

.auth-btn {
    width: 100%;
    height: 48px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    border: none;
    box-shadow: 0 20px 45px rgba(37, 99, 235, 0.25);
    transition: all 0.25s;
}

.auth-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 24px 60px rgba(37, 99, 235, 0.32);
}

.auth-footer {
    min-height: 8px;
}
</style>
