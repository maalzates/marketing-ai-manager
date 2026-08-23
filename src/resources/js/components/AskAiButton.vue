<script setup>
import { ref } from 'vue';
import { useAiStore } from '@/stores/ai';

const props = defineProps({
    field: { type: String, required: true },
    context: { type: Object, default: () => ({}) },
    label: { type: String, default: 'Ask AI' },
});

const emit = defineEmits(['apply']);

const ai = useAiStore();
const suggestion = ref('');
const open = ref(false);

async function ask() {
    const result = await ai.suggestFor(props.field, props.context);

    if (result === null) {
        return;
    }

    suggestion.value = typeof result === 'string' ? result : JSON.stringify(result, null, 2);
    open.value = true;
}

function apply() {
    emit('apply', suggestion.value);
    open.value = false;
}
</script>

<template>
    <div class="inline-flex flex-col items-start gap-2">
        <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-brand-200 bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 hover:bg-brand-100 disabled:opacity-50"
            :disabled="ai.loading"
            @click="ask"
        >
            <span aria-hidden="true">✨</span>
            {{ ai.loading && ai.pendingField === field ? 'Pensando…' : label }}
        </button>

        <div v-if="open" class="w-full rounded-card border border-brand-200 bg-brand-50 p-3">
            <p class="text-xs font-medium text-brand-700">Sugerencia de la IA — edítala antes de guardar</p>
            <textarea
                v-model="suggestion"
                rows="4"
                class="mt-2 w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink"
            />
            <div class="mt-2 flex gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700"
                    @click="apply"
                >
                    Usar esta sugerencia
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-medium text-ink hover:bg-canvas"
                    @click="open = false"
                >
                    Descartar
                </button>
            </div>
        </div>
    </div>
</template>
