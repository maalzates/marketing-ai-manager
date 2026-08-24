import http from '@/repositories/client';

export async function googleRedirectUrl() {
    return (await http.get('/auth/google/redirect')).data.result;
}

export async function me() {
    return (await http.get('/auth/me')).data.result;
}

export async function logout() {
    return (await http.post('/auth/logout')).data.result;
}
