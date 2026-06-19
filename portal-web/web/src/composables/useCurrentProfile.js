import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

export function useCurrentProfile() {
    const route = useRoute()
    const router = useRouter()

    const currentProfileId = computed(() => {
        // 优先从路由 param 读取 (新 URL 格式 /user/:profile_id/xxx)
        if (route.params.profile_id) {
            return route.params.profile_id
        }
        // 兼容旧格式 URL query 参数
        if (route.query.profile_id) {
            return route.query.profile_id
        }
        // fallback localStorage
        return localStorage.getItem('current_profile_id') || ''
    })

    // 当 profile_id param 变化时同步到 localStorage
    watch(
        () => route.params.profile_id,
        (newId) => {
            if (newId) {
                localStorage.setItem('current_profile_id', newId)
            }
        }
    )

    return { currentProfileId, route }
}