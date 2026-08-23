import http from '@/repositories/client';

export async function listKnowledge(type, params = {}) {
    return (await http.get(`/knowledge/${type}`, { params })).data.result;
}

export async function showKnowledge(type, key) {
    return (await http.get(`/knowledge/${type}/${key}`)).data.result;
}
