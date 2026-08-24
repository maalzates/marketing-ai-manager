import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { completeStep, fetchOnboarding, skipStep as skipStepRequest } from '@/repositories/onboardingRepository';
import { useAsyncState } from '@/stores/useAsyncState';

const RESOLVED = ['completed', 'skipped'];

export const useOnboardingStore = defineStore('onboarding', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const steps = ref([]);
    const status = ref('pending');
    const loaded = ref(false);

    const pendingSteps = computed(() => steps.value.filter((step) => !RESOLVED.includes(step.status)));
    const isFinished = computed(() => RESOLVED.includes(status.value) || (loaded.value && pendingSteps.value.length === 0));
    const mustResume = computed(() => loaded.value && !isFinished.value);
    const resumeStep = computed(() => pendingSteps.value[0]?.step ?? steps.value[0]?.step ?? null);
    const completedCount = computed(() => steps.value.filter((step) => RESOLVED.includes(step.status)).length);

    function apply(payload) {
        steps.value = payload?.steps ?? [];
        status.value = payload?.completed_at ? 'completed' : 'pending';
        loaded.value = true;
    }

    async function fetch() {
        apply(await run(fetchOnboarding));
    }

    async function save(step, payload) {
        const result = await run(
            () => completeStep(step, payload),
            'Paso guardado y verificado.',
        );

        if (!result) {
            return false;
        }

        apply(result);

        return true;
    }

    async function skip(step) {
        apply(await run(() => skipStepRequest(step), 'Podrás configurarlo después desde Configuración.'));
    }

    return {
        loading,
        error,
        fieldErrors,
        steps,
        status,
        loaded,
        pendingSteps,
        isFinished,
        mustResume,
        resumeStep,
        completedCount,
        fetch,
        save,
        skip,
    };
});
