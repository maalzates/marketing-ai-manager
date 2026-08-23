import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    createBrandProfile,
    listBrandProfiles,
    showBrandProfile,
    updateBrandProfile,
} from '@/repositories/brandRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useBrandStore = defineStore('brand', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const items = ref([]);
    const current = ref(null);

    const hasProfile = computed(() => items.value.length > 0);

    async function fetchAll() {
        items.value = (await run(listBrandProfiles)) ?? [];
        current.value = current.value ?? items.value[0] ?? null;
    }

    async function fetchOne(id) {
        current.value = (await run(() => showBrandProfile(id))) ?? null;
    }

    async function save(payload) {
        const result = current.value?.id
            ? await run(() => updateBrandProfile(current.value.id, payload), 'Perfil de marca actualizado.')
            : await run(() => createBrandProfile(payload), 'Perfil de marca creado.');

        if (!result) {
            return false;
        }

        current.value = result;
        await fetchAll();

        return true;
    }

    return { loading, error, fieldErrors, items, current, hasProfile, fetchAll, fetchOne, save };
});
