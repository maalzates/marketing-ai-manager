<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useAuthStore } from '@/stores/auth';
import { useIntegrationsStore } from '@/stores/integrations';
import { AI_TASKS, useSettingsStore } from '@/stores/settings';
import { useUiStore } from '@/stores/ui';

const route = useRoute();
const router = useRouter();
const ui = useUiStore();
const settings = useSettingsStore();
const integrations = useIntegrationsStore();
const auth = useAuthStore();

const tabs = [
    { key: 'integrations', label: 'Integraciones' },
    { key: 'models', label: 'Modelos' },
    { key: 'automations', label: 'Automatizaciones' },
    { key: 'budgets', label: 'Presupuestos' },
    { key: 'campaigns', label: 'Campañas' },
    { key: 'notifications', label: 'Notificaciones' },
    { key: 'preferences', label: 'Preferencias' },
];

// Every model the provider lists is selectable. No provider serves prices over the API, so a
// missing tariff is a gap in this deployment's table, not a reason to refuse the model: the
// call is recorded at cost zero and the label says so.
const MODEL_STATE_LABELS = {
    unpriced: 'sin tarifa · se registra a coste 0',
    retired: 'ya no lo lista el proveedor',
};

const STATUS_LABELS = {
    connected: 'Conectado',
    disconnected: 'Sin conectar',
    expired: 'Caducado',
    error: 'Con error',
};

const tab = ref('integrations');
const keyDrafts = reactive({});
const editingKey = reactive({});
const form = reactive({});

// Every zone and currency the browser knows, so the repository never carries a list that
// drifts from reality. Older engines without `supportedValuesOf` fall back to what is saved.
const timezones = computed(() => (
    typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : [form.timezone].filter(Boolean)
));
const currencies = computed(() => (
    typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('currency') : [form.currency].filter(Boolean)
));

// Only LLM providers carry a catalogue, and the same response says whether the account can
// reach them — so an unreachable model can be offered greyed out instead of hidden.
const modelProviders = computed(() => integrations.items
    .filter((integration) => (integration.models ?? []).length > 0)
    .map((integration) => ({
        provider: integration.provider,
        connected: integration.status === 'connected',
        models: integration.models,
        pricingUrl: integration.pricing_url,
    })));

function modelLabel(model) {
    const suffix = MODEL_STATE_LABELS[model.state] ?? price(model);

    return `${model.id} · ${suffix}`;
}

function selectable(group) {
    return group.connected;
}

const hasConnectedProvider = computed(() => modelProviders.value.some((one) => one.connected));

// Grouped by what each provider is for, in the order the backend declares: ungrouped, the
// eight rows read as eight equally required steps, and only one of the three models is needed.
const integrationGroups = computed(() => Object.values(
    integrations.items.reduce((groups, integration) => {
        const key = integration.group?.key ?? 'other';

        groups[key] ??= { ...integration.group, key, items: [] };
        groups[key].items.push(integration);

        return groups;
    }, {}),
).sort((one, other) => (one.position ?? 0) - (other.position ?? 0)));

onMounted(() => {
    settings.fetchAll();
    integrations.fetchAll();
    announceOauthResult();
});

watch(() => settings.values, (values) => Object.assign(form, values), { immediate: true });
watch(() => auth.account?.currency, (code) => { form.currency = code; }, { immediate: true });

// The OAuth callback redirects here with the outcome in the query: the provider sends the
// browser to the API, so this is the first place that can tell the user how it went.
function announceOauthResult() {
    if (ui.announceOauth(route.query)) {
        router.replace({ name: 'settings' });
    }
}

function price({ input, output }) {
    return `$${Number(input).toFixed(2)} entrada · $${Number(output).toFixed(2)} salida`;
}

function saveKey(provider) {
    integrations.save(provider, { api_key: keyDrafts[provider] }).then(() => {
        keyDrafts[provider] = '';
        editingKey[provider] = false;
    });
}

async function save() {
    if (form.currency !== auth.account?.currency && !await auth.saveCurrency(form.currency)) {
        return;
    }

    settings.save(form);
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

        <LoadingState v-if="settings.loading && !settings.loaded" />
        <ErrorState v-else-if="settings.error && !settings.loaded" :message="settings.error" @retry="settings.fetchAll()" />

        <template v-else>
            <section v-if="tab === 'integrations'" class="space-y-8">
                <section v-for="group in integrationGroups" :key="group.key" class="space-y-3">
                    <header>
                        <h2 class="text-sm font-semibold text-ink">{{ group.label }}</h2>
                        <p class="mt-1 max-w-3xl text-xs text-muted">{{ group.description }}</p>
                    </header>

                    <article
                        v-for="integration in group.items"
                        :key="integration.provider"
                        class="rounded-card border border-line bg-surface p-5"
                    >
                    <header class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold">{{ integration.label ?? integration.provider }}</h3>
                            <p class="max-w-xl text-xs text-muted">{{ integration.purpose }}</p>
                            <p class="flex flex-wrap items-center gap-2 text-xs">
                                <span
                                    class="rounded-full px-2 py-0.5"
                                    :class="integration.status === 'connected'
                                        ? 'bg-success-50 text-success-700'
                                        : 'bg-warning-50 text-warning-700'"
                                >
                                    {{ STATUS_LABELS[integration.status] ?? integration.status }}
                                </span>
                                <span v-if="integration.masked_key" class="font-mono text-muted">{{ integration.masked_key }}</span>
                                <span v-if="integration.expires_at" class="text-muted">caduca {{ new Date(integration.expires_at).toLocaleDateString('es') }}</span>
                                <span v-if="integration.last_error" class="text-danger-700">{{ integration.last_error }}</span>
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="integration.kind === 'oauth'"
                                type="button"
                                class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700"
                                @click="integrations.connectWithOauth(integration.provider)"
                            >
                                {{ integration.status === 'connected' ? 'Reconectar' : 'Conectar' }}
                            </button>
                            <button
                                v-else-if="integration.masked_key && !editingKey[integration.provider]"
                                type="button"
                                class="rounded-lg border border-line px-3 py-1.5 text-xs hover:bg-canvas"
                                @click="editingKey[integration.provider] = true"
                            >
                                Cambiar clave
                            </button>
                            <button
                                v-if="integration.status !== 'disconnected'"
                                type="button"
                                class="rounded-lg border border-line px-3 py-1.5 text-xs hover:bg-canvas"
                                @click="integrations.verify(integration.provider)"
                            >
                                Verificar
                            </button>
                            <button
                                v-if="integration.status !== 'disconnected'"
                                type="button"
                                class="rounded-lg border border-danger-200 px-3 py-1.5 text-xs text-danger-700 hover:bg-danger-50"
                                @click="integrations.disconnect(integration.provider)"
                            >
                                Desconectar
                            </button>
                        </div>
                    </header>

                    <!-- The form only exists when there is something to type: a stored key is
                         changed on request, never re-entered to prove it is still there. -->
                    <form
                        v-if="integration.kind === 'api_key' && (!integration.masked_key || editingKey[integration.provider])"
                        class="mt-4 flex flex-wrap items-end gap-3"
                        @submit.prevent="saveKey(integration.provider)"
                    >
                        <FormField label="API key" :errors="integrations.fieldErrors.api_key ?? []">
                            <input v-model="keyDrafts[integration.provider]" type="password" autocomplete="off" class="rounded-lg border border-line px-3 py-2 text-sm">
                        </FormField>
                        <button type="submit" class="rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                            Guardar y verificar
                        </button>
                        <button
                            v-if="editingKey[integration.provider]"
                            type="button"
                            class="px-2 py-2 text-sm text-muted hover:text-ink"
                            @click="editingKey[integration.provider] = false"
                        >
                            Cancelar
                        </button>
                        </form>
                    </article>
                </section>
            </section>

            <form v-else class="max-w-2xl space-y-5 rounded-card border border-line bg-surface p-6" @submit.prevent="save">
                <template v-if="tab === 'models'">
                    <p v-if="!hasConnectedProvider" class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700">
                        No hay ningún proveedor de modelos conectado. Conecta uno en Integraciones: los modelos de
                        proveedores sin credencial aparecen deshabilitados porque cualquier tarea que los use fallaría.
                    </p>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-canvas px-4 py-3">
                        <div class="space-y-1 text-xs text-muted">
                            <p>
                                La lista viene de cada proveedor. Los precios no: ninguno de los tres los expone por API,
                                así que un modelo sin tarifa se puede usar y se registra a coste 0.
                            </p>
                            <p class="flex flex-wrap items-center gap-3">
                                <span>Ver precios de tokens:</span>
                                <a
                                    v-for="group in modelProviders"
                                    :key="group.provider"
                                    :href="group.pricingUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="capitalize text-brand-600 underline hover:text-brand-700"
                                >{{ group.provider }}</a>
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs hover:bg-canvas disabled:opacity-50"
                            :disabled="integrations.loading"
                            @click="integrations.refreshModels()"
                        >
                            Actualizar modelos
                        </button>
                    </div>

                    <FormField label="Usar el mismo modelo para todas las tareas" hint="Con esto activo, manda el modelo de Chat: las otras ocho tareas lo usan.">
                        <input v-model="form.same_model_for_all" type="checkbox" class="size-4 rounded border-line">
                    </FormField>

                    <FormField
                        v-for="task in (form.same_model_for_all ? AI_TASKS.slice(0, 1) : AI_TASKS)"
                        :key="task.task"
                        :label="task.label"
                        :hint="task.capable ? 'Tarea de juicio: escribe, decide o argumenta.' : 'Tarea mecánica: clasifica o extrae de un texto que ya existe.'"
                        :errors="settings.fieldErrors[`ai.models.per_task.${task.task}`] ?? []"
                    >
                        <select v-model="form[`model_${task.task}`]" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <optgroup
                                v-for="group in modelProviders"
                                :key="group.provider"
                                :label="group.connected ? group.provider : `${group.provider} — sin conectar`"
                            >
                                <option
                                    v-for="model in group.models"
                                    :key="model.id"
                                    :value="model.id"
                                    :disabled="!selectable(group)"
                                >
                                    {{ modelLabel(model) }}
                                </option>
                            </optgroup>
                        </select>
                    </FormField>
                </template>

                <template v-else-if="tab === 'automations'">
                    <FormField label="Guardián activo">
                        <input v-model="form.guardian_enabled" type="checkbox" class="size-4 rounded border-line">
                    </FormField>
                    <FormField label="Cada cuántos días revisa el guardián" :errors="settings.fieldErrors['guardian.frequency_days'] ?? []">
                        <select v-model.number="form.guardian_frequency_days" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <option :value="1">Diario</option>
                            <option :value="3">Cada 3 días</option>
                            <option :value="7">Semanal</option>
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
                    <FormField label="Límite diario de tokens" :errors="settings.fieldErrors['ai.budget.daily_tokens'] ?? []">
                        <input v-model.number="form.daily_token_limit" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Límite mensual de tokens" :errors="settings.fieldErrors['ai.budget.monthly_tokens'] ?? []">
                        <input v-model.number="form.monthly_token_limit" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Avisar al alcanzar este porcentaje" :errors="settings.fieldErrors['ai.budget.alert_threshold_percent'] ?? []">
                        <input v-model.number="form.token_alert_threshold" type="number" min="1" max="100" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Límite diario de llamadas a Apify" hint="Es diario, no mensual: es el límite que el backend aplica." :errors="settings.fieldErrors['apify.budget.daily_calls'] ?? []">
                        <input v-model.number="form.daily_apify_calls" type="number" min="0" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                </template>

                <template v-else-if="tab === 'campaigns'">
                    <p class="rounded-lg bg-sandbox-50 px-4 py-3 text-sm text-sandbox-700">
                        Las campañas se lanzan contra la cuenta publicitaria de producción. Con una cuenta de pruebas
                        configurada, el gestor puede operar contra ella: las llamadas son reales, los anuncios no se
                        publican y no se gasta dinero.
                    </p>
                    <FormField label="Cuenta publicitaria de Meta" hint="Solo el número, sin el prefijo act_." :errors="settings.fieldErrors['campaigns.meta_ad_account_id'] ?? []">
                        <input v-model="form.meta_ad_account_id" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
                    </FormField>
                    <FormField label="Cuenta publicitaria de pruebas" :errors="settings.fieldErrors['campaigns.meta_sandbox_ad_account_id'] ?? []">
                        <input v-model="form.meta_sandbox_ad_account_id" type="text" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
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
                    <FormField label="Zona horaria" :errors="settings.fieldErrors['preferences.timezone'] ?? []">
                        <select v-model="form.timezone" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                        </select>
                    </FormField>
                    <FormField label="Moneda" :errors="auth.fieldErrors.currency ?? []">
                        <select v-model="form.currency" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
                        </select>
                        <p class="mt-1 text-xs text-muted">
                            Tiene que ser la misma que la de tu cuenta publicitaria de Meta, que se fija al crearla
                            y no se puede cambiar.
                            <a
                                href="https://business.facebook.com/settings/ad-accounts"
                                target="_blank"
                                rel="noopener"
                                class="text-brand-600 underline hover:text-brand-700"
                            >Verificarla en Meta</a>.
                        </p>
                    </FormField>
                    <FormField label="Idioma" :errors="settings.fieldErrors['preferences.locale'] ?? []">
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
