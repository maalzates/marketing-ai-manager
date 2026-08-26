<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useOnboardingStore } from '@/stores/onboarding';

defineProps({
    compact: { type: Boolean, default: false },
});

const onboarding = useOnboardingStore();

const total = computed(() => onboarding.steps.length);

// This list answers "what works right now", so it reads the live integration behind each step
// rather than the step's recorded status: a step skipped months ago and connected yesterday is
// connected, and a credential revoked last week has to stop reading as done.
const isConnected = (step) => Boolean(step.integrations?.some((one) => one.status === 'connected'));

const connectedCount = computed(() => onboarding.steps.filter(isConnected).length);
const percent = computed(() => (total.value ? Math.round((connectedCount.value / total.value) * 100) : 0));

onMounted(() => {
    if (!onboarding.loaded) {
        onboarding.fetch();
    }
});
</script>

<template>
    <RouterLink
        v-if="compact"
        :to="{ name: 'onboarding' }"
        class="flex items-center gap-2 rounded-full border border-line px-3 py-1.5 text-xs text-muted hover:bg-canvas"
    >
        <span class="size-2 rounded-full" :class="percent === 100 ? 'bg-success-500' : 'bg-warning-500'" />
        Configuración {{ connectedCount }}/{{ total }}
    </RouterLink>

    <section v-else class="rounded-card border border-line bg-surface p-5">
        <header class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink">Checklist de configuración</h2>
            <span class="text-xs text-muted">{{ percent }}%</span>
        </header>

        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-line">
            <span class="block h-full rounded-full bg-brand-600" :style="{ width: `${percent}%` }" />
        </div>

        <ul class="mt-4 space-y-2 text-sm">
            <li v-for="step in onboarding.steps" :key="step.step" class="flex items-center justify-between gap-3">
                <span class="text-ink">{{ step.label ?? step.step }}</span>
                <RouterLink
                    v-if="!isConnected(step)"
                    :to="{ name: 'onboarding', query: { step: step.step } }"
                    class="text-xs font-medium text-brand-600 hover:text-brand-700"
                >
                    {{ step.status === 'skipped' ? 'Configurar ahora' : 'Continuar' }}
                </RouterLink>
                <span v-else class="text-xs text-success-700">Conectado</span>
            </li>
        </ul>
    </section>
</template>
