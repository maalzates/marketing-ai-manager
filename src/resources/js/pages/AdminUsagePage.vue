<script setup>
import { computed, onMounted } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import MetricSparkline from '@/components/MetricSparkline.vue';
import { useAdminStore } from '@/stores/admin';
import { useUsageStore } from '@/stores/usage';

const admin = useAdminStore();
const usage = useUsageStore();

const columns = [
    { key: 'created_at', label: 'Fecha', sortable: true },
    { key: 'user_name', label: 'Usuario', sortable: true },
    { key: 'action', label: 'Acción', sortable: true },
    { key: 'subject', label: 'Sobre' },
];

const tokensSeries = computed(() => (admin.usage?.daily ?? []).map((day) => Number(day.tokens ?? 0)));

function load() {
    admin.fetchUsage();
    usage.fetchActionLogs();
}

onMounted(load);
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Consumo y auditoría</h1>
            <p class="mt-1 text-sm text-muted">Tokens, llamadas externas y acciones registradas por usuario.</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-3">
            <article class="rounded-card border border-line bg-surface p-5">
                <h2 class="text-xs text-muted">Tokens del mes</h2>
                <p class="mt-1 text-lg font-semibold">{{ admin.usage?.tokens_month ?? '—' }}</p>
            </article>
            <article class="rounded-card border border-line bg-surface p-5">
                <h2 class="text-xs text-muted">Llamadas a Apify</h2>
                <p class="mt-1 text-lg font-semibold">{{ admin.usage?.apify_calls_month ?? '—' }}</p>
            </article>
            <article class="rounded-card border border-line bg-surface p-5">
                <h2 class="text-xs text-muted">Costo estimado</h2>
                <p class="mt-1 text-lg font-semibold">{{ admin.usage?.estimated_cost ?? '—' }}</p>
            </article>
        </div>

        <section class="rounded-card border border-line bg-surface p-5">
            <h2 class="text-sm font-semibold">Tokens por día</h2>
            <MetricSparkline class="mt-2" :values="tokensSeries" label="Tokens" :width="480" :height="60" />
        </section>

        <ErrorState v-if="usage.error && !usage.actionLogs.length" :message="usage.error" @retry="load" />
        <DataTable
            v-else
            :columns="columns"
            :rows="usage.actionLogs"
            :loading="usage.loading && !usage.actionLogs.length"
            empty-title="Sin acciones registradas"
            @page="usage.fetchActionLogs({ page: $event })"
        />
    </div>
</template>
