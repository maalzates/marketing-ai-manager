<script setup>
import { onMounted, reactive, ref } from 'vue';
import AskAiButton from '@/components/AskAiButton.vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import TermTooltip from '@/components/TermTooltip.vue';
import { useStrategiesStore } from '@/stores/strategies';

const strategies = useStrategiesStore();

const creating = ref(false);
const form = reactive({ name: '', objective: '', north_star_metric: '', monthly_budget: '' });

const columns = [
    { key: 'name', label: 'Estrategia', sortable: true },
    { key: 'objective', label: 'Objetivo' },
    { key: 'north_star_metric', label: 'Métrica norte' },
    { key: 'monthly_budget', label: 'Presupuesto', sortable: true },
    { key: 'status', label: 'Estado', sortable: true },
];

onMounted(() => strategies.fetchAll());

async function create() {
    if (await strategies.create({ ...form })) {
        creating.value = false;
        Object.assign(form, { name: '', objective: '', north_star_metric: '', monthly_budget: '' });
    }
}
</script>

<template>
    <div class="space-y-6">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Estrategias</h1>
                <p class="mt-1 text-sm text-muted">Cada estrategia tiene su objetivo, su presupuesto y su historial.</p>
            </div>
            <button
                type="button"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                @click="creating = !creating"
            >
                {{ creating ? 'Cancelar' : 'Nueva estrategia' }}
            </button>
        </header>

        <form v-if="creating" class="max-w-2xl space-y-5 rounded-card border border-line bg-surface p-6" @submit.prevent="create">
            <FormField label="Nombre" :errors="strategies.fieldErrors.name ?? []" required>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>

            <FormField label="Objetivo" :errors="strategies.fieldErrors.objective ?? []" required>
                <textarea v-model="form.objective" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
            </FormField>

            <FormField label="Métrica norte" :errors="strategies.fieldErrors.north_star_metric ?? []">
                <template #term>
                    <TermTooltip concept="north_star_metric">(?)</TermTooltip>
                </template>
                <input v-model="form.north_star_metric" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <AskAiButton
                    class="mt-2"
                    field="strategy.north_star_metric"
                    :context="{ objective: form.objective }"
                    @apply="form.north_star_metric = $event"
                />
            </FormField>

            <FormField label="Presupuesto mensual" :errors="strategies.fieldErrors.monthly_budget ?? []">
                <input v-model="form.monthly_budget" type="number" min="0" step="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <AskAiButton
                    class="mt-2"
                    field="strategy.monthly_budget"
                    :context="{ objective: form.objective, north_star_metric: form.north_star_metric }"
                    @apply="form.monthly_budget = $event"
                />
            </FormField>

            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="strategies.loading"
            >
                Crear estrategia
            </button>
        </form>

        <ErrorState v-if="strategies.error && !strategies.items.length" :message="strategies.error" @retry="strategies.fetchAll()" />
        <DataTable
            v-else
            :columns="columns"
            :rows="strategies.items"
            :loading="strategies.loading && !strategies.items.length"
            empty-title="Aún no hay estrategias"
            empty-description="Una estrategia agrupa experimentos con un mismo objetivo y presupuesto."
        >
            <template #cell-name="{ row }">
                <RouterLink :to="{ name: 'strategy-detail', params: { id: row.id } }" class="font-medium text-brand-600 hover:text-brand-700">
                    {{ row.name }}
                </RouterLink>
            </template>
        </DataTable>
    </div>
</template>
