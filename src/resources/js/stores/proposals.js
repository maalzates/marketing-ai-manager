import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    acceptProposal,
    listProposals,
    rejectProposal,
    showProposal,
} from '@/repositories/proposalRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useProposalsStore = defineStore('proposals', () => {
    const { loading, error, run } = useAsyncState();

    const items = ref([]);
    const current = ref(null);
    const decidingId = ref(null);

    const pending = computed(() => items.value.filter((item) => item.status === 'pending'));

    async function fetchAll(params = {}) {
        const result = await run(() => listProposals(params));

        items.value = result?.data ?? result ?? [];
    }

    async function fetchOne(id) {
        current.value = (await run(() => showProposal(id))) ?? null;
    }

    async function decide(id, action, payload = {}) {
        decidingId.value = id;

        const result = action === 'accept'
            ? await run(() => acceptProposal(id), 'Propuesta aceptada. La acción quedó registrada.')
            : await run(() => rejectProposal(id, payload), 'Propuesta descartada.');

        decidingId.value = null;

        if (result) {
            items.value = items.value.map((item) => (item.id === id ? result : item));
        }

        return Boolean(result);
    }

    return { loading, error, items, current, decidingId, pending, fetchAll, fetchOne, decide };
});
