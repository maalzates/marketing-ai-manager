<script setup>
import { onMounted, reactive, watch } from 'vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useAdminStore } from '@/stores/admin';

const admin = useAdminStore();

const form = reactive({
    rate_limit_per_minute: 60,
    rate_limit_per_key: 120,
    default_model: '',
    default_daily_token_limit: '',
    default_guardian_frequency: 'daily',
    anomaly_threshold: 3,
    retention_scraped_days: 90,
    retention_action_logs_days: 365,
    maintenance_mode: false,
    jobs_paused: false,
    feature_chat: true,
    feature_comment_mining: true,
});

onMounted(admin.fetchSettings);

watch(() => admin.settings, (values) => Object.assign(form, values ?? {}));
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Configuración global</h1>
            <p class="mt-1 text-sm text-muted">Valores por defecto, límites y banderas que aplican a todas las cuentas.</p>
        </header>

        <LoadingState v-if="admin.loading && !Object.keys(admin.settings).length" />
        <ErrorState v-else-if="admin.error && !Object.keys(admin.settings).length" :message="admin.error" @retry="admin.fetchSettings()" />

        <form v-else class="grid max-w-3xl gap-5 rounded-card border border-line bg-surface p-6 md:grid-cols-2" @submit.prevent="admin.saveSettings({ ...form })">
            <FormField label="Requests por minuto y usuario" :errors="admin.fieldErrors.rate_limit_per_minute ?? []">
                <input v-model="form.rate_limit_per_minute" type="number" min="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Requests por minuto y API key" :errors="admin.fieldErrors.rate_limit_per_key ?? []">
                <input v-model="form.rate_limit_per_key" type="number" min="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Modelo sugerido para cuentas nuevas">
                <input v-model="form.default_model" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Límite diario de tokens inicial">
                <input v-model="form.default_daily_token_limit" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Frecuencia inicial del guardián">
                <select v-model="form.default_guardian_frequency" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="daily">Diario</option>
                    <option value="every_3_days">Cada 3 días</option>
                    <option value="weekly">Semanal</option>
                </select>
            </FormField>
            <FormField label="Umbral de anomalía (veces peor que lo esperado)">
                <input v-model="form.anomaly_threshold" type="number" min="1" step="0.5" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Retención de datos scrapeados (días)">
                <input v-model="form.retention_scraped_days" type="number" min="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Retención del log de acciones (días)">
                <input v-model="form.retention_action_logs_days" type="number" min="1" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Chat habilitado">
                <input v-model="form.feature_chat" type="checkbox" class="size-4 rounded border-line">
            </FormField>
            <FormField label="Minería de comentarios habilitada">
                <input v-model="form.feature_comment_mining" type="checkbox" class="size-4 rounded border-line">
            </FormField>
            <FormField label="Modo mantenimiento">
                <input v-model="form.maintenance_mode" type="checkbox" class="size-4 rounded border-line">
            </FormField>
            <FormField label="Pausar todos los jobs" hint="Kill-switch de automatizaciones.">
                <input v-model="form.jobs_paused" type="checkbox" class="size-4 rounded border-line">
            </FormField>

            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 md:col-span-2 md:justify-self-start"
                :disabled="admin.loading"
            >
                Guardar configuración global
            </button>
        </form>
    </div>
</template>
