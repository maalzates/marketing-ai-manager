<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="rounded-card border border-line bg-surface px-6 py-16 text-center">
        <h1 class="text-xl font-semibold">Esta página no existe</h1>
        <p class="mt-2 text-sm text-muted">Puede que el enlace esté roto o que el recurso ya no esté disponible.</p>
        <RouterLink
            :to="{ name: 'dashboard' }"
            class="mt-6 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
        >
            Volver al dashboard
        </RouterLink>
    </div>
</template>
