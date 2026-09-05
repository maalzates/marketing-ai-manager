import { defineStore } from 'pinia';
import { ref } from 'vue';
import { suggest as requestSuggestion } from '@/repositories/aiRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useAiStore = defineStore('ai', () => {
    const { loading, error, run } = useAsyncState();

    const pendingField = ref(null);

    // The suggestion is handed back to the caller, never written anywhere: the
    // user edits and saves it, the model only proposes.
    async function suggestFor(field, context = {}) {
        pendingField.value = field;

        const result = await run(() => requestSuggestion({ target: field, context }));

        pendingField.value = null;

        return result?.suggestion ?? result?.value ?? null;
    }

    return { loading, error, pendingField, suggestFor };
});
