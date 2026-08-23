<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useKnowledgeStore } from '@/stores/knowledge';

const props = defineProps({
    concept: { type: String, required: true },
});

const knowledge = useKnowledgeStore();
const router = useRouter();
const visible = ref(false);

const entry = computed(() => knowledge.term(props.concept));
const title = computed(() => entry.value?.title ?? props.concept.toUpperCase());
const summary = computed(() => entry.value?.summary ?? 'Sin explicación disponible todavía.');

onMounted(knowledge.loadGlossary);

function openFullEntry() {
    window.open(router.resolve({ name: 'glossary', params: { concept: props.concept } }).href, '_blank', 'noopener');
}
</script>

<template>
    <span class="relative inline-flex">
        <button
            type="button"
            class="cursor-help border-b border-dotted border-muted text-inherit"
            @mouseenter="visible = true"
            @mouseleave="visible = false"
            @focus="visible = true"
            @blur="visible = false"
            @click="openFullEntry"
        >
            <slot>{{ title }}</slot>
        </button>
        <span
            v-if="visible"
            class="absolute bottom-full left-0 z-30 mb-2 w-64 rounded-card border border-line bg-surface p-3 text-left shadow-lg"
            role="tooltip"
        >
            <span class="block text-xs font-semibold text-ink">{{ title }}</span>
            <span class="mt-1 block text-xs text-muted">{{ summary }}</span>
            <span class="mt-2 block text-xs text-brand-600">Clic para abrir la entrada completa</span>
        </span>
    </span>
</template>
