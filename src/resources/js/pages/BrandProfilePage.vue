<script setup>
import { onMounted, reactive, watch } from 'vue';
import AskAiButton from '@/components/AskAiButton.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useBrandStore } from '@/stores/brand';

const brand = useBrandStore();

const form = reactive({
    name: '',
    one_liner: '',
    niche: '',
    buyer_persona: '',
    tone: '',
});

onMounted(brand.fetchAll);

watch(() => brand.current, (profile) => {
    Object.assign(form, {
        name: profile?.name ?? '',
        one_liner: profile?.one_liner ?? '',
        niche: profile?.niche ?? '',
        buyer_persona: profile?.buyer_persona ?? '',
        tone: profile?.tone ?? '',
    });
});
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Perfil de marca</h1>
            <p class="mt-1 text-sm text-muted">
                Es el contexto que la IA usa en cada propuesta. Cuanto más preciso, mejores las sugerencias.
            </p>
        </header>

        <LoadingState v-if="brand.loading && !brand.current" />
        <ErrorState v-else-if="brand.error && !brand.current" :message="brand.error" @retry="brand.fetchAll()" />

        <form v-else class="max-w-2xl space-y-5 rounded-card border border-line bg-surface p-6" @submit.prevent="brand.save(form)">
            <FormField label="Nombre de la marca" :errors="brand.fieldErrors.name ?? []" required>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>

            <FormField
                label="Describe tu marca en una frase"
                hint="Con esto la IA puede proponer el resto del perfil."
                :errors="brand.fieldErrors.one_liner ?? []"
            >
                <textarea v-model="form.one_liner" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
            </FormField>

            <FormField label="Nicho" :errors="brand.fieldErrors.niche ?? []">
                <input v-model="form.niche" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.niche"
                    :context="{ one_liner: form.one_liner }"
                    @apply="form.niche = $event"
                />
            </FormField>

            <FormField label="Buyer persona" :errors="brand.fieldErrors.buyer_persona ?? []">
                <textarea v-model="form.buyer_persona" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.buyer_persona"
                    :context="{ one_liner: form.one_liner, niche: form.niche }"
                    @apply="form.buyer_persona = $event"
                />
            </FormField>

            <FormField label="Tono de comunicación" :errors="brand.fieldErrors.tone ?? []">
                <input v-model="form.tone" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.tone"
                    :context="{ one_liner: form.one_liner, buyer_persona: form.buyer_persona }"
                    @apply="form.tone = $event"
                />
            </FormField>

            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="brand.loading"
            >
                Guardar perfil
            </button>
        </form>
    </div>
</template>
