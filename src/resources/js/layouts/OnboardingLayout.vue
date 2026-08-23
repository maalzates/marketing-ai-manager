<script setup>
import { RouterView } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="min-h-screen bg-canvas text-ink">
        <header class="flex items-center justify-between border-b border-line bg-surface px-6 py-4">
            <span class="text-sm font-semibold">Configuración inicial</span>
            <button
                type="button"
                class="rounded-lg border border-line px-3 py-1.5 text-sm text-ink hover:bg-canvas"
                @click="auth.logout()"
            >
                Salir
            </button>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <RouterView />
        </main>
    </div>
</template>
