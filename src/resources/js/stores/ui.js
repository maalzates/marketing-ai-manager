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

    // The OAuth callback answers a browser navigation, so the only way to tell the user how it
    // went is the query it lands with. Both pages that can receive it say it the same way.
    function announceOauth({ status, integration, message }) {
        if (!status) {
            return false;
        }

        status === 'connected'
            ? push('success', `Conexión con ${integration} completada.`)
            : push('error', message || 'No se pudo completar la conexión.');

        return true;
    }

    return {
        toasts,
        sandbox,
        dismiss,
        announceOauth,
        success: (message) => push('success', message),
        error: (message) => push('error', message),
        info: (message) => push('info', message),
        setSandbox: (enabled) => { sandbox.value = Boolean(enabled); },
    };
});
