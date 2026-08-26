import http from '@/repositories/client';

export async function listAssets(params = {}) {
    return (await http.get('/assets', { params })).data.result;
}

// Multipart, because the file itself travels: the backend is what puts it in Drive. Axios sets
// the boundary from the FormData, so no Content-Type is passed by hand.
export async function uploadAsset(formData) {
    return (await http.post('/assets', formData)).data.result;
}

export async function linkAssetToExperiment(id, experimentId) {
    return (await http.post(`/assets/${id}/link-experiment`, { experiment_id: experimentId })).data.result;
}

export async function deleteAsset(id) {
    return (await http.delete(`/assets/${id}`)).data.result;
}
