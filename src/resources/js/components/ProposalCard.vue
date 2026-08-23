<script setup>
import { computed } from 'vue';
import { useProposalsStore } from '@/stores/proposals';

const props = defineProps({
    proposal: { type: Object, required: true },
});

const proposals = useProposalsStore();

const isPending = computed(() => props.proposal.status === 'pending');
const isDeciding = computed(() => proposals.decidingId === props.proposal.id);
const statusLabel = computed(() => ({
    pending: 'Pendiente de tu decisión',
    accepted: 'Aceptada',
    rejected: 'Descartada',
    expired: 'Vencida',
}[props.proposal.status] ?? props.proposal.status));
</script>

<template>
    <article class="rounded-card border border-line bg-surface p-5">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-ink">{{ proposal.title }}</h3>
                <p class="mt-1 text-xs uppercase tracking-wide text-muted">{{ proposal.type }} · {{ statusLabel }}</p>
            </div>
            <span v-if="proposal.source" class="rounded-full bg-canvas px-2.5 py-1 text-xs text-muted">
                {{ proposal.source }}
            </span>
        </header>

        <p v-if="proposal.rationale" class="mt-3 text-sm text-muted">{{ proposal.rationale }}</p>

        <dl v-if="proposal.summary" class="mt-4 grid gap-2 text-sm sm:grid-cols-2">
            <div v-for="(value, key) in proposal.summary" :key="key" class="rounded-lg bg-canvas px-3 py-2">
                <dt class="text-xs text-muted">{{ key }}</dt>
                <dd class="text-ink">{{ value }}</dd>
            </div>
        </dl>

        <p v-if="proposal.blocked_reason" class="mt-4 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700">
            {{ proposal.blocked_reason }}
        </p>

        <footer v-if="isPending" class="mt-5 flex gap-3">
            <button
                type="button"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
                :disabled="isDeciding || Boolean(proposal.blocked_reason)"
                @click="proposals.decide(proposal.id, 'accept')"
            >
                Aceptar
            </button>
            <button
                type="button"
                class="rounded-lg border border-line px-4 py-2 text-sm font-medium text-ink hover:bg-canvas disabled:opacity-50"
                :disabled="isDeciding"
                @click="proposals.decide(proposal.id, 'reject')"
            >
                Descartar
            </button>
        </footer>
    </article>
</template>
