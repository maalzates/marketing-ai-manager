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
const provider = ref(null);

const activeKey = computed(() => route.query.step ?? onboarding.resumeStep);
const step = computed(() => onboarding.steps.find((item) => item.step === activeKey.value) ?? onboarding.steps[0] ?? null);
const position = computed(() => onboarding.steps.findIndex((item) => item.step === step.value?.step) + 1);

// The LLM step accepts any of three providers, so the guide shown has to follow the choice.
const chosen = computed(() => step.value?.providers?.find((item) => item.value === provider.value) ?? null);
const guide = computed(() => step.value?.guides?.find((item) => item.metadata?.provider === provider.value)
    ?? step.value?.guides?.[0]
    ?? null);
const busy = computed(() => onboarding.loading || integrations.loading);

onMounted(async () => {
    if (!onboarding.loaded) {
        await onboarding.fetch();
    }

    if (onboarding.isFinished) {
        router.replace({ name: 'strategies' });
    }
});

watch(step, (current) => {
    credential.value = '';
    provider.value = current?.provider ?? current?.providers?.[0]?.value ?? null;
}, { immediate: true });

async function advance() {
    if (onboarding.isFinished) {
        router.replace({ name: 'strategies' });

        return;
    }

    router.replace({ name: 'onboarding', query: { step: onboarding.resumeStep } });
}

// Two calls, in this order: the credential has to exist before the step can verify it.
async function save() {
    if (!await integrations.save(provider.value, { api_key: credential.value })) {
        return;
    }

    if (await onboarding.save(step.value.step, { provider: provider.value })) {
        await advance();
    }
}

async function skip() {
    await onboarding.skip(step.value.step);
    await advance();
}
</script>

<template>
    <LoadingState v-if="onboarding.loading && !onboarding.loaded" label="Preparando tu configuración…" />
    <ErrorState v-else-if="onboarding.error && !step" :message="onboarding.error" @retry="onboarding.fetch()" />

    <section v-else-if="step" class="rounded-card border border-line bg-surface p-8">
        <p class="text-xs uppercase tracking-wide text-muted">Paso {{ position }} de {{ onboarding.steps.length }}</p>
        <h1 class="mt-1 text-xl font-semibold text-ink">{{ step.label }}</h1>
        <p v-if="step.unlocks" class="mt-2 text-sm text-muted">Desbloquea: {{ step.unlocks }}</p>

        <FormField
            v-if="step.providers?.length > 1"
            label="Proveedor"
            hint="Con uno basta. Elige el que tengas a mano."
            class="mt-6"
        >
            <select v-model="provider" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                <option v-for="option in step.providers" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
        </FormField>

        <article v-if="guide" class="mt-6 rounded-card border border-line bg-canvas px-4 py-4">
            <h2 class="text-sm font-semibold text-ink">{{ guide.title }}</h2>
            <p class="mt-2 whitespace-pre-line text-sm text-muted">{{ guide.body }}</p>
            <a
                v-if="guide.metadata?.docs_url"
                :href="guide.metadata.docs_url"
                target="_blank"
                rel="noopener"
                class="mt-3 inline-block text-sm text-brand-600 hover:text-brand-700"
            >
                Abrir la pantalla del proveedor
            </a>
        </article>
        <p v-else class="mt-6 rounded-card border border-dashed border-line bg-canvas px-4 py-6 text-center text-sm text-muted">
            La guía visual de este paso aún no está publicada.
        </p>

        <div v-if="chosen?.kind === 'oauth'" class="mt-6">
            <button
                type="button"
                class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="busy"
                @click="integrations.connectWithOauth(provider)"
            >
                Conectar con {{ chosen.label }}
            </button>
        </div>

        <form v-else class="mt-6 space-y-4" @submit.prevent="save">
            <FormField
                label="Credencial"
                hint="La guardamos cifrada y hacemos una llamada de prueba real al proveedor antes de darla por válida."
                :errors="integrations.fieldErrors?.api_key ?? []"
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
                :disabled="busy || !credential"
            >
                {{ busy ? 'Verificando…' : 'Guardar y verificar' }}
            </button>
        </form>

        <p v-if="step.status === 'completed'" class="mt-4 rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700">
            Conectado correctamente.
        </p>
        <p v-else-if="onboarding.error ?? integrations.error" class="mt-4 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700">
            {{ onboarding.error ?? integrations.error }}
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
