<script setup>
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="text-center">
        <h2 class="text-lg font-semibold text-ink">Inicia sesión</h2>
        <p class="mt-2 text-sm text-muted">
            Google es el único método de acceso. La aplicación no guarda contraseñas.
        </p>

        <button
            type="button"
            class="mt-6 w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            :disabled="auth.loading"
            @click="auth.login()"
        >
            {{ auth.loading ? 'Redirigiendo…' : 'Continuar con Google' }}
        </button>

        <p v-if="auth.error" class="mt-4 text-sm text-danger-700">{{ auth.error }}</p>
    </div>
</template>
