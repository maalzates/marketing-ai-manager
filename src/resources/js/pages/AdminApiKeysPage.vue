<script setup>
import { onMounted, reactive } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

const form = reactive({ name: '', scopes: '' });

const columns = [
    { key: 'name', label: 'Nombre', sortable: true },
    { key: 'scopes', label: 'Scopes' },
    { key: 'last_used_at', label: 'Último uso', sortable: true },
    { key: 'created_at', label: 'Creada', sortable: true },
    { key: 'actions', label: '' },
];

onMounted(() => admin.fetchApiKeys());

async function create() {
    if (await admin.createKey({ name: form.name, scopes: form.scopes.split(',').map((scope) => scope.trim()).filter(Boolean) })) {
        Object.assign(form, { name: '', scopes: '' });
    }
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">API keys de aplicaciones</h1>
            <p class="mt-1 text-sm text-muted">Se muestran una sola vez. En la base de datos solo queda su hash.</p>
        </header>

        <form class="flex flex-wrap items-end gap-4 rounded-card border border-line bg-surface p-5" @submit.prevent="create">
            <FormField label="Nombre" :errors="admin.fieldErrors.name ?? []" required>
                <input v-model="form.name" type="text" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Scopes" hint="Separados por comas.">
                <input v-model="form.scopes" type="text" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Crear key
            </button>
        </form>

        <div v-if="admin.revealedKey" class="rounded-card border border-warning-200 bg-warning-50 p-5">
            <p class="text-sm font-medium text-warning-700">Cópiala ahora: no volverá a mostrarse.</p>
            <code class="mt-2 block break-all rounded-lg bg-surface px-3 py-2 text-sm">{{ admin.revealedKey }}</code>
        </div>

        <ErrorState v-if="admin.error && !admin.apiKeys.length" :message="admin.error" @retry="admin.fetchApiKeys()" />
        <DataTable v-else :columns="columns" :rows="admin.apiKeys" :loading="admin.loading && !admin.apiKeys.length" empty-title="No hay keys creadas">
            <template #cell-scopes="{ row }">{{ (row.scopes ?? []).join(', ') || '—' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-xs font-medium text-danger-700 hover:underline" @click="admin.removeKey(row.id)">
                    Revocar
                </button>
            </template>
        </DataTable>
    </div>
</template>
