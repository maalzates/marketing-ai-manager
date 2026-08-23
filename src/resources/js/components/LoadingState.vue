<script setup>
defineProps({
    label: { type: String, default: 'Cargando…' },
});
</script>

<template>
    <div class="flex items-center justify-center gap-3 rounded-card border border-line bg-surface px-6 py-12 text-muted">
        <span class="size-4 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600" />
        <span class="text-sm">{{ label }}</span>
    </div>
</template>
