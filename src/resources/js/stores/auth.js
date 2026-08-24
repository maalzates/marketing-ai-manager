import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { TOKEN_KEY } from '@/bootstrap';
import { googleRedirectUrl, logout as logoutRequest, me } from '@/repositories/authRepository';
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

    async function adoptToken(granted) {
        token.value = granted;
        localStorage.setItem(TOKEN_KEY, granted);

        // The redirect carries the token and nothing else: /me is what settles the user,
        // the account and the roles the router guard reads.
        return fetchMe();
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

        // A full navigation, not a route change: it drops every other store's cached data
        // with it, so nothing of this session survives for whoever signs in next. Same
        // thing bootstrap.js does when a token dies mid-session.
        window.location.assign('/login');
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
        adoptToken,
        fetchMe,
        logout,
        clear,
    };
});
