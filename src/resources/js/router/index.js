import { createRouter, createWebHistory } from 'vue-router';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import OnboardingLayout from '@/layouts/OnboardingLayout.vue';
import { useAuthStore } from '@/stores/auth';
import { useOnboardingStore } from '@/stores/onboarding';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: AuthLayout,
            meta: { public: true },
            children: [
                { path: 'login', name: 'login', component: () => import('@/pages/LoginPage.vue') },
                { path: 'auth/callback', name: 'auth-callback', component: () => import('@/pages/AuthCallbackPage.vue') },
            ],
        },
        {
            path: '/',
            component: OnboardingLayout,
            meta: { onboarding: true },
            children: [
                { path: 'onboarding', name: 'onboarding', component: () => import('@/pages/OnboardingPage.vue') },
            ],
        },
        {
            path: '/',
            component: AppLayout,
            children: [
                { path: '', redirect: { name: 'dashboard' } },
                { path: 'dashboard', name: 'dashboard', component: () => import('@/pages/DashboardPage.vue') },
                { path: 'brand', name: 'brand-profile', component: () => import('@/pages/BrandProfilePage.vue') },
                { path: 'strategies', name: 'strategies', component: () => import('@/pages/StrategiesPage.vue') },
                { path: 'strategies/:id', name: 'strategy-detail', component: () => import('@/pages/StrategyDetailPage.vue'), props: true },
                { path: 'experiments', name: 'experiments', component: () => import('@/pages/ExperimentsPage.vue') },
                { path: 'experiments/:id', name: 'experiment-detail', component: () => import('@/pages/ExperimentDetailPage.vue'), props: true },
                { path: 'proposals', name: 'proposals', component: () => import('@/pages/ProposalsPage.vue') },
                { path: 'competitors', name: 'competitors', component: () => import('@/pages/CompetitorsPage.vue') },
                { path: 'insights', name: 'insights', component: () => import('@/pages/InsightsPage.vue') },
                { path: 'content', name: 'content-planner', component: () => import('@/pages/ContentPlannerPage.vue') },
                { path: 'content/calendar', name: 'content-calendar', component: () => import('@/pages/ContentCalendarPage.vue') },
                { path: 'assets', name: 'assets', component: () => import('@/pages/AssetsPage.vue') },
                { path: 'chat', name: 'chat', component: () => import('@/pages/ChatPage.vue') },
                { path: 'reports', name: 'reports', component: () => import('@/pages/ReportsPage.vue') },
                { path: 'settings', name: 'settings', component: () => import('@/pages/SettingsPage.vue') },
                { path: 'glosario/:concept?', name: 'glossary', component: () => import('@/pages/GlossaryPage.vue'), props: true },
                { path: 'admin/users', name: 'admin-users', component: () => import('@/pages/AdminUsersPage.vue'), meta: { admin: true } },
                { path: 'admin/roles', name: 'admin-roles', component: () => import('@/pages/AdminRolesPage.vue'), meta: { admin: true } },
                { path: 'admin/api-keys', name: 'admin-api-keys', component: () => import('@/pages/AdminApiKeysPage.vue'), meta: { admin: true } },
                { path: 'admin/settings', name: 'admin-settings', component: () => import('@/pages/AdminSettingsPage.vue'), meta: { admin: true } },
                { path: 'admin/knowledge', name: 'admin-knowledge', component: () => import('@/pages/AdminKnowledgePage.vue'), meta: { admin: true } },
                { path: 'admin/usage', name: 'admin-usage', component: () => import('@/pages/AdminUsagePage.vue'), meta: { admin: true } },
                { path: ':pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFoundPage.vue') },
            ],
        },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.public) {
        return true;
    }

    if (!auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (!auth.user && !await auth.fetchMe()) {
        return { name: 'login' };
    }

    if (to.meta.admin && !auth.isAdmin) {
        return { name: 'dashboard' };
    }

    if (to.meta.onboarding) {
        return true;
    }

    const onboarding = useOnboardingStore();

    if (!onboarding.loaded) {
        await onboarding.fetch();
    }

    // The wizard is resumable: the user lands on the step they left, not at the start.
    return onboarding.mustResume
        ? { name: 'onboarding', query: { step: onboarding.resumeStep } }
        : true;
});

export default router;
