<script setup>
import { computed, onMounted, ref } from 'vue';
import ErrorState from '@/components/ErrorState.vue';
import LearningPhaseGuardModal from '@/components/LearningPhaseGuardModal.vue';
import LoadingState from '@/components/LoadingState.vue';
import MetricSparkline from '@/components/MetricSparkline.vue';
import TermTooltip from '@/components/TermTooltip.vue';
import { useExperimentsStore } from '@/stores/experiments';

const props = defineProps({
    id: { type: [String, Number], required: true },
});

const experiments = useExperimentsStore();

const tab = ref('summary');
const guardedAction = ref(null);

const experiment = computed(() => experiments.current);
const spendPercent = computed(() => Math.round(experiments.spendRatio * 100));
const overBudget = computed(() => spendPercent.value >= 90);
const spendSeries = computed(() => experiments.metrics.map((day) => Number(day.spend ?? 0)));

function load() {
    experiments.fetchOne(props.id);
    experiments.fetchMetrics(props.id);
}

onMounted(load);

function requestAction(action) {
    if (experiments.isInLearningPhase) {
        guardedAction.value = action;

        return;
    }

    perform(action);
}

function perform(action) {
    guardedAction.value = null;

    if (action === 'pause') {
        experiments.update(props.id, { status: 'paused' });

        return;
    }

    experiments.sync(props.id);
}
</script>

<template>
    <LoadingState v-if="experiments.loading && !experiment" />
    <ErrorState v-else-if="experiments.error && !experiment" :message="experiments.error" @retry="load" />

    <div v-else-if="experiment" class="space-y-6">
        <header class="rounded-card border border-line bg-surface p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ experiment.name }}</h1>
                    <p class="mt-1 text-sm text-muted">
                        {{ experiment.type }} · {{ experiment.status }} ·
                        del {{ experiment.starts_at ?? '—' }} al {{ experiment.ends_at ?? '—' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-line px-3 py-2 text-sm hover:bg-canvas"
                        @click="requestAction('sync')"
                    >
                        Sincronizar métricas
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-danger-200 px-3 py-2 text-sm font-medium text-danger-700 hover:bg-danger-50"
                        @click="requestAction('pause')"
                    >
                        Pausar
                    </button>
                </div>
            </div>

            <div class="mt-6">
                <div class="flex items-baseline justify-between text-sm">
                    <span class="font-medium">Gasto acumulado</span>
                    <span :class="overBudget ? 'text-danger-700' : 'text-muted'">
                        {{ experiment.spend ?? 0 }} de {{ experiment.max_budget ?? 0 }} ({{ spendPercent }}%)
                    </span>
                </div>
                <div class="mt-2 h-3 w-full overflow-hidden rounded-full bg-line">
                    <span
                        class="block h-full rounded-full"
                        :class="overBudget ? 'bg-danger-500' : 'bg-brand-600'"
                        :style="{ width: `${spendPercent}%` }"
                    />
                </div>
            </div>
        </header>

        <section
            v-if="experiment.warnings?.length"
            class="rounded-card border border-warning-200 bg-warning-50 p-5"
        >
            <h2 class="text-sm font-semibold text-warning-700">Advertencias de este experimento</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-ink">
                <li v-for="(warning, index) in experiment.warnings" :key="index">
                    {{ typeof warning === 'string' ? warning : warning.message }}
                </li>
            </ul>
        </section>

        <section class="rounded-card border border-line bg-surface p-5">
            <h2 class="text-sm font-semibold">
                Fase de aprendizaje
                <TermTooltip concept="learning_phase">(?)</TermTooltip>
            </h2>
            <p class="mt-2 text-sm text-ink">
                Estado: {{ experiment.learning_phase?.status ?? 'sin datos' }} ·
                del {{ experiment.learning_phase?.started_at ?? '—' }}
                al {{ experiment.learning_phase?.ends_at ?? '—' }}
            </p>
            <p v-if="experiments.isInLearningPhase" class="mt-2 text-sm text-muted">
                Los costos de estos días son volátiles por diseño. Evaluar ahora es evaluar ruido.
            </p>
        </section>

        <nav class="flex gap-2 border-b border-line">
            <button
                v-for="option in [{ key: 'summary', label: 'Resumen' }, { key: 'metrics', label: 'Métricas' }]"
                :key="option.key"
                type="button"
                class="border-b-2 px-4 py-2 text-sm"
                :class="tab === option.key ? 'border-brand-600 font-medium text-brand-700' : 'border-transparent text-muted hover:text-ink'"
                @click="tab = option.key"
            >
                {{ option.label }}
            </button>
        </nav>

        <section v-if="tab === 'summary'" class="grid gap-4 md:grid-cols-2">
            <article class="rounded-card border border-line bg-surface p-5">
                <h3 class="text-sm font-semibold">Hipótesis</h3>
                <p class="mt-2 text-sm text-muted">{{ experiment.hypothesis ?? 'Sin hipótesis registrada.' }}</p>
            </article>
            <article class="rounded-card border border-line bg-surface p-5">
                <h3 class="text-sm font-semibold">Resultado esperado</h3>
                <p class="mt-2 text-sm text-muted">{{ experiment.expected_result ?? 'Sin resultado esperado registrado.' }}</p>
            </article>
            <article class="rounded-card border border-line bg-surface p-5 md:col-span-2">
                <h3 class="text-sm font-semibold">Gasto diario</h3>
                <MetricSparkline class="mt-2 text-brand-600" :values="spendSeries" label="Spend por día" :width="480" :height="60" />
            </article>
        </section>

        <section v-else class="overflow-hidden rounded-card border border-line bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="bg-canvas text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Día</th>
                        <th scope="col" class="px-4 py-3 font-medium">Gasto</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            <TermTooltip concept="impressions">Impresiones</TermTooltip>
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium"><TermTooltip concept="cpm">CPM</TermTooltip></th>
                        <th scope="col" class="px-4 py-3 font-medium"><TermTooltip concept="ctr">CTR</TermTooltip></th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            <TermTooltip concept="conversions">Conversiones</TermTooltip>
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium"><TermTooltip concept="cpa">CPA</TermTooltip></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="day in experiments.metrics" :key="day.date">
                        <td class="px-4 py-3">{{ day.date }}</td>
                        <td class="px-4 py-3">{{ day.spend ?? '—' }}</td>
                        <td class="px-4 py-3">{{ day.impressions ?? '—' }}</td>
                        <td class="px-4 py-3">{{ day.cpm ?? '—' }}</td>
                        <td class="px-4 py-3">{{ day.ctr ?? '—' }}</td>
                        <td class="px-4 py-3">{{ day.conversions ?? '—' }}</td>
                        <td class="px-4 py-3">{{ day.cpa ?? '—' }}</td>
                    </tr>
                    <tr v-if="!experiments.metrics.length">
                        <td colspan="7" class="px-4 py-8 text-center text-muted">
                            Todavía no hay métricas sincronizadas para este experimento.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <LearningPhaseGuardModal
            :open="Boolean(guardedAction)"
            :experiment="experiment"
            :action="guardedAction === 'pause' ? 'una pausa' : 'una sincronización'"
            @confirm="perform(guardedAction)"
            @cancel="guardedAction = null"
        />
    </div>
</template>
