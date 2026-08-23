<script setup>
import { computed } from 'vue';
import { RouterLink, RouterView } from 'vue-router';
import ConfigChecklist from '@/components/ConfigChecklist.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const links = [
    { name: 'dashboard', label: 'Dashboard' },
    { name: 'strategies', label: 'Estrategias' },
    { name: 'experiments', label: 'Experimentos' },
    { name: 'proposals', label: 'Propuestas' },
    { name: 'content-planner', label: 'Contenido' },
    { name: 'competitors', label: 'Competencia' },
    { name: 'assets', label: 'Biblioteca' },
    { name: 'chat', label: 'Chat' },
    { name: 'reports', label: 'Reportes' },
    { name: 'settings', label: 'Configuración' },
];

const visibleLinks = computed(() => (
    auth.isAdmin ? [...links, { name: 'admin-users', label: 'Admin' }] : links
));
</script>

<template>
    <div class="min-h-screen bg-canvas text-ink">
        <div
            v-if="ui.sandbox"
            class="sticky top-0 z-30 flex items-center justify-center gap-2 bg-sandbox-500 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-white"
        >
            <span class="rounded bg-sandbox-700 px-2 py-0.5">Sandbox</span>
            Las campañas no se publican ni gastan dinero real
        </div>

        <div class="flex min-h-screen">
            <aside class="hidden w-60 shrink-0 border-r border-line bg-surface lg:block">
                <div class="px-5 py-5 text-sm font-semibold">Marketing AI Manager</div>
                <nav class="flex flex-col gap-1 px-3 pb-6">
                    <RouterLink
                        v-for="link in visibleLinks"
                        :key="link.name"
                        :to="{ name: link.name }"
                        class="rounded-lg px-3 py-2 text-sm text-muted hover:bg-canvas hover:text-ink"
                        active-class="bg-brand-50 text-brand-700"
                    >
                        {{ link.label }}
                    </RouterLink>
                </nav>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="flex items-center justify-between gap-4 border-b border-line bg-surface px-6 py-3">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium">{{ auth.account?.name ?? 'Sin cuenta' }}</span>
                        <span
                            v-if="ui.sandbox"
                            class="rounded-full bg-sandbox-50 px-2.5 py-1 text-xs font-semibold text-sandbox-700 ring-1 ring-sandbox-200"
                        >
                            SANDBOX
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <ConfigChecklist compact />
                        <span class="hidden text-sm text-muted sm:inline">{{ auth.user?.name }}</span>
                        <button
                            type="button"
                            class="rounded-lg border border-line px-3 py-1.5 text-sm text-ink hover:bg-canvas"
                            @click="auth.logout()"
                        >
                            Salir
                        </button>
                    </div>
                </header>

                <main class="flex-1 px-6 py-8">
                    <RouterView />
                </main>
            </div>
        </div>
    </div>
</template>
