<script setup>
import { computed, onMounted } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useKnowledgeStore } from '@/stores/knowledge';

const props = defineProps({
    concept: { type: String, default: '' },
});

const knowledge = useKnowledgeStore();

const terms = computed(() => Object.values(knowledge.glossary));
const highlighted = computed(() => knowledge.term(props.concept));

onMounted(knowledge.loadGlossary);
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Glosario de métricas</h1>
            <p class="mt-1 text-sm text-muted">Qué mide cada número, cómo se calcula y cuándo no fiarse de él.</p>
        </header>

        <LoadingState v-if="knowledge.loading && !terms.length" />
        <EmptyState v-else-if="!terms.length" title="El glosario está vacío" description="Un administrador debe publicar las entradas desde el panel de admin." />

        <template v-else>
            <article
                v-if="highlighted"
                class="rounded-card border border-brand-200 bg-brand-50 p-6"
            >
                <h2 class="text-lg font-semibold text-brand-700">{{ highlighted.title ?? highlighted.key }}</h2>
                <p class="mt-2 text-sm text-ink">{{ highlighted.body ?? highlighted.summary }}</p>
                <p v-if="highlighted.formula" class="mt-3 text-sm text-muted">Fórmula: {{ highlighted.formula }}</p>
                <p v-if="highlighted.caveat" class="mt-2 text-sm text-warning-700">{{ highlighted.caveat }}</p>
            </article>

            <div class="grid gap-4 md:grid-cols-2">
                <article v-for="term in terms" :key="term.key" class="rounded-card border border-line bg-surface p-5">
                    <h3 class="text-sm font-semibold text-ink">{{ term.title ?? term.key }}</h3>
                    <p class="mt-1 text-sm text-ink">{{ term.summary }}</p>
                    <p v-if="term.mattersWhen" class="mt-2 text-sm text-muted">
                        <span class="font-medium text-ink">Cuándo importa:</span> {{ term.mattersWhen }}
                    </p>
                    <p v-if="term.formula" class="mt-2 text-xs text-muted">Fórmula: {{ term.formula }}</p>
                </article>
            </div>
        </template>
    </div>
</template>
