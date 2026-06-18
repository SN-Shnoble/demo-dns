<template>
    <el-card shadow="never" style="border-radius:6px">
        <el-tabs v-model="activeTab" class="config-tabs">
            <el-tab-pane :label="$t('admin.systemConfig.dnsParams') || 'DNS参数'" name="dns">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.defaultUpstream')">
                            <el-input v-model="config.dns.default_upstream" placeholder="1.1.1.1:53" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.timeout')">
                            <el-input-number v-model="config.dns.timeout_ms" :min="100" :max="10000" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.logRetention')">
                            <el-input-number v-model="config.dns.log_retention_days" :min="1" :max="365" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.maxQueriesPerNode')">
                            <el-input-number v-model="config.dns.max_queries_per_node" :min="1000" :step="1000" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.redis') || 'Redis'" name="redis">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.host') || '主机'">
                            <el-input v-model="config.redis.host" placeholder="127.0.0.1" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.port') || '端口'">
                            <el-input-number v-model="config.redis.port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.password') || '密码'">
                            <el-input v-model="config.redis.password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.database') || '数据库'">
                            <el-input-number v-model="config.redis.database" :min="0" :max="15" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.timeout') || '超时(ms)'">
                            <el-input-number v-model="config.redis.timeout_ms" :min="100" :max="30000" :step="100" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.clickhouse') || 'ClickHouse'" name="clickhouse">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.host') || '主机'">
                            <el-input v-model="config.clickhouse.host" placeholder="127.0.0.1" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.port') || '端口'">
                            <el-input-number v-model="config.clickhouse.port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.database') || '数据库'">
                            <el-input v-model="config.clickhouse.database" placeholder="default" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.username') || '用户名'">
                            <el-input v-model="config.clickhouse.username" placeholder="default" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.password') || '密码'">
                            <el-input v-model="config.clickhouse.password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.maxExecTime') || '最大执行时间(s)'">
                            <el-input-number v-model="config.clickhouse.max_execution_time" :min="1" :max="3600" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.payment') || '支付接口'" name="payment">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.paymentProvider') || '支付提供商'">
                            <el-select v-model="config.payment.provider" style="width:100%">
                                <el-option value="stripe" label="Stripe" />
                                <el-option value="paypal" label="PayPal" />
                                <el-option value="alipay" label="Alipay" />
                                <el-option value="wechat" label="WeChat Pay" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.merchantId') || '商户ID'">
                            <el-input v-model="config.payment.merchant_id" placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.merchantKey') || '商户密钥'">
                            <el-input v-model="config.payment.merchant_key" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.webhookSecret') || 'Webhook密钥'">
                            <el-input v-model="config.payment.webhook_secret" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.callbackUrl') || '回调地址'">
                            <el-input v-model="config.payment.callback_url" placeholder="https://example.com/api/payment/callback" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="$t('admin.systemConfig.mailServer') || '邮箱服务器'" name="mail">
                <div style="max-width:600px">
                    <el-form label-position="left" label-width="160px">
                        <el-form-item :label="$t('admin.systemConfig.mailDriver') || '邮件驱动'">
                            <el-select v-model="config.mail.driver" style="width:100%">
                                <el-option value="smtp" label="SMTP" />
                                <el-option value="mailgun" label="Mailgun" />
                                <el-option value="ses" label="AWS SES" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpHost') || 'SMTP主机'">
                            <el-input v-model="config.mail.smtp_host" placeholder="smtp.example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpPort') || 'SMTP端口'">
                            <el-input-number v-model="config.mail.smtp_port" :min="1" :max="65535" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpUsername') || '用户名'">
                            <el-input v-model="config.mail.smtp_username" placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.smtpPassword') || '密码'">
                            <el-input v-model="config.mail.smtp_password" type="password" show-password placeholder="" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.fromAddress') || '发件人地址'">
                            <el-input v-model="config.mail.from_address" placeholder="noreply@example.com" />
                        </el-form-item>
                        <el-form-item :label="$t('admin.systemConfig.fromName') || '发件人名称'">
                            <el-input v-model="config.mail.from_name" placeholder="OcerDNS" />
                        </el-form-item>
                    </el-form>
                </div>
            </el-tab-pane>
        </el-tabs>

        <div style="margin-top:24px">
            <el-button type="primary" :loading="saving" @click="handleSave">
                {{ $t('admin.systemConfig.save') }}
            </el-button>
            <el-button :loading="restoring" @click="handleReset" style="margin-left:8px">
                {{ $t('common.reset') || 'Reset' }}
            </el-button>
        </div>
    </el-card>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useI18n } from 'vue-i18n'
import client from '@/api/client'

const { t } = useI18n()

const activeTab = ref('dns')
const saving = ref(false)
const restoring = ref(false)

const defaultConfig = {
    dns: {
        default_upstream: '1.1.1.1:53',
        timeout_ms: 5000,
        log_retention_days: 90,
        max_queries_per_node: 100000,
    },
    redis: {
        host: '127.0.0.1',
        port: 6379,
        password: '',
        database: 0,
        timeout_ms: 5000,
    },
    clickhouse: {
        host: '127.0.0.1',
        port: 9000,
        database: 'default',
        username: 'default',
        password: '',
        max_execution_time: 30,
    },
    payment: {
        provider: 'stripe',
        merchant_id: '',
        merchant_key: '',
        webhook_secret: '',
        callback_url: '',
    },
    mail: {
        driver: 'smtp',
        smtp_host: 'smtp.example.com',
        smtp_port: 587,
        smtp_username: '',
        smtp_password: '',
        from_address: 'noreply@example.com',
        from_name: 'OcerDNS',
    },
}

const config = ref(JSON.parse(JSON.stringify(defaultConfig)))

const handleSave = async () => {
    saving.value = true
    try {
        await client.put('/admin/system-config', config.value)
        ElMessage.success(t('admin.systemConfig.saved'))
    } catch {
        ElMessage.error(t('admin.systemConfig.saveFailed'))
    } finally {
        saving.value = false
    }
}

const handleReset = () => {
    restoring.value = true
    config.value = JSON.parse(JSON.stringify(defaultConfig))
    restoring.value = false
}

onMounted(async () => {
    try {
        const { data } = await client.get('/admin/system-config').catch(() => ({
            data: { data: {} },
        }))

        if (data.data && Object.keys(data.data).length > 0) {
            config.value = {
                ...config.value,
                ...data.data,
            }
        }
    } catch {}
})
</script>

<style scoped>
.page-header {
    margin-bottom: 24px;
}
.page-header h2 {
    margin: 0 0 4px;
    font-size: 24px;
    color: #303133;
}
.page-header p {
    margin: 0;
    color: #909399;
    font-size: 14px;
}
.config-tabs :deep(.el-tabs__item) {
    font-size: 14px;
}
</style>
