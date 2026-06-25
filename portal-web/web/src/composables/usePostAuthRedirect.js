import client from '@/api/client'

/**
 * 登录/注册成功后跳转到控制台。
 * 优先跳转到用户上次访问的 profile，否则取第一个 profile。
 *
 * @param {import('vue-router').Router} router - Vue Router 实例
 */
export async function redirectToConsole(router) {
    const savedId = localStorage.getItem('current_profile_id')
    try {
        const { data } = await client.get('/user/profiles')
        const list = data.data || []
        const target = list.find(p => (p.profile_id || p.id) === savedId) || list[0]
        if (target) {
            const key = target.profile_id || target.id
            localStorage.setItem('current_profile_id', key)
            await router.push(`/user/${key}`)
            return
        }
    } catch (_) {
        if (savedId) {
            await router.push(`/user/${savedId}`)
            return
        }
    }
    await router.push('/user/profiles')
}
