import http from '@/repositories/client';

export async function listAssets(params = {}) {
    return (await http.get('/assets', { params })).data.result;
}

export async function createAsset(payload) {
    return (await http.post('/assets', payload)).data.result;
}

export async function linkAssetToExperiment(id, experimentId) {
    return (await http.post(`/assets/${id}/link-experiment`, { experiment_id: experimentId })).data.result;
}

export async function deleteAsset(id) {
    return (await http.delete(`/assets/${id}`)).data.result;
}
