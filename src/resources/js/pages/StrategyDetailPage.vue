<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useExperimentsStore } from '@/stores/experiments';
import { useStrategiesStore } from '@/stores/strategies';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const strategies = useStrategiesStore();
const experiments = useExperimentsStore();
const router = useRouter();
const confirmingDelete = ref(false);

const columns = [
    { key: 'name', label: 'Experimento', sortable: true },
    { key: 'type', label: 'Tipo', sortable: true },
    { key: 'status', label: 'Estado', sortable: true },
    { key: 'starts_at', label: 'Inicio', sortable: true },
    { key: 'ends_at', label: 'Fin', sortable: true },
    { key: 'verdict', label: 'Veredicto' },
];

function load() {
    strategies.fetchOne(props.id);
    experiments.fetchForStrategy(props.id);
}

onMounted(load);

async function remove() {
    confirmingDelete.value = false;
    await strategies.remove(props.id);
    router.push({ name: 'strategies' });
}
</script>

<template>
    <LoadingState v-if="strategies.loading && !strategies.current" />
    <ErrorState v-else-if="strategies.error && !strategies.current" :message="strategies.error" @retry="load" />

    <div v-else-if="strategies.current" class="space-y-6">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ strategies.current.name }}</h1>
                <p class="mt-1 text-sm text-muted">{{ strategies.current.objective }}</p>
            </div>
            <button
                type="button"
                class="rounded-lg border border-danger-200 px-4 py-2 text-sm font-medium text-danger-700 hover:bg-danger-50"
                @click="confirmingDelete = true"
            >
                Eliminar
            </button>
        </header>

        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-card border border-line bg-surface p-4">
                <dt class="text-xs text-muted">Métrica norte</dt>
                <dd class="mt-1 text-sm font-medium">{{ strategies.current.north_star_metric ?? '—' }}</dd>
            </div>
            <div class="rounded-card border border-line bg-surface p-4">
                <dt class="text-xs text-muted">Presupuesto mensual</dt>
                <dd class="mt-1 text-sm font-medium">{{ strategies.current.monthly_budget ?? '—' }}</dd>
            </div>
            <div class="rounded-card border border-line bg-surface p-4">
                <dt class="text-xs text-muted">Estado</dt>
                <dd class="mt-1 text-sm font-medium">{{ strategies.current.status }}</dd>
            </div>
        </dl>

        <section>
            <h2 class="mb-3 text-sm font-semibold">Experimentos de esta estrategia</h2>
            <DataTable
                :columns="columns"
                :rows="experiments.items"
                :loading="experiments.loading && !experiments.items.length"
                empty-title="Sin experimentos todavía"
                empty-description="Un experimento necesita hipótesis, resultado esperado y duración."
            >
                <template #cell-name="{ row }">
                    <RouterLink :to="{ name: 'experiment-detail', params: { id: row.id } }" class="font-medium text-brand-600 hover:text-brand-700">
                        {{ row.name }}
                    </RouterLink>
                </template>
            </DataTable>
        </section>

        <ConfirmDialog
            :open="confirmingDelete"
            title="Eliminar la estrategia"
            message="Se pierde el hilo con sus experimentos e historial. Esta acción no se puede deshacer."
            confirm-label="Eliminar"
            destructive
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>
