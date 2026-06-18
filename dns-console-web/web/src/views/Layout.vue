<template>
    <el-config-provider :locale="elLocale">
    <div class="layout">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">D</div>
                <span class="sidebar-title">管理后台</span>
            </div>
            <el-menu
                :default-active="activeRoute"
                :router="true"
                class="sidebar-menu"
                background-color="#0f172a"
                text-color="#94a3b8"
                active-text-color="#fff"
            >
                <el-menu-item index="/dashboard">
                    <el-icon><DataAnalysis /></el-icon>
                    <span>{{ $t('nav.dashboard') }}</span>
                </el-menu-item>
                <el-menu-item index="/nodes">
                    <el-icon><Monitor /></el-icon>
                    <span>{{ $t('nav.nodes') }}</span>
                </el-menu-item>
                <el-menu-item index="/publishes">
                    <el-icon><Upload /></el-icon>
                    <span>{{ $t('nav.publishes') }}</span>
                </el-menu-item>
                <el-menu-item index="/geo-dns">
                    <el-icon><Globe /></el-icon>
                    <span>{{ $t('nav.geoDns') }}</span>
                </el-menu-item>
                <el-menu-item index="/rules">
                    <el-icon><Collection /></el-icon>
                    <span>{{ $t('nav.ruleLibrary') }}</span>
                </el-menu-item>
                <el-menu-item index="/system-config">
                    <el-icon><Setting /></el-icon>
                    <span>{{ $t('nav.systemConfig') }}</span>
                </el-menu-item>
                <el-menu-item index="/audit-logs">
                    <el-icon><Document /></el-icon>
                    <span>{{ $t('nav.auditLogs') }}</span>
                </el-menu-item>
            </el-menu>
        </div>

        <!-- Right Content -->
        <div class="main-area">
            <div class="top-bar">
                <div class="top-bar-title">{{ $t('dashboard.title') }}</div>
                <div class="top-bar-right">
                    <el-dropdown @command="switchLocale" style="margin-right:12px">
                        <span class="lang-btn">
                            <el-icon><Iphone /></el-icon>
                            {{ currentLocale }}
                            <el-icon><ArrowDown /></el-icon>
                        </span>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item command="en">🇬🇧 English</el-dropdown-item>
                                <el-dropdown-item command="zh-CN">🇨🇳 简体中文</el-dropdown-item>
                                <el-dropdown-item command="ja">🇯🇵 日本語</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                    <el-dropdown @command="handleCommand">
                        <span class="user-btn">
                            <el-icon><User /></el-icon>
                            Admin
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
            </div>
            <div class="content">
                <slot />
            </div>
        </div>
    </div>
    </el-config-provider>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import enLocale from 'element-plus/dist/locale/en.mjs'
import zhLocale from 'element-plus/dist/locale/zh-cn.mjs'
import jaLocale from 'element-plus/dist/locale/ja.mjs'

const route = useRoute()
const router = useRouter()
const { locale } = useI18n()

const localeMap = { 'en': enLocale, 'zh-CN': zhLocale, 'ja': jaLocale }
const elLocale = ref(localeMap[locale.value] || zhLocale)

watch(locale, (val) => {
    elLocale.value = localeMap[val] || zhLocale
})

const activeRoute = computed(() => route.path)

const currentLocale = computed(() => {
    const map = { 'en': 'EN', 'zh-CN': '中文', 'ja': '日本語' }
    return map[locale.value] || '中文'
})

const switchLocale = (loc) => {
    locale.value = loc
    localStorage.setItem('dns_locale', loc)
}

const handleCommand = (cmd) => {
    if (cmd === 'logout') {
        localStorage.removeItem('dns_token')
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
.layout {
    display: flex;
    min-height: 100vh;
}
.sidebar {
    width: 220px;
    background: #0f172a;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}
.sidebar-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 16px;
    border-bottom: 1px solid #ffffff1a;
}
.sidebar-logo {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, #2563eb, #7c3aed);
    display: grid;
    place-items: center;
    color: #fff;
    font-weight: 800;
    font-size: 16px;
    box-shadow: 0 8px 20px rgba(37,99,235,0.3);
}
.sidebar-title {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
}
.sidebar-menu {
    border-right: none !important;
    flex: 1;
    padding: 8px 0;
}
.sidebar-menu .el-menu-item {
    height: 44px;
    line-height: 44px;
    margin: 2px 8px;
    border-radius: 8px;
}
.sidebar-menu .el-menu-item.is-active {
    background: linear-gradient(135deg, #2563eb, #7c3aed) !important;
    color: #fff !important;
}
.sidebar-menu .el-menu-item:hover {
    background-color: #1e293b !important;
    color: #fff !important;
}
.main-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.top-bar {
    height: 48px;
    background: rgba(255,255,255,0.88);
    backdrop-filter: blur(18px);
    -webkit-backdrop-filter: blur(18px);
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    flex-shrink: 0;
}
.top-bar-title {
    font-size: 14px;
    font-weight: 500;
    color: #475569;
}
.top-bar-right {
    display: flex;
    align-items: center;
}
.lang-btn {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    color: #475569;
    font-size: 14px;
}
.user-btn {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    color: #475569;
    font-size: 14px;
}
.content {
    flex: 1;
    padding: 24px;
    width: 100%;
    box-sizing: border-box;
}
</style>
