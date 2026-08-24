<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const failed = ref(false);

onMounted(async () => {
    const { token } = route.query;

    if (!token || !await auth.adoptToken(token)) {
        failed.value = true;

        return;
    }

    router.replace({ name: 'dashboard' });
});
</script>

<template>
    <div class="text-center">
        <template v-if="!failed">
            <h2 class="text-lg font-semibold text-ink">Validando tu acceso…</h2>
            <p class="mt-2 text-sm text-muted">Un momento, estamos confirmando la respuesta de Google.</p>
        </template>
        <template v-else>
            <h2 class="text-lg font-semibold text-danger-700">No pudimos completar el acceso</h2>
            <p class="mt-2 text-sm text-muted">{{ auth.error ?? 'Google no devolvió un token válido.' }}</p>
            <RouterLink
                :to="{ name: 'login' }"
                class="mt-6 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
            >
                Volver a intentar
            </RouterLink>
        </template>
    </div>
</template>
