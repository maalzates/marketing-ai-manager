import http from '@/repositories/client';

export async function listStrategyExperiments(strategyId, params = {}) {
    return (await http.get(`/strategies/${strategyId}/experiments`, { params })).data.result;
}

export async function createStrategyExperiment(strategyId, payload) {
    return (await http.post(`/strategies/${strategyId}/experiments`, payload)).data.result;
}

export async function showExperiment(id) {
    return (await http.get(`/experiments/${id}`)).data.result;
}

export async function updateExperiment(id, payload) {
    return (await http.put(`/experiments/${id}`, payload)).data.result;
}

export async function fetchExperimentMetrics(id, params = {}) {
    return (await http.get(`/experiments/${id}/metrics`, { params })).data.result;
}

export async function submitVerdict(id, payload) {
    return (await http.post(`/experiments/${id}/verdict`, payload)).data.result;
}

export async function syncCampaign(experimentId) {
    return (await http.post(`/campaigns/${experimentId}/sync`)).data.result;
}
