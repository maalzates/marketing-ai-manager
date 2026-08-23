import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
    createCompetitor,
    deleteCompetitor,
    listCompetitors,
    listInsights,
    syncCompetitor,
} from '@/repositories/competitorRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useCompetitorsStore = defineStore('competitors', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const items = ref([]);
    const insights = ref([]);

    async function fetchAll(params = {}) {
        const result = await run(() => listCompetitors(params));

        items.value = result?.data ?? result ?? [];
    }

    async function fetchInsights(params = {}) {
        const result = await run(() => listInsights(params));

        insights.value = result?.data ?? result ?? [];
    }

    async function create(payload) {
        const result = await run(() => createCompetitor(payload), 'Competidor agregado. El scraping corre en segundo plano.');

        if (result) {
            items.value = [result, ...items.value];
        }

        return Boolean(result);
    }

    async function sync(id) {
        await run(() => syncCompetitor(id), 'Sincronización encolada. Verás los datos cuando termine.');
    }

    async function remove(id) {
        const result = await run(() => deleteCompetitor(id), 'Competidor eliminado.');

        if (result !== undefined) {
            items.value = items.value.filter((item) => item.id !== id);
        }
    }

    return { loading, error, fieldErrors, items, insights, fetchAll, fetchInsights, create, sync, remove };
});
