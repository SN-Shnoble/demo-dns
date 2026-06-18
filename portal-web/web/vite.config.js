import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
        },
    },
    server: {
        port: 5173,
        proxy: {
            // portal-web API (member control plane + admin / agent / internal;
            // dns-console-web was merged into portal-web on 2026-06-15).
            '/api/v1': {
                target: 'http://localhost:8081',
                changeOrigin: true,
                configure: (proxy) => {
                    proxy.on('proxyReq', (proxyReq, req) => {
                        console.log(`[Vite Proxy] ${req.method} ${req.url} -> ${proxyReq.path}`)
                    })
                    proxy.on('proxyReqWs', (proxyReq, req) => {
                        console.log(`[Vite Proxy WS] ${req.method} ${req.url}`)
                    })
                },
            },
        },
    },
    build: {
        outDir: '../dist',
        emptyOutDir: true,
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', 'vue-router', 'vue-i18n'],
                    'vendor-ui': ['element-plus', '@element-plus/icons-vue'],
                    'vendor-http': ['axios'],
                },
            },
        },
    },
})
