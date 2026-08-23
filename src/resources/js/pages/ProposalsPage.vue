<script setup>
import { onMounted, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import ProposalCard from '@/components/ProposalCard.vue';
import { useProposalsStore } from '@/stores/proposals';

const proposals = useProposalsStore();
const status = ref('pending');

onMounted(() => proposals.fetchAll({ status: status.value }));

watch(status, (value) => proposals.fetchAll(value ? { status: value } : {}));
</script>

<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Propuestas</h1>
                <p class="mt-1 text-sm text-muted">
                    Nada se ejecuta solo. Cada cambio en tus campañas pasa por una decisión tuya registrada aquí.
                </p>
            </div>
            <label class="text-sm">
                <span class="mb-1 block text-xs text-muted">Estado</span>
                <select v-model="status" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                    <option value="pending">Pendientes</option>
                    <option value="accepted">Aceptadas</option>
                    <option value="rejected">Descartadas</option>
                    <option value="">Todas</option>
                </select>
            </label>
        </header>

        <LoadingState v-if="proposals.loading && !proposals.items.length" />
        <ErrorState v-else-if="proposals.error && !proposals.items.length" :message="proposals.error" @retry="proposals.fetchAll({ status })" />
        <EmptyState
            v-else-if="!proposals.items.length"
            title="Sin propuestas en este estado"
            description="El guardián solo habla cuando detecta algo. El silencio es buena señal."
        />
        <div v-else class="space-y-4">
            <ProposalCard v-for="proposal in proposals.items" :key="proposal.id" :proposal="proposal" />
        </div>
    </div>
</template>
