import { defineStore } from 'pinia';
import { ref } from 'vue';
import { listReports, showReport } from '@/repositories/reportRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useReportsStore = defineStore('reports', () => {
    const { loading, error, run } = useAsyncState();

    const items = ref([]);
    const current = ref(null);

    async function fetchAll(params = {}) {
        const result = await run(() => listReports(params));

        items.value = result?.data ?? result ?? [];
    }

    async function fetchOne(id) {
        current.value = (await run(() => showReport(id))) ?? null;
    }

    return { loading, error, items, current, fetchAll, fetchOne };
});
