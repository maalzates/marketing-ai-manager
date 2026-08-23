<script setup>
import { computed } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    experiment: { type: Object, default: null },
    action: { type: String, default: 'esta acción' },
});

defineEmits(['confirm', 'cancel']);

const learning = computed(() => props.experiment?.learning_phase ?? {});
const spend = computed(() => Number(props.experiment?.spend ?? 0));
const cap = computed(() => Number(props.experiment?.max_budget ?? 0));
const spendPercent = computed(() => (cap.value > 0 ? Math.min(Math.round((spend.value / cap.value) * 100), 100) : 0));
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center bg-ink/40 px-4">
        <div class="w-full max-w-lg rounded-card border border-warning-200 bg-surface p-6 shadow-lg">
            <h2 class="text-lg font-semibold text-ink">Este experimento está en fase de aprendizaje</h2>

            <p class="mt-2 text-sm text-muted">
                Vas a ejecutar {{ action }} sobre un experimento que Meta todavía está aprendiendo a entregar.
                Puedes continuar: solo queremos que decidas con la información completa.
            </p>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="rounded-lg bg-warning-50 px-3 py-2">
                    <dt class="text-xs font-medium text-warning-700">Ventana de aprendizaje</dt>
                    <dd class="text-ink">
                        Del {{ learning.started_at ?? '—' }} al {{ learning.ends_at ?? '—' }}
                        <span v-if="learning.events_needed">
                            · faltan ~{{ learning.events_needed }} eventos de optimización
                        </span>
                    </dd>
                </div>
                <div class="rounded-lg bg-canvas px-3 py-2">
                    <dt class="text-xs font-medium text-muted">Por qué la volatilidad de hoy es esperada</dt>
                    <dd class="text-ink">
                        Meta necesita unos 50 eventos en 7 días para estabilizar la entrega. Hasta entonces el CPA y el
                        CPM suben y bajan sin que eso signifique que el experimento sea malo. Editar presupuesto,
                        segmentación o creativo reinicia esta fase desde cero.
                    </dd>
                </div>
                <div class="rounded-lg bg-canvas px-3 py-2">
                    <dt class="text-xs font-medium text-muted">Gasto actual vs. tope protegido en backend</dt>
                    <dd class="text-ink">{{ spend }} de {{ cap }} ({{ spendPercent }}%)</dd>
                    <dd class="mt-2 h-2 w-full overflow-hidden rounded-full bg-line">
                        <span class="block h-full rounded-full bg-warning-500" :style="{ width: `${spendPercent}%` }" />
                    </dd>
                </div>
            </dl>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas"
                    @click="$emit('cancel')"
                >
                    Mejor espero
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-warning-700 px-4 py-2 text-sm font-medium text-white hover:bg-warning-500"
                    @click="$emit('confirm')"
                >
                    Entiendo, continuar
                </button>
            </div>
        </div>
    </div>
</template>
