import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
    fetchSettings,
    fetchStrategySettings,
    updateSettings,
    updateStrategySettings,
} from '@/repositories/settingsRepository';
import { useAsyncState } from '@/stores/useAsyncState';
import { useUiStore } from '@/stores/ui';

export const useSettingsStore = defineStore('settings', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const values = ref({});
    const strategyValues = ref({});

    async function fetchAll() {
        values.value = (await run(fetchSettings)) ?? {};
        useUiStore().setSandbox(values.value.sandbox_mode);
    }

    async function save(payload) {
        const result = await run(() => updateSettings(payload), 'Configuración guardada.');

        if (!result) {
            return false;
        }

        values.value = result;
        useUiStore().setSandbox(result.sandbox_mode);

        return true;
    }

    async function fetchForStrategy(strategyId) {
        strategyValues.value = (await run(() => fetchStrategySettings(strategyId))) ?? {};
    }

    async function saveForStrategy(strategyId, payload) {
        const result = await run(
            () => updateStrategySettings(strategyId, payload),
            'Configuración de la estrategia guardada.',
        );

        if (!result) {
            return false;
        }

        strategyValues.value = result;

        return true;
    }

    return { loading, error, fieldErrors, values, strategyValues, fetchAll, save, fetchForStrategy, saveForStrategy };
});
