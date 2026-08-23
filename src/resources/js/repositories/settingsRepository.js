import http from '@/repositories/client';

export async function fetchSettings() {
    return (await http.get('/settings')).data.result;
}

export async function updateSettings(payload) {
    return (await http.put('/settings', payload)).data.result;
}

export async function fetchStrategySettings(strategyId) {
    return (await http.get(`/settings/strategies/${strategyId}`)).data.result;
}

export async function updateStrategySettings(strategyId, payload) {
    return (await http.put(`/settings/strategies/${strategyId}`, payload)).data.result;
}
