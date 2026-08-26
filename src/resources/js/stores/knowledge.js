import { defineStore } from 'pinia';
import { ref } from 'vue';
import { listKnowledge, showKnowledge } from '@/repositories/knowledgeRepository';
import { useAsyncState } from '@/stores/useAsyncState';

// The value of `KnowledgeType::GlossaryTerm`. It is a route segment the backend validates
// against that enum, so a friendlier-looking 'glossary' fails every fetch with
// "The selected type is invalid" — and every tooltip in the application goes quiet.
const GLOSSARY = 'glossary_term';

export const useKnowledgeStore = defineStore('knowledge', () => {
    const { loading, error, run } = useAsyncState();

    const glossary = ref({});
    const entries = ref([]);
    const glossaryLoaded = ref(false);

    // The glossary backs every <TermTooltip> in the app, so it is fetched once
    // and shared instead of once per term.
    async function loadGlossary() {
        if (glossaryLoaded.value) {
            return;
        }

        glossaryLoaded.value = true;

        const result = await run(() => listKnowledge(GLOSSARY));
        const items = result?.data ?? result ?? [];

        glossary.value = Object.fromEntries(items.map((item) => [item.key, item]));
    }

    function term(key) {
        return glossary.value[key] ?? null;
    }

    async function fetchType(type, params = {}) {
        const result = await run(() => listKnowledge(type, params));

        entries.value = result?.data ?? result ?? [];
    }

    async function fetchEntry(type, key) {
        return run(() => showKnowledge(type, key));
    }

    return { loading, error, glossary, entries, glossaryLoaded, loadGlossary, term, fetchType, fetchEntry };
});
