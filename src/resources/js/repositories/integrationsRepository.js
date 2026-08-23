import http from '@/repositories/client';

export async function listIntegrations() {
    return (await http.get('/integrations')).data.result;
}

export async function saveIntegration(provider, payload) {
    return (await http.put(`/integrations/${provider}`, payload)).data.result;
}

export async function deleteIntegration(provider) {
    return (await http.delete(`/integrations/${provider}`)).data.result;
}

export async function verifyIntegration(provider) {
    return (await http.post(`/integrations/${provider}/verify`)).data.result;
}

export async function oauthRedirectUrl(provider) {
    return (await http.get(`/integrations/${provider}/oauth/redirect`)).data.result;
}
