<script setup>
import { computed, onMounted, reactive } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useContentStore } from '@/stores/content';
import { useStrategiesStore } from '@/stores/strategies';

const content = useContentStore();
const strategies = useStrategiesStore();

const dayInMs = 86400000;
const isoDay = (offsetDays) => new Date(Date.now() + offsetDays * dayInMs).toISOString().slice(0, 10);

const range = reactive({ from: isoDay(0), to: isoDay(14), strategy_id: '', suggest: 3 });

const byDay = computed(() => content.schedules.reduce((days, schedule) => {
    const day = (schedule.publish_at ?? '').slice(0, 10) || 'Sin fecha';

    return { ...days, [day]: [...(days[day] ?? []), schedule] };
}, {}));

// The backend only ranks slots for a concrete strategy, so asking for them
// without one would always come back empty.
function load() {
    content.fetchCalendarRange({
        from: range.from,
        to: range.to,
        ...(range.strategy_id ? { strategy_id: range.strategy_id, suggest: range.suggest } : {}),
    });
}

onMounted(() => {
    strategies.fetchAll();
    load();
});
</script>

<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Calendario de publicación</h1>
                <p class="mt-1 text-sm text-muted">Cada pieza con su fecha y hora. Un job la publica automáticamente.</p>
            </div>
            <RouterLink :to="{ name: 'content-planner' }" class="rounded-lg border border-line px-4 py-2 text-sm hover:bg-canvas">
                Ver guiones
            </RouterLink>
        </header>

        <form class="flex flex-wrap items-end gap-4 rounded-card border border-line bg-surface p-5" @submit.prevent="load">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Desde</span>
                <input v-model="range.from" type="date" class="rounded-lg border border-line px-3 py-2 text-sm">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Hasta</span>
                <input v-model="range.to" type="date" class="rounded-lg border border-line px-3 py-2 text-sm">
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Estrategia</span>
                <select v-model="range.strategy_id" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    <option v-for="strategy in strategies.items" :key="strategy.id" :value="strategy.id">
                        {{ strategy.name }}
                    </option>
                </select>
            </label>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Horarios sugeridos</span>
                <input
                    v-model.number="range.suggest"
                    type="number"
                    min="0"
                    max="10"
                    class="w-24 rounded-lg border border-line px-3 py-2 text-sm disabled:bg-canvas"
                    :disabled="!range.strategy_id"
                >
            </label>
            <p v-if="!range.strategy_id" class="text-xs text-muted">
                Elige una estrategia para que la IA sugiera horarios: los ordena según su cadencia.
            </p>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                Actualizar
            </button>
        </form>

        <section v-if="content.suggestedSlots.length" class="rounded-card border border-brand-200 bg-brand-50 p-5">
            <h2 class="text-sm font-semibold text-brand-700">Horarios sugeridos por la IA</h2>
            <p class="mt-1 text-xs text-brand-700">
                Basados en el engagement observado, ya sin horas ocupadas ni pasadas. Son una propuesta: tú eliges cuándo publicar.
            </p>
            <ul class="mt-3 flex flex-wrap gap-2">
                <li v-for="slot in content.suggestedSlots" :key="slot" class="rounded-full bg-surface px-3 py-1.5 text-xs text-ink">
                    {{ slot.replace('T', ' ').slice(0, 16) }}
                </li>
            </ul>
        </section>

        <LoadingState v-if="content.loading && !content.schedules.length" />
        <ErrorState v-else-if="content.error && !content.schedules.length" :message="content.error" @retry="load" />
        <EmptyState
            v-else-if="!content.schedules.length"
            title="Nada programado en este rango"
            description="Aprueba un guion, vincula su pieza grabada y aparecerá aquí con su horario."
        />
        <div v-else class="space-y-5">
            <section v-for="(items, day) in byDay" :key="day" class="rounded-card border border-line bg-surface p-5">
                <h2 class="text-sm font-semibold">{{ day }}</h2>
                <ul class="mt-3 divide-y divide-line">
                    <li v-for="schedule in items" :key="schedule.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm font-medium">{{ schedule.title ?? schedule.script_title ?? 'Pieza sin título' }}</p>
                            <p class="text-xs text-muted">
                                {{ (schedule.publish_at ?? '').slice(11, 16) || '—' }} · {{ schedule.platform ?? 'instagram' }} ·
                                {{ schedule.status }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-medium text-danger-700 hover:underline"
                            @click="content.unschedule(schedule.id)"
                        >
                            Quitar del calendario
                        </button>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
