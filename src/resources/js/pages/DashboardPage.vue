<script setup>
import { onMounted } from 'vue';
import ConfigChecklist from '@/components/ConfigChecklist.vue';
import EmptyState from '@/components/EmptyState.vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import ProposalCard from '@/components/ProposalCard.vue';
import { useProposalsStore } from '@/stores/proposals';
import { useStrategiesStore } from '@/stores/strategies';

const strategies = useStrategiesStore();
const proposals = useProposalsStore();

function load() {
    strategies.fetchAll();
    proposals.fetchAll({ status: 'pending' });
}

onMounted(load);
</script>

<template>
    <div class="space-y-8">
        <header>
            <h1 class="text-xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-muted">Lo que necesita tu decisión hoy.</p>
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="lg:col-span-2">
                <h2 class="text-sm font-semibold">Propuestas pendientes</h2>

                <LoadingState v-if="proposals.loading" class="mt-3" />
                <ErrorState v-else-if="proposals.error" class="mt-3" :message="proposals.error" @retry="load" />
                <EmptyState
                    v-else-if="!proposals.pending.length"
                    class="mt-3"
                    title="Nada que decidir"
                    description="Cuando el guardián o el asistente detecten algo, la propuesta aparecerá aquí."
                />
                <div v-else class="mt-3 space-y-4">
                    <ProposalCard v-for="proposal in proposals.pending" :key="proposal.id" :proposal="proposal" />
                </div>
            </section>

            <aside class="space-y-6">
                <ConfigChecklist />

                <section class="rounded-card border border-line bg-surface p-5">
                    <h2 class="text-sm font-semibold">Estrategias activas</h2>
                    <ul v-if="strategies.active.length" class="mt-3 space-y-2 text-sm">
                        <li v-for="strategy in strategies.active" :key="strategy.id">
                            <RouterLink
                                :to="{ name: 'strategy-detail', params: { id: strategy.id } }"
                                class="text-brand-600 hover:text-brand-700"
                            >
                                {{ strategy.name }}
                            </RouterLink>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted">
                        Todavía no tienes estrategias activas.
                        <RouterLink :to="{ name: 'strategies' }" class="text-brand-600">Crear la primera</RouterLink>
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
