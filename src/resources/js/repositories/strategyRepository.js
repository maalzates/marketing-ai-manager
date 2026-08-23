import http from '@/repositories/client';

export async function listStrategies(params = {}) {
    return (await http.get('/strategies', { params })).data.result;
}

export async function showStrategy(id) {
    return (await http.get(`/strategies/${id}`)).data.result;
}

export async function createStrategy(payload) {
    return (await http.post('/strategies', payload)).data.result;
}

export async function updateStrategy(id, payload) {
    return (await http.put(`/strategies/${id}`, payload)).data.result;
}

export async function deleteStrategy(id) {
    return (await http.delete(`/strategies/${id}`)).data.result;
}
