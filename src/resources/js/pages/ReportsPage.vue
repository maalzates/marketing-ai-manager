<script setup>
import { onMounted } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import { useReportsStore } from '@/stores/reports';

const reports = useReportsStore();

const columns = [
    { key: 'title', label: 'Reporte', sortable: true },
    { key: 'experiment_name', label: 'Experimento' },
    { key: 'verdict', label: 'Veredicto', sortable: true },
    { key: 'created_at', label: 'Generado', sortable: true },
];

onMounted(() => reports.fetchAll());
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Reportes</h1>
            <p class="mt-1 text-sm text-muted">Cada experimento cerrado deja su reporte y su veredicto en el historial.</p>
        </header>

        <ErrorState v-if="reports.error && !reports.items.length" :message="reports.error" @retry="reports.fetchAll()" />
        <DataTable
            v-else
            :columns="columns"
            :rows="reports.items"
            :loading="reports.loading && !reports.items.length"
            empty-title="Sin reportes todavía"
            empty-description="Se generan al vencer un experimento o al cerrarlo anticipadamente."
            @page="reports.fetchAll({ page: $event })"
        >
            <template #cell-title="{ row }">
                <button type="button" class="font-medium text-brand-600 hover:text-brand-700" @click="reports.fetchOne(row.id)">
                    {{ row.title }}
                </button>
            </template>
        </DataTable>

        <article v-if="reports.current" class="rounded-card border border-line bg-surface p-6">
            <h2 class="text-base font-semibold">{{ reports.current.title }}</h2>
            <p class="mt-1 text-xs uppercase tracking-wide text-muted">Veredicto: {{ reports.current.verdict ?? '—' }}</p>
            <p class="mt-4 whitespace-pre-line text-sm text-ink">{{ reports.current.body }}</p>
        </article>
    </div>
</template>
