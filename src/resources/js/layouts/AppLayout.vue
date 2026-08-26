<script setup>
import { computed } from 'vue';
import { RouterLink, RouterView } from 'vue-router';
import ConfigChecklist from '@/components/ConfigChecklist.vue';
import NavIcon from '@/components/NavIcon.vue';
import { useAuthStore } from '@/stores/auth';
import { useUiStore } from '@/stores/ui';

const auth = useAuthStore();
const ui = useUiStore();

const primary = [
    { name: 'dashboard', label: 'Dashboard', icon: 'dashboard' },
];

const work = [
    { name: 'strategies', label: 'Estrategias', icon: 'strategies' },
    { name: 'experiments', label: 'Experimentos', icon: 'experiments' },
    { name: 'proposals', label: 'Propuestas', icon: 'proposals' },
    { name: 'content-planner', label: 'Contenido', icon: 'content' },
    { name: 'competitors', label: 'Competencia', icon: 'competitors' },
    { name: 'assets', label: 'Biblioteca', icon: 'assets' },
    { name: 'chat', label: 'Chat', icon: 'chat' },
    { name: 'reports', label: 'Reportes', icon: 'reports' },
];

const account = computed(() => {
    const entries = [{ name: 'settings', label: 'Configuración', icon: 'settings' }];

    return auth.isAdmin
        ? [...entries, { name: 'admin-users', label: 'Admin', icon: 'admin' }]
        : entries;
});

const groups = computed(() => [
    { key: 'primary', title: null, links: primary },
    { key: 'work', title: 'Trabajo', links: work },
    { key: 'account', title: 'Cuenta', links: account.value },
]);
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
                <div class="flex items-center gap-2.5 border-b border-line px-4 py-4">
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-500 to-brand-700 text-[15px] font-bold text-white"
                        aria-hidden="true"
                    >
                        M
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[15px] font-semibold leading-tight">Mazze Flow</span>
                        <span class="block truncate text-[11px] leading-tight text-muted">Marketing AI Manager</span>
                    </span>
                </div>

                <nav class="flex flex-col gap-5 px-3 py-4 pb-6">
                    <div v-for="group in groups" :key="group.key" class="flex flex-col gap-0.5">
                        <p
                            v-if="group.title"
                            class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted/70"
                        >
                            {{ group.title }}
                        </p>

                        <RouterLink
                            v-for="link in group.links"
                            :key="link.name"
                            :to="{ name: link.name }"
                            class="group relative flex items-center gap-2.5 rounded-lg py-2 pl-3 pr-3 text-sm text-muted transition-colors hover:bg-canvas hover:text-ink"
                            active-class="is-active !bg-brand-50 font-medium !text-brand-700"
                        >
                            <span
                                class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-brand-500 opacity-0 transition-opacity group-[.is-active]:opacity-100"
                                aria-hidden="true"
                            />
                            <NavIcon :name="link.icon" class="opacity-70 transition-opacity group-hover:opacity-100 group-[.is-active]:opacity-100" />
                            {{ link.label }}
                        </RouterLink>
                    </div>
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
