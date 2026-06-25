/**
 * 格式化时间戳为 YYYY-MM-DD HH:mm:ss。
 * @param {string|number|Date|null} ts
 * @returns {string}
 */
export function formatDateTime(ts) {
    if (!ts) return '-'
    const d = new Date(ts)
    if (Number.isNaN(d.getTime())) return '-'
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}
