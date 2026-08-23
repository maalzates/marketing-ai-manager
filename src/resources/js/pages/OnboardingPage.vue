<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useIntegrationsStore } from '@/stores/integrations';
import { useOnboardingStore } from '@/stores/onboarding';

const onboarding = useOnboardingStore();
const integrations = useIntegrationsStore();
const route = useRoute();
const router = useRouter();

const credential = ref('');

const activeKey = computed(() => route.query.step ?? onboarding.resumeStep);
const step = computed(() => onboarding.steps.find((item) => item.key === activeKey.value) ?? onboarding.steps[0] ?? null);
const position = computed(() => onboarding.steps.findIndex((item) => item.key === step.value?.key) + 1);

onMounted(async () => {
    if (!onboarding.loaded) {
        await onboarding.fetch();
    }

    if (onboarding.isFinished) {
        router.replace({ name: 'strategies' });
    }
});

watch(step, () => { credential.value = ''; });

async function advance() {
    if (onboarding.isFinished) {
        router.replace({ name: 'strategies' });

        return;
    }

    router.replace({ name: 'onboarding', query: { step: onboarding.resumeStep } });
}

async function save() {
    if (await onboarding.save(step.value.key, { credential: credential.value })) {
        await advance();
    }
}

async function skip() {
    await onboarding.skip(step.value.key);
    await advance();
}
</script>

<template>
    <LoadingState v-if="onboarding.loading && !onboarding.loaded" label="Preparando tu configuración…" />
    <ErrorState v-else-if="onboarding.error && !step" :message="onboarding.error" @retry="onboarding.fetch()" />

    <section v-else-if="step" class="rounded-card border border-line bg-surface p-8">
        <p class="text-xs uppercase tracking-wide text-muted">Paso {{ position }} de {{ onboarding.steps.length }}</p>
        <h1 class="mt-1 text-xl font-semibold text-ink">{{ step.label ?? step.key }}</h1>
        <p v-if="step.description" class="mt-2 text-sm text-muted">{{ step.description }}</p>

        <div v-if="step.guide?.images?.length" class="mt-6 grid gap-4 sm:grid-cols-2">
            <figure v-for="image in step.guide.images" :key="image.url" class="overflow-hidden rounded-card border border-line">
                <img :src="image.url" :alt="image.caption ?? step.label" class="w-full" loading="lazy">
                <figcaption v-if="image.caption" class="bg-canvas px-3 py-2 text-xs text-muted">{{ image.caption }}</figcaption>
            </figure>
        </div>
        <p v-else class="mt-6 rounded-card border border-dashed border-line bg-canvas px-4 py-6 text-center text-sm text-muted">
            La guía visual de este paso aún no está publicada.
        </p>

        <div v-if="step.kind === 'oauth'" class="mt-6">
            <button
                type="button"
                class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="integrations.loading"
                @click="integrations.connectWithOauth(step.provider)"
            >
                Conectar con {{ step.label ?? step.provider }}
            </button>
        </div>

        <form v-else class="mt-6 space-y-4" @submit.prevent="save">
            <FormField
                label="Credencial"
                hint="La guardamos cifrada y hacemos una llamada de prueba real al proveedor antes de darla por válida."
                :errors="onboarding.fieldErrors.credential ?? []"
                required
            >
                <input
                    v-model="credential"
                    type="password"
                    autocomplete="off"
                    class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm"
                    placeholder="sk-…"
                >
            </FormField>

            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="onboarding.loading || !credential"
            >
                {{ onboarding.loading ? 'Verificando…' : 'Guardar y verificar' }}
            </button>
        </form>

        <p v-if="step.status === 'completed'" class="mt-4 rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700">
            Conectado correctamente.
        </p>
        <p v-else-if="step.last_error" class="mt-4 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700">
            {{ step.last_error }}
        </p>

        <footer class="mt-8 flex items-center justify-between border-t border-line pt-5">
            <button type="button" class="text-sm text-muted underline hover:text-ink" @click="skip">
                Configurar después
            </button>
            <RouterLink :to="{ name: 'strategies' }" class="text-sm font-medium text-brand-600 hover:text-brand-700">
                Ir a crear mi primera estrategia
            </RouterLink>
        </footer>
    </section>
</template>
