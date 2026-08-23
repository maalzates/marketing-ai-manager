<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    actionLabel: { type: String, default: '' },
});

defineEmits(['action']);
</script>

<template>
    <div class="rounded-card border border-dashed border-line bg-surface px-6 py-12 text-center">
        <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
        <p v-if="description" class="mx-auto mt-2 max-w-md text-sm text-muted">{{ description }}</p>
        <button
            v-if="actionLabel"
            type="button"
            class="mt-5 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
            @click="$emit('action')"
        >
            {{ actionLabel }}
        </button>
        <slot />
    </div>
</template>
