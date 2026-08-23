import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { TOKEN_KEY } from '@/bootstrap';
import { exchange, googleRedirectUrl, logout as logoutRequest, me } from '@/repositories/authRepository';
import { useAsyncState } from '@/stores/useAsyncState';
import { useUiStore } from '@/stores/ui';

export const useAuthStore = defineStore('auth', () => {
    const { loading, error, run } = useAsyncState();

    const token = ref(localStorage.getItem(TOKEN_KEY));
    const user = ref(null);
    const account = ref(null);
    const roles = ref([]);

    const isAuthenticated = computed(() => Boolean(token.value));
    const isAdmin = computed(() => roles.value.includes('admin'));

    function apply(payload) {
        user.value = payload?.user ?? null;
        account.value = payload?.account ?? null;
        roles.value = payload?.roles ?? [];
        useUiStore().setSandbox(payload?.account?.sandbox_mode);
    }

    function clear() {
        token.value = null;
        localStorage.removeItem(TOKEN_KEY);
        apply(null);
    }

    async function login() {
        const result = await run(googleRedirectUrl);

        if (result?.url) {
            window.location.assign(result.url);
        }
    }

    async function completeLogin(code, state) {
        const result = await run(() => exchange(code, state), 'Sesión iniciada.');

        if (!result?.access_token) {
            return false;
        }

        token.value = result.access_token;
        localStorage.setItem(TOKEN_KEY, result.access_token);
        apply(result);

        return true;
    }

    async function fetchMe() {
        const result = await run(me);

        if (!result) {
            clear();

            return false;
        }

        apply(result);

        return true;
    }

    async function logout() {
        await run(logoutRequest, 'Sesión cerrada.');
        clear();
    }

    return {
        loading,
        error,
        token,
        user,
        account,
        roles,
        isAuthenticated,
        isAdmin,
        login,
        completeLogin,
        fetchMe,
        logout,
        clear,
    };
});
