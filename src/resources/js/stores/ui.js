import { defineStore } from 'pinia';
import { ref } from 'vue';

const TOAST_TTL = 6000;

export const useUiStore = defineStore('ui', () => {
    const toasts = ref([]);
    const sandbox = ref(false);

    let nextId = 0;

    function dismiss(id) {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    function push(type, message) {
        const id = ++nextId;

        toasts.value = [...toasts.value, { id, type, message }];
        setTimeout(() => dismiss(id), TOAST_TTL);
    }

    return {
        toasts,
        sandbox,
        dismiss,
        success: (message) => push('success', message),
        error: (message) => push('error', message),
        info: (message) => push('info', message),
        setSandbox: (enabled) => { sandbox.value = Boolean(enabled); },
    };
});
