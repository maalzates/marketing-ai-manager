import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    deleteAsset,
    linkAssetToExperiment,
    listAssets,
    uploadAsset,
} from '@/repositories/assetRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useAssetsStore = defineStore('assets', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const items = ref([]);

    const ready = computed(() => items.value.filter((item) => item.status === 'ready'));

    async function fetchAll(params = {}) {
        const result = await run(() => listAssets(params));

        items.value = result?.data ?? result ?? [];
    }

    async function upload(file, { type, strategyId }) {
        const body = new FormData();

        body.append('file', file);
        body.append('type', type);
        body.append('strategy_id', strategyId);

        const result = await run(() => uploadAsset(body), `${file.name} subida a la biblioteca.`);

        if (result) {
            items.value = [result, ...items.value];
        }

        return Boolean(result);
    }

    async function linkToExperiment(id, experimentId) {
        const result = await run(() => linkAssetToExperiment(id, experimentId), 'Pieza vinculada al experimento.');

        if (result) {
            items.value = items.value.map((item) => (item.id === id ? result : item));
        }
    }

    async function remove(id) {
        const result = await run(() => deleteAsset(id), 'Pieza retirada de la biblioteca.');

        if (result !== undefined) {
            items.value = items.value.filter((item) => item.id !== id);
        }
    }

    return { loading, error, fieldErrors, items, ready, fetchAll, upload, linkToExperiment, remove };
});
