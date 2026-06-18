<template>
    <div class="page-shell-header">
        <div>
            <span v-if="eyebrow" class="page-shell-header__eyebrow">{{ eyebrow }}</span>
            <h1>{{ title }}</h1>
            <p v-if="description">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="page-shell-header__actions">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
})
</script>

<style scoped>
.page-shell-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    padding: 4px 0;
}

.page-shell-header__eyebrow {
    display: inline-flex;
    margin-bottom: 10px;
    padding: 6px 12px;
    border-radius: 999px;
    background: rgba(37, 99, 235, 0.08);
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.page-shell-header h1 {
    margin: 0;
    font-size: clamp(26px, 3vw, 34px);
    line-height: 1.08;
    letter-spacing: -0.03em;
    color: #0f172a;
}

.page-shell-header p {
    max-width: 720px;
    margin: 10px 0 0;
    color: #64748b;
    font-size: 14px;
}

.page-shell-header__actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .page-shell-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
