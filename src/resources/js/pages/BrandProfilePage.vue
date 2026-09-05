<script setup>
import { onMounted, reactive, watch } from 'vue';
import AskAiButton from '@/components/AskAiButton.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useBrandStore } from '@/stores/brand';

const brand = useBrandStore();

const KINDS = [
    { value: 'personal_brand', label: 'Marca personal' },
    { value: 'company', label: 'Empresa' },
    { value: 'project', label: 'Proyecto' },
];

const form = reactive({
    name: '',
    kind: 'personal_brand',
    description: '',
    value_proposition: '',
    niche: '',
    buyer_persona: '',
    tone_of_voice: '',
});

onMounted(brand.fetchAll);

watch(() => brand.current, (profile) => {
    Object.assign(form, {
        name: profile?.name ?? '',
        kind: profile?.kind ?? 'personal_brand',
        description: profile?.description ?? '',
        value_proposition: profile?.value_proposition ?? '',
        niche: profile?.niche ?? '',
        buyer_persona: profile?.buyer_personas?.[0] ?? '',
        tone_of_voice: profile?.tone_of_voice ?? '',
    });
});

// La API guarda varias buyer personas; la pantalla pide una sola, en prosa.
function save() {
    const { buyer_persona: persona, ...fields } = form;

    brand.save({ ...fields, buyer_personas: persona ? [persona] : [] });
}
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

        <form v-else class="max-w-2xl space-y-5 rounded-card border border-line bg-surface p-6" @submit.prevent="save">
            <FormField label="Nombre de la marca" :errors="brand.fieldErrors.name ?? []" required>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>

            <FormField label="Tipo de marca" :errors="brand.fieldErrors.kind ?? []" required>
                <select v-model="form.kind" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option v-for="kind in KINDS" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
                </select>
            </FormField>

            <FormField
                label="Descripción"
                hint="Qué hace la marca y para quién. Con esto la IA puede proponer el resto del perfil."
                :errors="brand.fieldErrors.description ?? []"
                required
            >
                <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
            </FormField>

            <FormField label="Tu marca en una frase" :errors="brand.fieldErrors.value_proposition ?? []">
                <textarea v-model="form.value_proposition" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.value_proposition"
                    :context="{ description: form.description, niche: form.niche }"
                    @apply="form.value_proposition = $event"
                />
            </FormField>

            <FormField label="Nicho" :errors="brand.fieldErrors.niche ?? []">
                <input v-model="form.niche" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.niche"
                    :context="{ description: form.description }"
                    @apply="form.niche = $event"
                />
            </FormField>

            <FormField label="Buyer persona" :errors="brand.fieldErrors.buyer_personas ?? []">
                <textarea v-model="form.buyer_persona" rows="3" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.buyer_persona"
                    :context="{ description: form.description, niche: form.niche }"
                    @apply="form.buyer_persona = $event"
                />
            </FormField>

            <FormField label="Tono de comunicación" :errors="brand.fieldErrors.tone_of_voice ?? []">
                <input v-model="form.tone_of_voice" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                <AskAiButton
                    class="mt-2"
                    field="brand_profile.tone_of_voice"
                    :context="{ description: form.description, buyer_persona: form.buyer_persona }"
                    @apply="form.tone_of_voice = $event"
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
