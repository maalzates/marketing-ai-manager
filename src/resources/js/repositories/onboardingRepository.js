import http from '@/repositories/client';

export async function fetchOnboarding() {
    return (await http.get('/onboarding')).data.result;
}

export async function completeStep(step, payload) {
    return (await http.post(`/onboarding/steps/${step}/complete`, payload)).data.result;
}

export async function skipStep(step) {
    return (await http.post(`/onboarding/steps/${step}/skip`)).data.result;
}
