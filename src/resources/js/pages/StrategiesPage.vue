<script setup>
import { onMounted, reactive, ref } from 'vue';
import AskAiButton from '@/components/AskAiButton.vue';
import DataTable from '@/components/DataTable.vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import TermTooltip from '@/components/TermTooltip.vue';
import { useAuthStore } from '@/stores/auth';
import { useBrandStore } from '@/stores/brand';
import { useStrategiesStore } from '@/stores/strategies';
import { useUiStore } from '@/stores/ui';
import { matchNorthStar, NORTH_STAR_METRICS, northStarLabel } from '@/support/metrics';
import { formatMoney } from '@/support/money';

const strategies = useStrategiesStore();
const brand = useBrandStore();
const auth = useAuthStore();
const ui = useUiStore();

const creating = ref(false);
const form = reactive({ brand_profile_id: '', name: '', objective: '', north_star_metric: '', monthly_budget: '' });

const columns = [
    { key: 'name', label: 'Estrategia', sortable: true },
    { key: 'objective', label: 'Objetivo' },
    { key: 'north_star_metric', label: 'Métrica norte' },
    { key: 'monthly_budget', label: 'Presupuesto', sortable: true },
    { key: 'status', label: 'Estado', sortable: true },
];

onMounted(async () => {
    strategies.fetchAll();
    await brand.fetchAll();
    form.brand_profile_id = brand.current?.id ?? '';
});

function applyMetricSuggestion(suggestion) {
    const match = matchNorthStar(suggestion);

    match
        ? form.north_star_metric = match
        : ui.info('La sugerencia no nombra ninguna de las cinco métricas, así que la selección no cambió.');
}

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

        <template v-if="creating">
            <EmptyState
                v-if="!brand.hasProfile"
                title="Primero hace falta un perfil de marca"
                description="Una estrategia se cuelga de un perfil de marca: de ahí sale el tono, el público y la promesa."
            >
                <RouterLink :to="{ name: 'brand-profile' }" class="mt-5 inline-block font-medium text-brand-600 hover:text-brand-700">
                    Crear el perfil de marca
                </RouterLink>
            </EmptyState>

            <form v-else class="max-w-2xl space-y-5 rounded-card border border-line bg-surface p-6" @submit.prevent="create">
                <FormField label="Perfil de marca" :errors="strategies.fieldErrors.brand_profile_id ?? []" required>
                    <select v-model="form.brand_profile_id" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                        <option v-for="profile in brand.items" :key="profile.id" :value="profile.id">{{ profile.name }}</option>
                    </select>
                </FormField>

                <FormField label="Nombre" :errors="strategies.fieldErrors.name ?? []" required>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                </FormField>

                <FormField label="Objetivo" :errors="strategies.fieldErrors.objective ?? []" required>
                    <textarea v-model="form.objective" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
                </FormField>

                <FormField label="Métrica norte" :errors="strategies.fieldErrors.north_star_metric ?? []" required>
                    <template #term>
                        <TermTooltip concept="north_star_metric">(?)</TermTooltip>
                    </template>
                    <select v-model="form.north_star_metric" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                        <option value="">Selecciona una métrica</option>
                        <option v-for="metric in NORTH_STAR_METRICS" :key="metric.value" :value="metric.value">{{ metric.label }}</option>
                    </select>
                    <AskAiButton
                        class="mt-2"
                        field="strategy.north_star_metric"
                        :context="{ objective: form.objective, options: NORTH_STAR_METRICS.map((metric) => metric.value) }"
                        @apply="applyMetricSuggestion"
                    />
                </FormField>

                <FormField label="Presupuesto mensual" :errors="strategies.fieldErrors.monthly_budget ?? []">
                    <span class="flex items-center gap-2">
                        <input v-model="form.monthly_budget" type="number" min="0" step="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                        <span class="text-sm text-muted">{{ auth.account?.currency }}</span>
                    </span>
                    <p class="mt-1 text-xs text-muted">
                        Es la moneda de la cuenta, no la de Meta.
                        <a
                            href="https://business.facebook.com/settings/ad-accounts"
                            target="_blank"
                            rel="noopener"
                            class="text-brand-600 underline hover:text-brand-700"
                        >Verificá que coincida con tu cuenta publicitaria</a>: si no coinciden, el presupuesto que
                        se le manda a Meta sale desviado.
                    </p>
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
        </template>

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
            <template #cell-north_star_metric="{ row }">
                {{ northStarLabel(row.north_star_metric) }}
            </template>
            <template #cell-monthly_budget="{ row }">
                {{ formatMoney(row.monthly_budget, auth.account?.currency) }}
            </template>
        </DataTable>
    </div>
</template>
