<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirmar' },
    cancelLabel: { type: String, default: 'Cancelar' },
    destructive: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 px-4">
        <div class="w-full max-w-md rounded-card border border-line bg-surface p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-ink">{{ title }}</h2>
            <p v-if="message" class="mt-2 text-sm text-muted">{{ message }}</p>
            <slot />
            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas"
                    @click="$emit('cancel')"
                >
                    {{ cancelLabel }}
                </button>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-white"
                    :class="destructive ? 'bg-danger-500 hover:bg-danger-700' : 'bg-brand-600 hover:bg-brand-700'"
                    @click="$emit('confirm')"
                >
                    {{ confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
