import { defineStore } from 'pinia';
import { ref } from 'vue';
import {
    createAdminKnowledge,
    createApiKey,
    createRole,
    createUser,
    deleteAdminKnowledge,
    deleteApiKey,
    deleteRole,
    deleteUser,
    fetchAdminSettings,
    fetchAdminUsage,
    listAdminKnowledge,
    listApiKeys,
    listRoles,
    listUsers,
    updateAdminKnowledge,
    updateAdminSettings,
    updateRole,
    updateUser,
} from '@/repositories/adminRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useAdminStore = defineStore('admin', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const users = ref([]);
    const roles = ref([]);
    const apiKeys = ref([]);
    const knowledge = ref([]);
    const settings = ref({});
    const usage = ref(null);
    const revealedKey = ref(null);

    const unwrap = (result) => result?.data ?? result ?? [];

    async function fetchUsers(params = {}) {
        users.value = unwrap(await run(() => listUsers(params)));
    }

    async function saveUser(id, payload) {
        const result = id
            ? await run(() => updateUser(id, payload), 'Usuario actualizado.')
            : await run(() => createUser(payload), 'Usuario creado.');

        if (result) {
            await fetchUsers();
        }

        return Boolean(result);
    }

    async function removeUser(id) {
        const result = await run(() => deleteUser(id), 'Usuario eliminado.');

        if (result !== undefined) {
            users.value = users.value.filter((user) => user.id !== id);
        }
    }

    async function fetchRoles(params = {}) {
        roles.value = unwrap(await run(() => listRoles(params)));
    }

    async function saveRole(id, payload) {
        const result = id
            ? await run(() => updateRole(id, payload), 'Rol actualizado.')
            : await run(() => createRole(payload), 'Rol creado.');

        if (result) {
            await fetchRoles();
        }

        return Boolean(result);
    }

    async function removeRole(id) {
        const result = await run(() => deleteRole(id), 'Rol eliminado.');

        if (result !== undefined) {
            roles.value = roles.value.filter((role) => role.id !== id);
        }
    }

    async function fetchApiKeys(params = {}) {
        apiKeys.value = unwrap(await run(() => listApiKeys(params)));
    }

    async function createKey(payload) {
        const result = await run(() => createApiKey(payload), 'API key creada. Cópiala ahora: no se vuelve a mostrar.');

        if (!result) {
            return false;
        }

        revealedKey.value = result.plain_text_token ?? null;
        await fetchApiKeys();

        return true;
    }

    async function removeKey(id) {
        const result = await run(() => deleteApiKey(id), 'API key revocada.');

        if (result !== undefined) {
            apiKeys.value = apiKeys.value.filter((key) => key.id !== id);
        }
    }

    async function fetchSettings() {
        settings.value = (await run(fetchAdminSettings)) ?? {};
    }

    async function saveSettings(payload) {
        const result = await run(() => updateAdminSettings(payload), 'Configuración global guardada.');

        if (!result) {
            return false;
        }

        settings.value = result;

        return true;
    }

    async function fetchUsage(params = {}) {
        usage.value = (await run(() => fetchAdminUsage(params))) ?? null;
    }

    async function fetchKnowledge(params = {}) {
        knowledge.value = unwrap(await run(() => listAdminKnowledge(params)));
    }

    async function saveKnowledge(id, payload) {
        const result = id
            ? await run(() => updateAdminKnowledge(id, payload), 'Entrada actualizada.')
            : await run(() => createAdminKnowledge(payload), 'Entrada creada.');

        if (result) {
            await fetchKnowledge();
        }

        return Boolean(result);
    }

    async function removeKnowledge(id) {
        const result = await run(() => deleteAdminKnowledge(id), 'Entrada eliminada.');

        if (result !== undefined) {
            knowledge.value = knowledge.value.filter((entry) => entry.id !== id);
        }
    }

    return {
        loading,
        error,
        fieldErrors,
        users,
        roles,
        apiKeys,
        knowledge,
        settings,
        usage,
        revealedKey,
        fetchUsers,
        saveUser,
        removeUser,
        fetchRoles,
        saveRole,
        removeRole,
        fetchApiKeys,
        createKey,
        removeKey,
        fetchSettings,
        saveSettings,
        fetchUsage,
        fetchKnowledge,
        saveKnowledge,
        removeKnowledge,
    };
});
