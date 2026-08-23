import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    createStrategyExperiment,
    fetchExperimentMetrics,
    listStrategyExperiments,
    showExperiment,
    submitVerdict,
    syncCampaign,
    updateExperiment,
} from '@/repositories/experimentRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useExperimentsStore = defineStore('experiments', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const items = ref([]);
    const current = ref(null);
    const metrics = ref([]);

    const spendRatio = computed(() => {
        const budget = Number(current.value?.max_budget ?? 0);

        return budget > 0 ? Math.min(Number(current.value?.spend ?? 0) / budget, 1) : 0;
    });

    const isInLearningPhase = computed(() => current.value?.learning_phase?.status === 'learning');

    async function fetchForStrategy(strategyId, params = {}) {
        const result = await run(() => listStrategyExperiments(strategyId, params));

        items.value = result?.data ?? result ?? [];
    }

    async function fetchOne(id) {
        current.value = (await run(() => showExperiment(id))) ?? null;
    }

    async function fetchMetrics(id, params = {}) {
        const result = await run(() => fetchExperimentMetrics(id, params));

        metrics.value = result?.daily ?? result ?? [];
    }

    async function create(strategyId, payload) {
        return run(() => createStrategyExperiment(strategyId, payload), 'Experimento creado.');
    }

    async function update(id, payload) {
        const result = await run(() => updateExperiment(id, payload), 'Experimento actualizado.');

        if (result) {
            current.value = result;
        }

        return Boolean(result);
    }

    async function saveVerdict(id, payload) {
        const result = await run(() => submitVerdict(id, payload), 'Veredicto registrado en el historial.');

        if (result) {
            current.value = result;
        }

        return Boolean(result);
    }

    async function sync(id) {
        await run(() => syncCampaign(id), 'Sincronización de métricas encolada.');
    }

    return {
        loading,
        error,
        fieldErrors,
        items,
        current,
        metrics,
        spendRatio,
        isInLearningPhase,
        fetchForStrategy,
        fetchOne,
        fetchMetrics,
        create,
        update,
        saveVerdict,
        sync,
    };
});
