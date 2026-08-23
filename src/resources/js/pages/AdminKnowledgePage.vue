<script setup>
import { onMounted, reactive } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

const form = reactive({ type: 'glossary', key: '', title: '', summary: '', body: '' });

const columns = [
    { key: 'type', label: 'Tipo', sortable: true },
    { key: 'key', label: 'Clave', sortable: true },
    { key: 'title', label: 'Título' },
    { key: 'version', label: 'Versión', sortable: true },
    { key: 'actions', label: '' },
];

onMounted(() => admin.fetchKnowledge());

async function create() {
    if (await admin.saveKnowledge(null, { ...form })) {
        Object.assign(form, { type: form.type, key: '', title: '', summary: '', body: '' });
    }
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Base de conocimiento</h1>
            <p class="mt-1 text-sm text-muted">
                Glosario, reglas del dominio, guías del onboarding y plantillas de prompts. Editable sin desplegar código.
            </p>
        </header>

        <form class="grid max-w-3xl gap-4 rounded-card border border-line bg-surface p-5 md:grid-cols-2" @submit.prevent="create">
            <FormField label="Tipo">
                <select v-model="form.type" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="glossary">Glosario</option>
                    <option value="domain_rule">Regla del dominio</option>
                    <option value="onboarding_guide">Guía de onboarding</option>
                    <option value="prompt_template">Plantilla de prompt</option>
                </select>
            </FormField>
            <FormField label="Clave" :errors="admin.fieldErrors.key ?? []" required>
                <input v-model="form.key" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Título" :errors="admin.fieldErrors.title ?? []" required>
                <input v-model="form.title" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Resumen (tooltip)" :errors="admin.fieldErrors.summary ?? []">
                <input v-model="form.summary" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Contenido completo" :errors="admin.fieldErrors.body ?? []">
                <textarea v-model="form.body" rows="5" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
            </FormField>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 md:col-span-2 md:justify-self-start">
                Publicar entrada
            </button>
        </form>

        <ErrorState v-if="admin.error && !admin.knowledge.length" :message="admin.error" @retry="admin.fetchKnowledge()" />
        <DataTable v-else :columns="columns" :rows="admin.knowledge" :loading="admin.loading && !admin.knowledge.length" empty-title="Sin entradas publicadas">
            <template #cell-actions="{ row }">
                <button type="button" class="text-xs font-medium text-danger-700 hover:underline" @click="admin.removeKnowledge(row.id)">
                    Eliminar
                </button>
            </template>
        </DataTable>
    </div>
</template>
