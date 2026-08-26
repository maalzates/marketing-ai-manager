import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    fetchSettings,
    fetchStrategySettings,
    updateSettings,
    updateStrategySettings,
} from '@/repositories/settingsRepository';
import { useAsyncState } from '@/stores/useAsyncState';
import { useUiStore } from '@/stores/ui';

// The nine AI tasks, in the order Settings → Models shows them. The suffix is the settings
// key, so a task added to the backend enum has to be added here too or it stays unreachable.
export const AI_TASKS = [
    { task: 'chat', label: 'Chat', capable: true },
    { task: 'content_script', label: 'Guiones de contenido', capable: true },
    { task: 'campaign_proposal', label: 'Propuestas de campaña', capable: true },
    { task: 'verdict', label: 'Veredictos de experimentos', capable: true },
    { task: 'guardian', label: 'Guardián', capable: true },
    { task: 'comment_sentiment', label: 'Sentimiento de comentarios', capable: false },
    { task: 'comment_mining', label: 'Minería de comentarios', capable: false },
    { task: 'insight_extraction', label: 'Extracción de insights', capable: false },
    { task: 'field_suggestion', label: 'Sugerencias de campo', capable: false },
];

/**
 * The form field names on the left, the settings keys the registry declares on the right.
 * This map is the whole reason the page can save: every field used to be sent under an
 * invented flat name that `config/settings.php` never declared, so the API rejected the
 * write before looking at a single value.
 */
export const SETTING_KEYS = {
    same_model_for_all: 'ai.models.same_for_all',
    daily_token_limit: 'ai.budget.daily_tokens',
    monthly_token_limit: 'ai.budget.monthly_tokens',
    token_alert_threshold: 'ai.budget.alert_threshold_percent',
    daily_apify_calls: 'apify.budget.daily_calls',
    guardian_enabled: 'guardian.enabled',
    guardian_frequency_days: 'guardian.frequency_days',
    reports_enabled: 'guardian.reports_enabled',
    auto_skip_idle_strategies: 'guardian.auto_skip_without_active_experiments',
    notify_proposals: 'notifications.proposals',
    notify_reports: 'notifications.reports',
    notify_token_expiry: 'notifications.token_expiry',
    notify_usage_limits: 'notifications.usage_limits',
    meta_ad_account_id: 'campaigns.meta_ad_account_id',
    meta_sandbox_ad_account_id: 'campaigns.meta_sandbox_ad_account_id',
    timezone: 'preferences.timezone',
    currency: 'preferences.currency',
    locale: 'preferences.locale',
    ...Object.fromEntries(AI_TASKS.map(({ task }) => [`model_${task}`, `ai.models.per_task.${task}`])),
};

// `GET /settings` answers `{ value, scope }` per key: the scope says which level of the
// cascade won, which the form does not need but a future "inherited from global" badge will.
const valueOf = (entry) => (entry !== null && typeof entry === 'object' && 'value' in entry ? entry.value : entry);

function toForm(effective) {
    return Object.fromEntries(
        Object.entries(SETTING_KEYS).map(([field, key]) => [field, valueOf(effective[key])]),
    );
}

function toValues(form) {
    return Object.fromEntries(
        Object.entries(SETTING_KEYS)
            .filter(([field]) => form[field] !== undefined && form[field] !== null && form[field] !== '')
            .map(([field, key]) => [key, form[field]]),
    );
}

export const useSettingsStore = defineStore('settings', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const effective = ref({});
    const strategyValues = ref({});

    const values = computed(() => toForm(effective.value));
    const loaded = computed(() => Object.keys(effective.value).length > 0);

    function apply(result) {
        effective.value = result ?? {};
        useUiStore().setSandbox(Boolean(valueOf(effective.value['campaigns.meta_sandbox_ad_account_id'])));
    }

    async function fetchAll() {
        apply(await run(fetchSettings));
    }

    async function save(form) {
        const result = await run(() => updateSettings({ values: toValues(form) }), 'Configuración guardada.');

        if (!result) {
            return false;
        }

        apply(result);

        return true;
    }

    async function fetchForStrategy(strategyId) {
        strategyValues.value = (await run(() => fetchStrategySettings(strategyId))) ?? {};
    }

    async function saveForStrategy(strategyId, form) {
        return Boolean(await run(
            () => updateStrategySettings(strategyId, { values: toValues(form) }),
            'Configuración de la estrategia guardada.',
        ));
    }

    return {
        loading,
        error,
        fieldErrors,
        effective,
        values,
        loaded,
        strategyValues,
        fetchAll,
        save,
        fetchForStrategy,
        saveForStrategy,
    };
});
