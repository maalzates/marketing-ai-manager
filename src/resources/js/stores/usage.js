import { defineStore } from 'pinia';
import { ref } from 'vue';
import { fetchUsage, listActionLogs } from '@/repositories/usageRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useUsageStore = defineStore('usage', () => {
    const { loading, error, run } = useAsyncState();

    const summary = ref(null);
    const actionLogs = ref([]);

    async function fetchSummary(params = {}) {
        summary.value = (await run(() => fetchUsage(params))) ?? null;
    }

    async function fetchActionLogs(params = {}) {
        const result = await run(() => listActionLogs(params));

        actionLogs.value = result?.data ?? result ?? [];
    }

    return { loading, error, summary, actionLogs, fetchSummary, fetchActionLogs };
});
