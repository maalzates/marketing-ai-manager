<script setup>
import { onMounted, ref } from 'vue';
import { fetchHealth } from '@/repositories/healthRepository';

const health = ref(null);

onMounted(async () => {
    health.value = await fetchHealth();
});
</script>

<template>
    <section class="rounded-xl border border-slate-200 bg-white p-8">
        <h1 class="text-2xl font-semibold">Marketing AI Manager</h1>
        <p class="mt-2 text-slate-600">Laravel + Vue SPA base is up.</p>
        <p class="mt-4 text-sm text-slate-500">API health: {{ health?.status ?? 'checking…' }}</p>
    </section>
</template>
