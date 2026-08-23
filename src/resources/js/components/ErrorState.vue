<script setup>
defineProps({
    message: { type: String, required: true },
    retryLabel: { type: String, default: 'Reintentar' },
});

defineEmits(['retry']);
</script>

<template>
    <div class="rounded-card border border-danger-200 bg-danger-50 px-6 py-8 text-center">
        <p class="text-sm font-medium text-danger-700">{{ message }}</p>
        <button
            type="button"
            class="mt-4 rounded-lg border border-danger-200 bg-surface px-4 py-2 text-sm font-medium text-danger-700 hover:bg-danger-50"
            @click="$emit('retry')"
        >
            {{ retryLabel }}
        </button>
    </div>
</template>
