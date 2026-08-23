<script setup>
import { onMounted, reactive, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import { useCompetitorsStore } from '@/stores/competitors';

const competitors = useCompetitorsStore();

const form = reactive({ handle: '', platform: 'instagram' });
const removingId = ref(null);

const columns = [
    { key: 'handle', label: 'Cuenta', sortable: true },
    { key: 'platform', label: 'Plataforma', sortable: true },
    { key: 'last_synced_at', label: 'Última sincronización', sortable: true },
    { key: 'posts_count', label: 'Posts analizados', sortable: true },
    { key: 'actions', label: '' },
];

onMounted(() => competitors.fetchAll());

async function add() {
    if (await competitors.create({ ...form })) {
        form.handle = '';
    }
}

async function confirmRemove() {
    await competitors.remove(removingId.value);
    removingId.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Competencia</h1>
            <p class="mt-1 text-sm text-muted">
                El scraping corre en segundo plano. Aquí siempre ves datos ya guardados, nunca una consulta en vivo.
            </p>
        </header>

        <form class="flex flex-wrap items-end gap-4 rounded-card border border-line bg-surface p-5" @submit.prevent="add">
            <FormField label="Cuenta a monitorear" :errors="competitors.fieldErrors.handle ?? []" required>
                <input v-model="form.handle" type="text" placeholder="@marca" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Plataforma">
                <select v-model="form.platform" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="instagram">Instagram</option>
                    <option value="youtube">YouTube</option>
                    <option value="meta_ads">Biblioteca de anuncios</option>
                </select>
            </FormField>
            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="competitors.loading || !form.handle"
            >
                Agregar
            </button>
        </form>

        <ErrorState v-if="competitors.error && !competitors.items.length" :message="competitors.error" @retry="competitors.fetchAll()" />
        <DataTable
            v-else
            :columns="columns"
            :rows="competitors.items"
            :loading="competitors.loading && !competitors.items.length"
            empty-title="Todavía no monitoreas a nadie"
            empty-description="Agrega las cuentas contra las que compites y el sistema empezará a acumular insights."
        >
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <button type="button" class="text-xs font-medium text-brand-600 hover:text-brand-700" @click="competitors.sync(row.id)">
                        Sincronizar
                    </button>
                    <button type="button" class="text-xs font-medium text-danger-700 hover:underline" @click="removingId = row.id">
                        Eliminar
                    </button>
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            :open="Boolean(removingId)"
            title="Dejar de monitorear"
            message="Se eliminan también los datos scrapeados asociados a esta cuenta."
            confirm-label="Eliminar"
            destructive
            @confirm="confirmRemove"
            @cancel="removingId = null"
        />
    </div>
</template>
