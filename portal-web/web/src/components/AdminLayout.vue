<template>
    <el-config-provider :locale="elLocale">
        <div class="admin-shell">
            <aside class="admin-sidebar">
                <router-link to="/admin" class="admin-brand">
                    <div class="admin-brand__mark">A</div>
                    <div>
                        <strong>{{ $t('admin.title') }}</strong>
                        <span>Operations Console</span>
                    </div>
                </router-link>

                <div class="admin-sidebar__panel">
                    <span class="admin-sidebar__eyebrow">Control Plane</span>
                    <strong>{{ $t(pageTitle) }}</strong>
                    <p>Nodes, policies, logs, settlements and release tasks aligned in one operational view.</p>
                </div>

                <div v-for="group in navGroups" :key="group.key" class="admin-sidebar__group">
                    <span class="admin-sidebar__group-title">{{ group.title }}</span>
                    <router-link
                        v-for="item in group.items"
                        :key="item.to"
                        :to="item.to"
                        class="admin-nav-item"
                        :class="{ 'is-active': activeRoute === item.to }"
                    >
                        <el-icon><component :is="item.icon" /></el-icon>
                        <span>{{ item.label }}</span>
                    </router-link>
                </div>
            </aside>

            <div class="admin-shell__main">
                <header class="admin-topbar">
                    <div>
                        <span class="admin-topbar__eyebrow">Admin Workspace</span>
                        <h1>{{ $t(pageTitle) }}</h1>
                        <div class="admin-topbar__breadcrumb">
                            <span>{{ $t('admin.title') }}</span>
                            <el-icon><CaretRight /></el-icon>
                            <span>{{ $t(pageTitle) }}</span>
                        </div>
                    </div>
                    <div class="admin-topbar__actions">
                        <el-dropdown @command="switchLocale">
                            <span class="admin-toolbar-button">
                                <el-icon><Iphone /></el-icon>
                                {{ currentLocale }}
                                <el-icon><ArrowDown /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="en">{{ $t('settings.lang.en') }}</el-dropdown-item>
                                    <el-dropdown-item command="zh-CN">{{ $t('settings.lang.zh') }}</el-dropdown-item>
                                    <el-dropdown-item command="ko">{{ $t('settings.lang.ko') }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                        <el-dropdown @command="handleCommand">
                            <span class="admin-toolbar-button admin-toolbar-button--strong">
                                <el-icon><User /></el-icon>
                                {{ $t('admin.admin') }}
                                <el-icon><ArrowDown /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="logout">
                                        <el-icon><SwitchButton /></el-icon>
                                        {{ $t('nav.logout') }}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                </header>

                <main class="admin-shell__content">
                    <router-view />
                </main>
            </div>
        </div>
    </el-config-provider>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import i18n from '@/locales'
import enLocale from 'element-plus/dist/locale/en.mjs'
import zhLocale from 'element-plus/dist/locale/zh-cn.mjs'

const route = useRoute()
const { locale } = useI18n()

const localeMap = { 'en': enLocale, 'zh-CN': zhLocale, 'ko': zhLocale }
const elLocale = ref(localeMap[locale.value] || zhLocale)

watch(locale, (val) => {
    elLocale.value = localeMap[val] || zhLocale
})

const titleMap = {
    AdminDashboard: 'admin.title',
    AdminNodes: 'nav.nodes',
    AdminPublishes: 'nav.publishes',
    AdminGeoDNS: 'nav.geoDns',
    AdminRules: 'nav.ruleLibrary',
    AdminQueryLogs: 'admin.queryLogs',
    AdminAlerts: 'admin.alerts',
    AdminUsers: 'admin.users',
    AdminDevices: 'admin.devices',
    AdminBilling: 'admin.billing.title',
    AdminBalance: 'admin.finance.balance',
    AdminRecharge: 'admin.finance.recharge',
    AdminBill: 'admin.finance.bill',
    AdminRefundRecords: 'admin.finance.refundRecords',
    AdminSystemConfig: 'nav.systemConfig',
    AdminBasicConfig: 'admin.basicConfig.title',
    AdminAuditLogs: 'nav.auditLogs',
    AdminRoleManagement: 'admin.rbac.title',
    AdminMenuConfig: 'admin.menuConfig.title',
}

const pageTitle = computed(() => (titleMap[route.name] || 'admin.title'))
const activeRoute = computed(() => route.path)

const navGroups = computed(() => ([
    {
        key: 'service',
        title: i18n.global.t('admin.menuGroup.service'),
        items: [
            { to: '/admin/dashboard', label: i18n.global.t('nav.dashboard'), icon: 'DataAnalysis' },
            { to: '/admin/nodes', label: i18n.global.t('nav.nodes'), icon: 'Monitor' },
            { to: '/admin/geo-dns', label: i18n.global.t('nav.geoDns'), icon: 'Connection' },
            { to: '/admin/rules', label: i18n.global.t('nav.ruleLibrary'), icon: 'Collection' },
            { to: '/admin/publishes', label: i18n.global.t('nav.publishes'), icon: 'Upload' },
        ],
    },
    {
        key: 'monitor',
        title: i18n.global.t('admin.menuGroup.monitor'),
        items: [
            { to: '/admin/alerts', label: i18n.global.t('admin.alerts'), icon: 'Message' },
            { to: '/admin/query-logs', label: i18n.global.t('admin.queryLogs'), icon: 'Document' },
            { to: '/admin/audit-logs', label: i18n.global.t('nav.auditLogs'), icon: 'Tickets' },
        ],
    },
    {
        key: 'user',
        title: i18n.global.t('admin.menuGroup.userMgmt'),
        items: [
            { to: '/admin/users', label: i18n.global.t('admin.users'), icon: 'User' },
            { to: '/admin/devices', label: i18n.global.t('admin.devices'), icon: 'Avatar' },
            { to: '/admin/rbac', label: i18n.global.t('admin.rbac.title'), icon: 'Lock' },
        ],
    },
    {
        key: 'finance',
        title: i18n.global.t('admin.menuGroup.finance'),
        items: [
            { to: '/admin/billing', label: i18n.global.t('admin.billing.title'), icon: 'Coin' },
            { to: '/admin/balance', label: i18n.global.t('admin.finance.balance'), icon: 'Wallet' },
            { to: '/admin/recharge', label: i18n.global.t('admin.finance.recharge'), icon: 'Money' },
            { to: '/admin/bill', label: i18n.global.t('admin.finance.bill'), icon: 'CreditCard' },
            { to: '/admin/refund-records', label: i18n.global.t('admin.finance.refundRecords'), icon: 'RefreshLeft' },
        ],
    },
    {
        key: 'settings',
        title: i18n.global.t('admin.menuGroup.settings'),
        items: [
            { to: '/admin/basic-config', label: i18n.global.t('admin.basicConfig.title'), icon: 'Setting' },
            { to: '/admin/system-config', label: i18n.global.t('nav.systemConfig'), icon: 'Tools' },
            { to: '/admin/menu-config', label: i18n.global.t('admin.menuConfig.title'), icon: 'List' },
        ],
    },
]))

const currentLocale = computed(() => {
    const map = {
        'en': i18n.global.t('settings.lang.en'),
        'zh-CN': i18n.global.t('settings.lang.zh'),
        'ko': i18n.global.t('settings.lang.ko'),
    }
    return map[locale.value] || i18n.global.t('settings.lang.zh')
})

const switchLocale = (loc) => {
    locale.value = loc
    localStorage.setItem('dns_locale', loc)
}

const handleCommand = (cmd) => {
    if (cmd === 'logout') {
        sessionStorage.removeItem('admin_token')
        sessionStorage.removeItem('admin_role')
        window.location.href = '/'
    }
}
</script>

<style>
body {
    margin: 0;
    font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
    background: #f8fafc;
}

.admin-shell {
    display: flex;
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(37, 99, 235, 0.08), transparent 20%),
        linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
}

.admin-sidebar {
    width: 296px;
    flex-shrink: 0;
    padding: 26px 18px;
    background: rgba(15, 23, 42, 0.98);
    border-right: 1px solid rgba(148, 163, 184, 0.12);
}

.admin-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    color: inherit;
    text-decoration: none;
}

.admin-brand__mark {
    width: 48px;
    height: 48px;
    border-radius: 18px;
    background: linear-gradient(135deg, #2563eb, #0f172a);
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 800;
    font-size: 20px;
    box-shadow: 0 8px 20px rgba(37,99,235,0.3);
}

.admin-brand strong,
.admin-brand span {
    display: block;
}

.admin-brand strong {
    color: #fff;
    font-size: 18px;
}

.admin-brand span {
    margin-top: 4px;
    font-size: 12px;
    color: #94a3b8;
}

.admin-sidebar__panel {
    margin: 26px 8px 20px;
    padding: 18px;
    border-radius: 20px;
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(30, 41, 59, 0.84));
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.admin-sidebar__eyebrow {
    display: inline-flex;
    margin-bottom: 10px;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(59, 130, 246, 0.14);
    color: #bfdbfe;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.admin-sidebar__panel strong {
    display: block;
    color: #fff;
    font-size: 18px;
}

.admin-sidebar__panel p {
    margin: 8px 0 0;
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.7;
}

.admin-sidebar__group + .admin-sidebar__group {
    margin-top: 18px;
}

.admin-sidebar__group-title {
    display: block;
    margin: 0 10px 10px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
}

.admin-nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 6px 0;
    padding: 12px 12px;
    border-radius: 14px;
    color: #cbd5e1;
    text-decoration: none;
    transition: 0.2s ease;
}

.admin-nav-item:hover {
    background: rgba(30, 41, 59, 0.96);
    color: #fff;
}

.admin-nav-item.is-active {
    color: #fff;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.26), rgba(14, 165, 233, 0.28));
    border: 1px solid rgba(96, 165, 250, 0.28);
}

.admin-shell__main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.admin-topbar {
    position: sticky;
    top: 0;
    z-index: 40;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 24px 28px 18px;
    background: rgba(248, 250, 252, 0.88);
    backdrop-filter: blur(18px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.92);
}

.admin-topbar__eyebrow {
    display: inline-flex;
    margin-bottom: 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #2563eb;
}

.admin-topbar h1 {
    margin: 0;
    font-size: clamp(24px, 3vw, 34px);
    color: #0f172a;
    line-height: 1.08;
}

.admin-topbar__breadcrumb {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 13px;
    color: #64748b;
}

.admin-topbar__actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.admin-toolbar-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid #dbe3ef;
    color: #334155;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
}

.admin-toolbar-button--strong {
    font-weight: 600;
}

.admin-shell__content {
    flex: 1;
    width: 100%;
    box-sizing: border-box;
    padding: 28px;
}

@media (max-width: 1120px) {
    .admin-shell {
        flex-direction: column;
    }

    .admin-sidebar {
        width: auto;
        padding: 18px;
    }

    .admin-sidebar__group {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .admin-sidebar__group-title {
        width: 100%;
    }

    .admin-nav-item {
        margin: 0;
    }

    .admin-topbar,
    .admin-shell__content {
        padding-left: 18px;
        padding-right: 18px;
    }
}

@media (max-width: 768px) {
    .admin-topbar {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
