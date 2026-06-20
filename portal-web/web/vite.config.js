import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  const isDev = mode === 'development'
  const target = env.VITE_API_PROXY_TARGET

  return {
    base: isDev ? '/' : '/dist/',

    plugins: [vue()],

    resolve: {
      alias: {
        '@': resolve(__dirname, 'src')
      }
    },

    server: {
      host: env.VITE_DEV_HOST || '127.0.0.1',
      port: Number(env.VITE_DEV_PORT || 3000),
      strictPort: true,

      proxy: isDev && target
        ? {
            '/api': {
              target,
              changeOrigin: true
            }
          }
        : undefined
    },

    build: {
       outDir: '../public/dist',
      emptyOutDir: true,
      assetsDir: 'assets',
      sourcemap: mode !== 'production'
    }
  }
})