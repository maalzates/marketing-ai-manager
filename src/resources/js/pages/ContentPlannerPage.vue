<script setup>
import { onMounted, reactive, ref } from 'vue';
import AskAiButton from '@/components/AskAiButton.vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import FormField from '@/components/FormField.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useContentStore } from '@/stores/content';
import { useStrategiesStore } from '@/stores/strategies';

const content = useContentStore();
const strategies = useStrategiesStore();

const generating = ref(false);
const approvingId = ref(null);

const brief = reactive({ strategy_id: null, count: 3, brief: '' });

const approval = reactive({
    platform: 'instagram',
    hypothesis: '',
    metric: '',
    operator: '>=',
    value: '',
    starts_at: '',
    ends_at: '',
});

onMounted(async () => {
    content.fetchScripts();
    await strategies.fetchAll();
    brief.strategy_id = strategies.items[0]?.id ?? null;
});

async function generate() {
    if (await content.generate({ ...brief })) {
        generating.value = false;
        brief.brief = '';
    }
}

function startApproval(script) {
    approvingId.value = script.id;
    Object.assign(approval, {
        platform: script.platform ?? 'instagram',
        hypothesis: script.hypothesis ?? '',
        metric: '',
        operator: '>=',
        value: '',
        starts_at: '',
        ends_at: '',
    });
}

async function approve() {
    const approved = await content.approve(approvingId.value, {
        platform: approval.platform,
        hypothesis: approval.hypothesis,
        expected_result: {
            metric: approval.metric,
            operator: approval.operator,
            value: Number(approval.value),
        },
        starts_at: approval.starts_at,
        ends_at: approval.ends_at,
    });

    if (approved) {
        approvingId.value = null;
    }
}
</script>

<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Planificador de contenido</h1>
                <p class="mt-1 text-sm text-muted">
                    Guiones propuestos por la IA a partir de tus insights. Al aprobar uno se crea su experimento orgánico,
                    y por eso hace falta decir qué esperas que consiga.
                </p>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
                    @click="generating = !generating"
                >
                    {{ generating ? 'Cancelar' : 'Generar guiones' }}
                </button>
                <RouterLink :to="{ name: 'content-calendar' }" class="rounded-lg border border-line px-4 py-2 text-sm hover:bg-canvas">
                    Ver calendario
                </RouterLink>
            </div>
        </header>

        <form v-if="generating" class="grid max-w-3xl gap-4 rounded-card border border-line bg-surface p-5 md:grid-cols-2" @submit.prevent="generate">
            <FormField label="Estrategia" :errors="content.fieldErrors.strategy_id ?? []" required>
                <select v-model="brief.strategy_id" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option v-for="strategy in strategies.items" :key="strategy.id" :value="strategy.id">
                        {{ strategy.name }}
                    </option>
                </select>
            </FormField>
            <FormField label="Cuántos guiones" hint="Entre 1 y 10." :errors="content.fieldErrors.count ?? []">
                <input v-model.number="brief.count" type="number" min="1" max="10" class="w-full rounded-lg border border-line px-3 py-2 text-sm">
            </FormField>
            <FormField label="Brief opcional" :errors="content.fieldErrors.brief ?? []">
                <textarea v-model="brief.brief" rows="2" class="w-full rounded-lg border border-line px-3 py-2 text-sm" />
            </FormField>
            <button
                type="submit"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 md:col-span-2 md:justify-self-start"
                :disabled="content.loading || !brief.strategy_id"
            >
                Pedir guiones a la IA
            </button>
        </form>

        <LoadingState v-if="content.loading && !content.scripts.length" />
        <ErrorState v-else-if="content.error && !content.scripts.length" :message="content.error" @retry="content.fetchScripts()" />
        <EmptyState
            v-else-if="!content.scripts.length"
            title="Sin guiones propuestos"
            description="El planificador necesita insights de competencia y una estrategia activa para proponer contenido."
        />
        <div v-else class="space-y-4">
            <article v-for="script in content.scripts" :key="script.id" class="rounded-card border border-line bg-surface p-5">
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">{{ script.title }}</h2>
                        <p class="mt-1 text-xs uppercase tracking-wide text-muted">
                            {{ script.format ?? 'reel' }} · {{ script.status }}
                        </p>
                    </div>
                    <button
                        v-if="script.status !== 'approved'"
                        type="button"
                        class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"
                        @click="approvingId === script.id ? approvingId = null : startApproval(script)"
                    >
                        {{ approvingId === script.id ? 'Cancelar' : 'Aprobar guion' }}
                    </button>
                </header>

                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-medium text-muted">Hook</dt>
                        <dd class="text-ink">{{ script.hook ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted">Estructura</dt>
                        <dd class="whitespace-pre-line text-ink">{{ script.body ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-muted">CTA</dt>
                        <dd class="text-ink">{{ script.cta ?? '—' }}</dd>
                    </div>
                </dl>

                <form
                    v-if="approvingId === script.id"
                    class="mt-5 grid gap-4 rounded-card border border-brand-200 bg-brand-50 p-4 md:grid-cols-2"
                    @submit.prevent="approve"
                >
                    <p class="text-xs text-brand-700 md:col-span-2">
                        Al aprobar nace un experimento. Sin resultado esperado ni fechas no se puede medir, así que son obligatorios.
                    </p>

                    <FormField label="Plataforma" :errors="content.fieldErrors.platform ?? []" required>
                        <select v-model="approval.platform" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                            <option value="instagram">Instagram</option>
                            <option value="facebook">Facebook</option>
                            <option value="youtube">YouTube</option>
                            <option value="tiktok">TikTok</option>
                        </select>
                    </FormField>

                    <FormField label="Hipótesis" :errors="content.fieldErrors.hypothesis ?? []" required>
                        <textarea v-model="approval.hypothesis" rows="2" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm" />
                        <AskAiButton
                            class="mt-2"
                            field="experiment.hypothesis"
                            :context="{ script_title: script.title, hook: script.hook }"
                            @apply="approval.hypothesis = $event"
                        />
                    </FormField>

                    <FormField label="Métrica esperada" :errors="content.fieldErrors['expected_result.metric'] ?? []" required>
                        <input v-model="approval.metric" type="text" placeholder="engagement_rate" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    </FormField>

                    <div class="grid grid-cols-2 gap-3">
                        <FormField label="Operador" :errors="content.fieldErrors['expected_result.operator'] ?? []" required>
                            <select v-model="approval.operator" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                                <option value=">=">≥</option>
                                <option value=">">&gt;</option>
                                <option value="<=">≤</option>
                                <option value="<">&lt;</option>
                                <option value="=">=</option>
                            </select>
                        </FormField>
                        <FormField label="Valor" :errors="content.fieldErrors['expected_result.value'] ?? []" required>
                            <input v-model="approval.value" type="number" step="any" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                        </FormField>
                    </div>

                    <FormField label="Inicio" :errors="content.fieldErrors.starts_at ?? []" required>
                        <input v-model="approval.starts_at" type="date" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    </FormField>

                    <FormField label="Fin" hint="Posterior al inicio." :errors="content.fieldErrors.ends_at ?? []" required>
                        <input v-model="approval.ends_at" type="date" class="w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    </FormField>

                    <button
                        type="submit"
                        class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 md:col-span-2 md:justify-self-start"
                        :disabled="content.loading"
                    >
                        Aprobar y crear el experimento
                    </button>
                </form>
            </article>
        </div>
    </div>
</template>
