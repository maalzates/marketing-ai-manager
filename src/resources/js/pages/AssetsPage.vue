<script setup>
import { onMounted, reactive } from 'vue';
import DataTable from '@/components/DataTable.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import { useAssetsStore } from '@/stores/assets';

const assets = useAssetsStore();

const form = reactive({ drive_file_id: '', type: 'video_vertical', aspect_ratio: '9:16' });

const columns = [
    { key: 'name', label: 'Pieza', sortable: true },
    { key: 'type', label: 'Tipo', sortable: true },
    { key: 'aspect_ratio', label: 'Formato' },
    { key: 'duration', label: 'Duración', sortable: true },
    { key: 'status', label: 'Estado', sortable: true },
    { key: 'actions', label: '' },
];

onMounted(() => assets.fetchAll());

async function register() {
    if (await assets.create({ ...form })) {
        form.drive_file_id = '';
    }
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Biblioteca de piezas</h1>
            <p class="mt-1 text-sm text-muted">
                Los archivos viven en tu Google Drive. Aquí la aplicación gobierna su estado y a qué experimento sirven.
            </p>
        </header>

        <form class="flex flex-wrap items-end gap-4 rounded-card border border-line bg-surface p-5" @submit.prevent="register">
            <FormField label="ID del archivo en Drive" :errors="assets.fieldErrors.drive_file_id ?? []" required>
                <input v-model="form.drive_file_id" type="text" class="rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Tipo">
                <select v-model="form.type" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="video_vertical">Video vertical</option>
                    <option value="reel">Reel</option>
                    <option value="foto">Foto</option>
                    <option value="carrusel">Carrusel</option>
                    <option value="story">Story</option>
                </select>
            </FormField>
            <FormField label="Aspect ratio">
                <input v-model="form.aspect_ratio" type="text" class="w-28 rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="assets.loading || !form.drive_file_id"
            >
                Registrar pieza
            </button>
        </form>

        <ErrorState v-if="assets.error && !assets.items.length" :message="assets.error" @retry="assets.fetchAll()" />
        <DataTable
            v-else
            :columns="columns"
            :rows="assets.items"
            :loading="assets.loading && !assets.items.length"
            empty-title="La biblioteca está vacía"
            empty-description="Una campaña solo puede aprobarse con piezas en estado «listo»."
        >
            <template #cell-actions="{ row }">
                <button type="button" class="text-xs font-medium text-danger-700 hover:underline" @click="assets.remove(row.id)">
                    Retirar
                </button>
            </template>
        </DataTable>
    </div>
</template>
