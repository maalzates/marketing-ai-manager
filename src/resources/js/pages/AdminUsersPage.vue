<script setup>
import { onMounted, reactive, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

const form = reactive({ name: '', email: '', role: 'user' });
const removingId = ref(null);

const columns = [
    { key: 'name', label: 'Nombre', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'roles', label: 'Roles' },
    { key: 'last_login_at', label: 'Último acceso', sortable: true },
    { key: 'actions', label: '' },
];

onMounted(() => admin.fetchUsers());

async function create() {
    if (await admin.saveUser(null, { ...form })) {
        Object.assign(form, { name: '', email: '', role: 'user' });
    }
}

async function confirmRemove() {
    await admin.removeUser(removingId.value);
    removingId.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Usuarios</h1>
            <p class="mt-1 text-sm text-muted">Alta, baja y roles. El acceso siempre se hace con Google.</p>
        </header>

        <form class="flex flex-wrap items-end gap-4 rounded-card border border-line bg-surface p-5" @submit.prevent="create">
            <FormField label="Nombre" :errors="admin.fieldErrors.name ?? []" required>
                <input v-model="form.name" type="text" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Email" :errors="admin.fieldErrors.email ?? []" required>
                <input v-model="form.email" type="email" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Rol">
                <select v-model="form.role" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="user">Usuario</option>
                    <option value="admin">Administrador</option>
                </select>
            </FormField>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Crear usuario
            </button>
        </form>

        <ErrorState v-if="admin.error && !admin.users.length" :message="admin.error" @retry="admin.fetchUsers()" />
        <DataTable
            v-else
            :columns="columns"
            :rows="admin.users"
            :loading="admin.loading && !admin.users.length"
            empty-title="No hay usuarios"
            @page="admin.fetchUsers({ page: $event })"
        >
            <template #cell-roles="{ row }">{{ (row.roles ?? []).join(', ') || '—' }}</template>
            <template #cell-actions="{ row }">
                <button type="button" class="text-xs font-medium text-danger-700 hover:underline" @click="removingId = row.id">
                    Eliminar
                </button>
            </template>
        </DataTable>

        <ConfirmDialog
            :open="Boolean(removingId)"
            title="Eliminar usuario"
            message="Perderá el acceso inmediatamente. Su historial de auditoría se conserva."
            confirm-label="Eliminar"
            destructive
            @confirm="confirmRemove"
            @cancel="removingId = null"
        />
    </div>
</template>
