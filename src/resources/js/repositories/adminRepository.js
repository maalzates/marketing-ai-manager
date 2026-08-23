import http from '@/repositories/client';

export async function listUsers(params = {}) {
    return (await http.get('/admin/users', { params })).data.result;
}

export async function showUser(id) {
    return (await http.get(`/admin/users/${id}`)).data.result;
}

export async function createUser(payload) {
    return (await http.post('/admin/users', payload)).data.result;
}

export async function updateUser(id, payload) {
    return (await http.put(`/admin/users/${id}`, payload)).data.result;
}

export async function deleteUser(id) {
    return (await http.delete(`/admin/users/${id}`)).data.result;
}

export async function listRoles(params = {}) {
    return (await http.get('/admin/roles', { params })).data.result;
}

export async function showRole(id) {
    return (await http.get(`/admin/roles/${id}`)).data.result;
}

export async function createRole(payload) {
    return (await http.post('/admin/roles', payload)).data.result;
}

export async function updateRole(id, payload) {
    return (await http.put(`/admin/roles/${id}`, payload)).data.result;
}

export async function deleteRole(id) {
    return (await http.delete(`/admin/roles/${id}`)).data.result;
}

export async function listApiKeys(params = {}) {
    return (await http.get('/admin/api-keys', { params })).data.result;
}

export async function createApiKey(payload) {
    return (await http.post('/admin/api-keys', payload)).data.result;
}

export async function deleteApiKey(id) {
    return (await http.delete(`/admin/api-keys/${id}`)).data.result;
}

export async function fetchAdminSettings() {
    return (await http.get('/admin/settings')).data.result;
}

export async function updateAdminSettings(payload) {
    return (await http.put('/admin/settings', payload)).data.result;
}

export async function fetchAdminUsage(params = {}) {
    return (await http.get('/admin/usage', { params })).data.result;
}

export async function listAdminActionLogs(params = {}) {
    return (await http.get('/admin/action-logs', { params })).data.result;
}

export async function listAdminKnowledge(params = {}) {
    return (await http.get('/admin/knowledge', { params })).data.result;
}

export async function createAdminKnowledge(payload) {
    return (await http.post('/admin/knowledge', payload)).data.result;
}

export async function updateAdminKnowledge(id, payload) {
    return (await http.put(`/admin/knowledge/${id}`, payload)).data.result;
}

export async function deleteAdminKnowledge(id) {
    return (await http.delete(`/admin/knowledge/${id}`)).data.result;
}
