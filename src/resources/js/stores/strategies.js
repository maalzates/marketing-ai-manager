import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    createStrategy,
    deleteStrategy,
    listStrategies,
    showStrategy,
    updateStrategy,
} from '@/repositories/strategyRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useStrategiesStore = defineStore('strategies', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const items = ref([]);
    const current = ref(null);

    const active = computed(() => items.value.filter((item) => item.status === 'active'));

    async function fetchAll(params = {}) {
        const result = await run(() => listStrategies(params));

        items.value = result?.data ?? result ?? [];
    }

    async function fetchOne(id) {
        current.value = (await run(() => showStrategy(id))) ?? null;
    }

    async function create(payload) {
        const result = await run(() => createStrategy(payload), 'Estrategia creada.');

        if (result) {
            items.value = [result, ...items.value];
        }

        return result;
    }

    async function update(id, payload) {
        const result = await run(() => updateStrategy(id, payload), 'Estrategia actualizada.');

        if (result) {
            current.value = result;
            items.value = items.value.map((item) => (item.id === id ? result : item));
        }

        return Boolean(result);
    }

    async function remove(id) {
        const result = await run(() => deleteStrategy(id), 'Estrategia eliminada.');

        if (result !== undefined) {
            items.value = items.value.filter((item) => item.id !== id);
        }
    }

    return { loading, error, fieldErrors, items, current, active, fetchAll, fetchOne, create, update, remove };
});
