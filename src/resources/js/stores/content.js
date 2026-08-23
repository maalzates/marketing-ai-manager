import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import {
    approveScript,
    createSchedule,
    createScript,
    deleteSchedule,
    fetchCalendar,
    generateScripts,
    listSchedules,
    listScripts,
    updateSchedule,
    updateScript,
} from '@/repositories/contentRepository';
import { useAsyncState } from '@/stores/useAsyncState';

export const useContentStore = defineStore('content', () => {
    const { loading, error, fieldErrors, run } = useAsyncState();

    const scripts = ref([]);
    const schedules = ref([]);
    const suggestedSlots = ref([]);

    const pendingScripts = computed(() => scripts.value.filter((script) => script.status !== 'approved'));

    async function fetchScripts(params = {}) {
        const result = await run(() => listScripts(params));

        scripts.value = result?.data ?? result ?? [];
    }

    async function fetchSchedules(params = {}) {
        const result = await run(() => listSchedules(params));

        schedules.value = result?.data ?? result ?? [];
    }

    async function fetchCalendarRange(params) {
        const result = await run(() => fetchCalendar(params));

        schedules.value = result?.schedules ?? [];
        suggestedSlots.value = result?.suggested_slots ?? [];
    }

    async function generate(payload) {
        return Boolean(await run(
            () => generateScripts(payload),
            'Generación encolada. Los guiones aparecerán aquí cuando el modelo termine.',
        ));
    }

    async function saveScript(id, payload) {
        const result = id
            ? await run(() => updateScript(id, payload), 'Guion actualizado.')
            : await run(() => createScript(payload), 'Guion creado.');

        if (result) {
            await fetchScripts();
        }

        return Boolean(result);
    }

    async function approve(id, payload) {
        const result = await run(() => approveScript(id, payload), 'Guion aprobado. Se creó su experimento orgánico.');

        if (result) {
            scripts.value = scripts.value.map((script) => (script.id === id ? result : script));
        }

        return Boolean(result);
    }

    // The caller refetches its range instead of appending: a new slot may well
    // fall outside the dates currently on screen.
    async function schedule(payload) {
        return Boolean(await run(() => createSchedule(payload), 'Pieza programada.'));
    }

    async function reschedule(id, payload, successMessage = 'Programación actualizada.') {
        const result = await run(() => updateSchedule(id, payload), successMessage);

        if (result) {
            schedules.value = schedules.value.map((item) => (item.id === id ? result : item));
        }

        return Boolean(result);
    }

    function markPublished(id, externalPostId) {
        return reschedule(
            id,
            { status: 'published', external_post_id: externalPostId },
            'Publicación manual registrada.',
        );
    }

    async function unschedule(id) {
        const result = await run(() => deleteSchedule(id), 'Programación eliminada.');

        if (result !== undefined) {
            schedules.value = schedules.value.filter((item) => item.id !== id);
        }
    }

    return {
        loading,
        error,
        fieldErrors,
        scripts,
        schedules,
        suggestedSlots,
        pendingScripts,
        fetchScripts,
        fetchSchedules,
        fetchCalendarRange,
        generate,
        saveScript,
        approve,
        schedule,
        reschedule,
        markPublished,
        unschedule,
    };
});
