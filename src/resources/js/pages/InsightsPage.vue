<script setup>
import { onMounted, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useCompetitorsStore } from '@/stores/competitors';

const competitors = useCompetitorsStore();
const kind = ref('');

function load() {
    competitors.fetchInsights(kind.value ? { kind: kind.value } : {});
}

onMounted(load);
watch(kind, load);
</script>

<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Insights</h1>
                <p class="mt-1 text-sm text-muted">Patrones detectados e ideas mineadas de comentarios, con su evidencia.</p>
            </div>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Tipo</span>
                <select v-model="kind" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <option value="pattern">Patrones de contenido</option>
                    <option value="idea">Ideas de comentarios</option>
                    <option value="sentiment">Sentimiento</option>
                </select>
            </label>
        </header>

        <LoadingState v-if="competitors.loading && !competitors.insights.length" />
        <ErrorState v-else-if="competitors.error && !competitors.insights.length" :message="competitors.error" @retry="load" />
        <EmptyState
            v-else-if="!competitors.insights.length"
            title="Sin insights todavía"
            description="Se generan cuando los jobs de scraping y análisis terminan de procesar a tus competidores."
        />
        <div v-else class="grid gap-4 md:grid-cols-2">
            <article v-for="insight in competitors.insights" :key="insight.id" class="rounded-card border border-line bg-surface p-5">
                <header class="flex items-start justify-between gap-3">
                    <h2 class="text-sm font-semibold">{{ insight.title }}</h2>
                    <span class="rounded-full bg-canvas px-2.5 py-1 text-xs text-muted">{{ insight.kind }}</span>
                </header>
                <p class="mt-2 text-sm text-muted">{{ insight.summary }}</p>
                <p v-if="insight.evidence_count" class="mt-3 text-xs text-muted">
                    Evidencia: {{ insight.evidence_count }} menciones · {{ insight.competitor_handle ?? 'varias cuentas' }}
                </p>
            </article>
        </div>
    </div>
</template>
