<script setup>
import { RouterView } from 'vue-router';
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-canvas px-4 text-ink">
        <div class="w-full max-w-md">
            <h1 class="text-center text-xl font-semibold">Marketing AI Manager</h1>
            <p class="mt-1 text-center text-sm text-muted">Tu equipo de marketing asistido por IA</p>
            <div class="mt-8 rounded-card border border-line bg-surface p-8">
                <RouterView />
            </div>
        </div>
    </div>
</template>
