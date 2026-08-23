<script setup>
import { storeToRefs } from 'pinia';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const { toasts } = storeToRefs(ui);

const tone = {
    success: 'border-success-200 bg-success-50 text-success-700',
    error: 'border-danger-200 bg-danger-50 text-danger-700',
    info: 'border-line bg-surface text-ink',
};
</script>

<template>
    <div class="pointer-events-none fixed bottom-6 right-6 z-50 flex w-full max-w-sm flex-col gap-3">
        <div
            v-for="toast in toasts"
            :key="toast.id"
            class="pointer-events-auto flex items-start gap-3 rounded-card border px-4 py-3 shadow-sm"
            :class="tone[toast.type]"
            role="status"
        >
            <p class="flex-1 text-sm">{{ toast.message }}</p>
            <button type="button" class="text-xs font-medium opacity-70 hover:opacity-100" @click="ui.dismiss(toast.id)">
                Cerrar
            </button>
        </div>
    </div>
</template>
