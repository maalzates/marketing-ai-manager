<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useIntegrationsStore } from '@/stores/integrations';
import { useSettingsStore } from '@/stores/settings';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();
const settings = useSettingsStore();
const integrations = useIntegrationsStore();

const tabs = [
    { key: 'integrations', label: 'Integraciones' },
    { key: 'models', label: 'Modelos' },
    { key: 'automations', label: 'Automatizaciones' },
    { key: 'budgets', label: 'Presupuestos' },
    { key: 'sandbox', label: 'Sandbox' },
    { key: 'notifications', label: 'Notificaciones' },
    { key: 'preferences', label: 'Preferencias' },
];

const tab = ref('integrations');
const credentials = reactive({});

const form = reactive({
    same_model_for_all: true,
    default_model: '',
    models: {},
    guardian_frequency: 'daily',
    guardian_enabled: true,
    reports_enabled: true,
    auto_skip_idle_strategies: true,
    daily_token_limit: '',
    monthly_token_limit: '',
    monthly_apify_limit: '',
    sandbox_mode: false,
    notify_proposals: true,
    notify_reports: true,
    notify_token_expiry: true,
    notify_usage_limits: true,
    timezone: 'UTC',
    currency: 'USD',
    locale: 'es',
});

onMounted(() => {
    settings.fetchAll();
    integrations.fetchAll();
    announceOauthResult();
});

// The OAuth callback redirects here with the outcome in the query, because the provider
// sends the browser to the API and only the SPA can tell the user how it went.
function announceOauthResult() {
    const { status, integration, message } = route.query;

    if (!status) {
        return;
    }

    status === 'connected'
        ? ui.success(`Conexión con ${integration} completada.`)
        : ui.error(message || 'No se pudo completar la conexión.');

    router.replace({ name: 'settings' });
}

watch(() => settings.values, (values) => Object.assign(form, values ?? {}));

function save() {
    settings.save({ ...form });
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Configuración</h1>
            <p class="mt-1 text-sm text-muted">Todo el comportamiento de la aplicación se ajusta desde aquí, sin tocar código.</p>
        </header>

        <nav class="flex flex-wrap gap-2 border-b border-line">
            <button
                v-for="option in tabs"
                :key="option.key"
                type="button"
                class="border-b-2 px-3 py-2 text-sm"
                :class="tab === option.key ? 'border-brand-600 font-medium text-brand-700' : 'border-transparent text-muted hover:text-ink'"
                @click="tab = option.key"
            >
                {{ option.label }}
            </button>
        </nav>

        <LoadingState v-if="settings.loading && !Object.keys(settings.values).length" />
        <ErrorState v-else-if="settings.error && !Object.keys(settings.values).length" :message="settings.error" @retry="settings.fetchAll()" />

        <template v-else>
            <section v-if="tab === 'integrations'" class="space-y-4">
                <article
                    v-for="integration in integrations.items"
                    :key="integration.provider"
                    class="rounded-card border border-line bg-surface p-5"
                >
                    <header class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold capitalize">{{ integration.provider }}</h2>
                            <p class="text-xs text-muted">
                                {{ integration.status }}
                                <span v-if="integration.masked_key"> · {{ integration.masked_key }}</span>
                                <span v-if="integration.last_error"> · {{ integration.last_error }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs hover:bg-canvas" @click="integrations.verify(integration.provider)">
                                Verificar
                            </button>
                            <button type="button" class="rounded-lg border border-danger-200 px-3 py-1.5 text-xs text-danger-700 hover:bg-danger-50" @click="integrations.disconnect(integration.provider)">
                                Desconectar
                            </button>
                        </div>
                    </header>

                    <div v-if="integration.kind === 'oauth'" class="mt-4">
                        <button type="button" class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700" @click="integrations.connectWithOauth(integration.provider)">
                            {{ integration.status === 'connected' ? 'Reconectar' : 'Conectar' }}
                        </button>
                    </div>
                    <form v-else class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="integrations.save(integration.provider, { credential: credentials[integration.provider] })">
                        <FormField label="API key" :errors="integrations.fieldErrors.credential ?? []">
                            <input v-model="credentials[integration.provider]" type="password" autocomplete="off" class="rounded-lg border border-line px-3 py-2 text-sm">
                        </FormField>
                        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                            Guardar y verificar
                        </button>
                    </form>
                </article>
            </section>

            <form v-else class="max-w-2xl space-y-5 rounded-card border border-line bg-surface p-6" @submit.prevent="save">
                <template v-if="tab === 'models'">
                    <FormField label="Usar el mismo modelo para todas las tareas">
                        <input v-model="form.same_model_for_all" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Modelo por defecto" :errors="settings.fieldErrors.default_model ?? []">
                        <input v-model="form.default_model" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                </template>

                <template v-else-if="tab === 'automations'">
                    <FormField label="Guardián activo">
                        <input v-model="form.guardian_enabled" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Frecuencia del guardián">
                        <select v-model="form.guardian_frequency" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <option value="daily">Diario</option>
                            <option value="every_3_days">Cada 3 días</option>
                            <option value="weekly">Semanal</option>
                        </select>
                    </FormField>
                    <FormField label="Reportes periódicos activos">
                        <input v-model="form.reports_enabled" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Saltar estrategias sin experimentos activos" hint="Evita llamadas al LLM y a las APIs cuando no hay nada corriendo.">
                        <input v-model="form.auto_skip_idle_strategies" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                </template>

                <template v-else-if="tab === 'budgets'">
                    <FormField label="Límite diario de tokens" :errors="settings.fieldErrors.daily_token_limit ?? []">
                        <input v-model="form.daily_token_limit" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Límite mensual de tokens" :errors="settings.fieldErrors.monthly_token_limit ?? []">
                        <input v-model="form.monthly_token_limit" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Límite mensual de llamadas a Apify" :errors="settings.fieldErrors.monthly_apify_limit ?? []">
                        <input v-model="form.monthly_apify_limit" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                </template>

                <template v-else-if="tab === 'sandbox'">
                    <p class="rounded-lg bg-sandbox-50 px-4 py-3 text-sm text-sandbox-700">
                        Con el modo sandbox activo, el gestor de campañas opera contra la cuenta publicitaria de pruebas de
                        Meta: las llamadas son reales, los anuncios no se publican y no se gasta dinero. Necesitas tener una
                        sandbox ad account vinculada.
                    </p>
                    <FormField label="Modo sandbox">
                        <input v-model="form.sandbox_mode" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                </template>

                <template v-else-if="tab === 'notifications'">
                    <FormField label="Propuestas del guardián">
                        <input v-model="form.notify_proposals" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Reportes de cierre">
                        <input v-model="form.notify_reports" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Expiración de tokens">
                        <input v-model="form.notify_token_expiry" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Límites de consumo">
                        <input v-model="form.notify_usage_limits" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                </template>

                <template v-else>
                    <FormField label="Zona horaria" :errors="settings.fieldErrors.timezone ?? []">
                        <input v-model="form.timezone" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Moneda" :errors="settings.fieldErrors.currency ?? []">
                        <input v-model="form.currency" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Idioma" :errors="settings.fieldErrors.locale ?? []">
                        <select v-model="form.locale" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <option value="es">Español</option>
                            <option value="en">Inglés</option>
                        </select>
                    </FormField>
                </template>

                <button
                    type="submit"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                    :disabled="settings.loading"
                >
                    Guardar cambios
                </button>
            </form>
        </template>
    </div>
</template>
