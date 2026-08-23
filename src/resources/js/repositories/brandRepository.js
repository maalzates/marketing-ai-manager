import http from '@/repositories/client';

export async function listBrandProfiles() {
    return (await http.get('/brand-profiles')).data.result;
}

export async function showBrandProfile(id) {
    return (await http.get(`/brand-profiles/${id}`)).data.result;
}

export async function createBrandProfile(payload) {
    return (await http.post('/brand-profiles', payload)).data.result;
}

export async function updateBrandProfile(id, payload) {
    return (await http.put(`/brand-profiles/${id}`, payload)).data.result;
}
