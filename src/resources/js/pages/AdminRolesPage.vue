<script setup>
import { onMounted, reactive } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

const form = reactive({ name: '', label: '' });

const columns = [
    { key: 'name', label: 'Identificador', sortable: true },
    { key: 'label', label: 'Nombre visible' },
    { key: 'users_count', label: 'Usuarios', sortable: true },
    { key: 'actions', label: '' },
];

onMounted(() => admin.fetchRoles());

async function create() {
    if (await admin.saveRole(null, { ...form })) {
        Object.assign(form, { name: '', label: '' });
    }
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Roles</h1>
            <p class="mt-1 text-sm text-muted">Los roles agrupan permisos. Hoy la aplicación distingue administrador de usuario.</p>
        </header>

        <form class="flex flex-wrap items-end gap-4 rounded-card border border-line bg-surface p-5" @submit.prevent="create">
            <FormField label="Identificador" :errors="admin.fieldErrors.name ?? []" required>
                <input v-model="form.name" type="text" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Nombre visible" :errors="admin.fieldErrors.label ?? []">
                <input v-model="form.label" type="text" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Crear rol
            </button>
        </form>

        <ErrorState v-if="admin.error && !admin.roles.length" :message="admin.error" @retry="admin.fetchRoles()" />
        <DataTable v-else :columns="columns" :rows="admin.roles" :loading="admin.loading && !admin.roles.length" empty-title="No hay roles">
            <template #cell-actions="{ row }">
                <button type="button" class="text-xs font-medium text-danger-700 hover:underline" @click="admin.removeRole(row.id)">
                    Eliminar
                </button>
            </template>
        </DataTable>
    </div>
</template>
