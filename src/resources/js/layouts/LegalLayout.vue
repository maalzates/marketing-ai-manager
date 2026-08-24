<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>

<template>
    <div class="min-h-screen bg-canvas px-4 py-12 text-ink">
        <div class="mx-auto w-full max-w-3xl">
            <RouterLink to="/login" class="text-sm text-brand-600 hover:text-brand-700">
                ← Volver al acceso
            </RouterLink>
            <div class="mt-6 rounded-card border border-line bg-surface p-8">
                <RouterView />
            </div>
        </div>
    </div>
</template>
