<script setup>
import { RouterLink } from 'vue-router';
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
            class="mt-6 flex w-full items-center justify-center gap-3 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50"
            :disabled="auth.loading"
            @click="auth.login()"
        >
            <!-- Google forbids recolouring its mark, so it sits on its own white tile
                 instead of being tinted to match the button. -->
            <span class="flex h-5 w-5 items-center justify-center rounded-sm bg-white">
                <svg class="h-3.5 w-3.5" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.28-3.14.76-4.59l-7.97-6.19C.92 16.46 0 20.12 0 24s.92 7.54 2.56 10.78l7.97-6.19z" />
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.97 6.19C6.51 42.62 14.62 48 24 48z" />
                </svg>
            </span>
            {{ auth.loading ? 'Redirigiendo…' : 'Continuar con Google' }}
        </button>

        <p class="mt-4 text-xs text-muted">
            Al ingresar aceptas nuestros
            <RouterLink :to="{ name: 'terms' }" class="text-brand-600 hover:text-brand-700">términos y condiciones</RouterLink>,
            así como nuestras
            <RouterLink :to="{ name: 'privacy' }" class="text-brand-600 hover:text-brand-700">políticas de privacidad</RouterLink>.
        </p>

        <p v-if="auth.error" class="mt-4 text-sm text-danger-700">{{ auth.error }}</p>
    </div>
</template>
