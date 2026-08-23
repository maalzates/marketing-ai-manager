<script setup>
import { onMounted, ref, watch } from 'vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import { useExperimentsStore } from '@/stores/experiments';
import { useStrategiesStore } from '@/stores/strategies';

const strategies = useStrategiesStore();
const experiments = useExperimentsStore();

const selectedStrategy = ref(null);

const columns = [
    { key: 'name', label: 'Experimento', sortable: true },
    { key: 'type', label: 'Tipo', sortable: true },
    { key: 'status', label: 'Estado', sortable: true },
    { key: 'spend', label: 'Gasto', sortable: true },
    { key: 'max_budget', label: 'Presupuesto máx.', sortable: true },
    { key: 'ends_at', label: 'Fin', sortable: true },
];

onMounted(async () => {
    await strategies.fetchAll();
    selectedStrategy.value = strategies.items[0]?.id ?? null;
});

watch(selectedStrategy, (id) => {
    if (id) {
        experiments.fetchForStrategy(id);
    }
});
</script>

<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Experimentos</h1>
                <p class="mt-1 text-sm text-muted">Cada experimento pertenece a una estrategia y se cierra con un veredicto.</p>
            </div>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Estrategia</span>
                <select v-model="selectedStrategy" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option v-for="strategy in strategies.items" :key="strategy.id" :value="strategy.id">
                        {{ strategy.name }}
                    </option>
                </select>
            </label>
        </header>

        <EmptyState
            v-if="!strategies.loading && !strategies.items.length"
            title="Primero necesitas una estrategia"
            description="Los experimentos siempre viven dentro de una estrategia."
        />
        <ErrorState v-else-if="experiments.error && !experiments.items.length" :message="experiments.error" @retry="experiments.fetchForStrategy(selectedStrategy)" />
        <DataTable
            v-else
            :columns="columns"
            :rows="experiments.items"
            :loading="experiments.loading && !experiments.items.length"
            empty-title="Esta estrategia no tiene experimentos"
            empty-description="Créalos desde el planificador de contenido o acepta una propuesta de campaña."
        >
            <template #cell-name="{ row }">
                <RouterLink :to="{ name: 'experiment-detail', params: { id: row.id } }" class="font-medium text-brand-600 hover:text-brand-700">
                    {{ row.name }}
                </RouterLink>
            </template>
        </DataTable>
    </div>
</template>
