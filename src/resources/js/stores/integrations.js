import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    deleteIntegration,
    listIntegrations,
    oauthRedirectUrl,
    saveIntegration,
    verifyIntegration,
} from '@/repositories/integrationsRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useIntegrationsStore = defineStore('integrations', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const items = ref([]);

    const byProvider = computed(() => Object.fromEntries(items.value.map((item) => [item.provider, item])));
    const connected = computed(() => items.value.filter((item) => item.status === 'connected'));

    async function fetchAll() {
        items.value = (await run(listIntegrations)) ?? [];
    }

    async function save(provider, payload) {
        const result = await run(() => saveIntegration(provider, payload), 'Credencial guardada y verificada.');

        if (result) {
            await fetchAll();
        }

        return Boolean(result);
    }

    async function verify(provider) {
        const result = await run(() => verifyIntegration(provider), 'Conexión verificada.');

        if (result) {
            await fetchAll();
        }
    }

    async function disconnect(provider) {
        await run(() => deleteIntegration(provider), 'Integración desconectada.');
        await fetchAll();
    }

    async function connectWithOauth(provider) {
        const result = await run(() => oauthRedirectUrl(provider));

        if (result?.url) {
            window.location.assign(result.url);
        }
    }

    return {
        loading,
        error,
        fieldErrors,
        items,
        byProvider,
        connected,
        fetchAll,
        save,
        verify,
        disconnect,
        connectWithOauth,
    };
});
