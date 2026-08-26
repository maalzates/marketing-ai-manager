<script setup>
import { computed, onMounted, ref } from 'vue';
import ErrorState from '@/components/ErrorState.vue';
import LoadingState from '@/components/LoadingState.vue';
import { useAssetsStore } from '@/stores/assets';
import { useStrategiesStore } from '@/stores/strategies';

const assets = useAssetsStore();
const strategies = useStrategiesStore();

const strategyId = ref(null);
const dragging = ref(false);
const fileInput = ref(null);

// The groups the library organises itself into, in the order they matter for publishing.
const GROUPS = [
    { type: 'reel', label: 'Reels', hint: 'Vídeo vertical, el formato que Instagram empuja.' },
    { type: 'video_vertical', label: 'Vídeos', hint: 'Todo lo demás en vídeo.' },
    { type: 'photo', label: 'Fotos', hint: 'Imágenes sueltas para feed o anuncios.' },
    { type: 'story', label: 'Stories', hint: 'Piezas de 24 horas.' },
    { type: 'carousel', label: 'Carruseles', hint: 'Varias piezas que se publican juntas.' },
    { type: 'carousel_slide', label: 'Diapositivas', hint: 'Las piezas de dentro de un carrusel.' },
];

const STATUS_LABELS = {
    uploading: 'Subiendo',
    ready: 'Lista',
    draft: 'Borrador',
    failed: 'Falló',
};

const groups = computed(() => GROUPS
    .map((group) => ({ ...group, items: assets.items.filter((item) => item.type === group.type) }))
    .filter((group) => group.items.length > 0));

const uncategorised = computed(() => assets.items.filter(
    (item) => !GROUPS.some((group) => group.type === item.type),
));

onMounted(async () => {
    await Promise.all([assets.fetchAll(), strategies.fetchAll()]);

    strategyId.value = strategies.items.find((one) => one.status === 'active')?.id
        ?? strategies.items[0]?.id
        ?? null;
});

/**
 * The type has to be right at upload time — there is no endpoint that changes it afterwards.
 * A video taller than it is wide is a reel; everything else in video is a plain vertical video;
 * an image is a photo. Wrong guesses are cheap to fix: delete and drop it again.
 */
async function inferType(file) {
    if (file.type.startsWith('image/')) {
        return 'photo';
    }

    return await isPortrait(file) ? 'reel' : 'video_vertical';
}

function isPortrait(file) {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);

        video.onloadedmetadata = () => {
            URL.revokeObjectURL(url);
            resolve(video.videoHeight > video.videoWidth);
        };
        video.onerror = () => {
            URL.revokeObjectURL(url);
            resolve(true);
        };

        video.src = url;
    });
}

async function accept(files) {
    dragging.value = false;

    for (const file of Array.from(files)) {
        await assets.upload(file, { type: await inferType(file), strategyId: strategyId.value });
    }
}

function onDrop(event) {
    accept(event.dataTransfer?.files ?? []);
}

function browse(event) {
    accept(event.target.files ?? []);
    event.target.value = '';
}

function size(bytes) {
    return bytes ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : '—';
}
</script>

<template>
    <div class="space-y-6">
        <header>
            <h1 class="text-xl font-semibold">Biblioteca de piezas</h1>
            <p class="mt-1 text-sm text-muted">
                Arrastra los archivos aquí y se suben a tu propio Google Drive. La aplicación gobierna su estado y a qué
                experimento sirven; los ficheros siguen siendo tuyos.
            </p>
        </header>

        <div class="flex flex-wrap items-center gap-3">
            <label class="text-xs text-muted" for="strategy">Estrategia</label>
            <select id="strategy" v-model="strategyId" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
                <option v-for="one in strategies.items" :key="one.id" :value="one.id">{{ one.name }}</option>
            </select>
        </div>

        <!-- The whole panel is the drop target, so there is nothing to aim at. -->
        <div
            class="rounded-card border-2 border-dashed p-10 text-center transition"
            :class="dragging ? 'border-brand-600 bg-brand-50' : 'border-line bg-surface'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <p class="text-sm font-medium text-ink">Arrastra tus vídeos y fotos aquí</p>
            <p class="mt-1 text-xs text-muted">
                Se ordenan solas por tipo. Un vídeo más alto que ancho se guarda como reel.
            </p>
            <button
                type="button"
                class="mt-4 rounded-lg border border-line bg-surface px-4 py-2 text-sm hover:bg-canvas disabled:opacity-50"
                :disabled="!strategyId || assets.loading"
                @click="fileInput.click()"
            >
                O elige archivos
            </button>
            <input ref="fileInput" type="file" multiple accept="image/*,video/*" class="hidden" @change="browse">
            <p v-if="!strategyId" class="mt-3 text-xs text-warning-700">
                Necesitas una estrategia antes de subir: una pieza pertenece a la estrategia que la va a usar.
            </p>
        </div>

        <LoadingState v-if="assets.loading && !assets.items.length" />
        <ErrorState v-else-if="assets.error && !assets.items.length" :message="assets.error" @retry="assets.fetchAll()" />

        <p v-else-if="!assets.items.length" class="rounded-card border border-line bg-surface p-8 text-center text-sm text-muted">
            Todavía no hay piezas. Lo que arrastres arriba aparecerá aquí, agrupado por tipo.
        </p>

        <section v-for="group in [...groups, ...(uncategorised.length ? [{ type: 'other', label: 'Otras', hint: 'Tipos que esta pantalla no agrupa todavía.', items: uncategorised }] : [])]" :key="group.type" class="space-y-3">
            <header class="flex items-baseline gap-3">
                <h2 class="text-sm font-semibold text-ink">{{ group.label }}</h2>
                <span class="text-xs text-muted">{{ group.items.length }} · {{ group.hint }}</span>
            </header>

            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li
                    v-for="asset in group.items"
                    :key="asset.id"
                    class="flex items-start justify-between gap-3 rounded-card border border-line bg-surface p-4"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="truncate text-sm font-medium text-ink" :title="asset.name">{{ asset.name }}</p>
                        <p class="flex flex-wrap items-center gap-2 text-xs text-muted">
                            <span
                                class="rounded-full px-2 py-0.5"
                                :class="asset.status === 'ready' ? 'bg-success-50 text-success-700' : 'bg-canvas'"
                            >{{ STATUS_LABELS[asset.status] ?? asset.status }}</span>
                            <span v-if="asset.duration_seconds">{{ asset.duration_seconds }}s</span>
                            <span>{{ size(asset.size_bytes) }}</span>
                        </p>
                        <p v-if="asset.spec_warnings?.length" class="text-xs text-warning-700">
                            {{ asset.spec_warnings.join(' · ') }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-lg border border-danger-200 px-2 py-1 text-xs text-danger-700 hover:bg-danger-50"
                        @click="assets.remove(asset.id)"
                    >
                        Eliminar
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
