import http from '@/repositories/client';

export async function listCompetitors(params = {}) {
    return (await http.get('/competitors', { params })).data.result;
}

export async function createCompetitor(payload) {
    return (await http.post('/competitors', payload)).data.result;
}

export async function deleteCompetitor(id) {
    return (await http.delete(`/competitors/${id}`)).data.result;
}

export async function syncCompetitor(id) {
    return (await http.post(`/competitors/${id}/sync`)).data.result;
}

export async function listInsights(params = {}) {
    return (await http.get('/insights', { params })).data.result;
}
