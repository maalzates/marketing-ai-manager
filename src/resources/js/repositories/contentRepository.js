import http from '@/repositories/client';

export async function listScripts(params = {}) {
    return (await http.get('/content/scripts', { params })).data.result;
}

export async function showScript(id) {
    return (await http.get(`/content/scripts/${id}`)).data.result;
}

export async function createScript(payload) {
    return (await http.post('/content/scripts', payload)).data.result;
}

export async function updateScript(id, payload) {
    return (await http.put(`/content/scripts/${id}`, payload)).data.result;
}

export async function approveScript(id, payload) {
    return (await http.post(`/content/scripts/${id}/approve`, payload)).data.result;
}

export async function generateScripts(payload) {
    return (await http.post('/content/scripts/generate', payload)).data.result;
}

export async function fetchCalendar(params = {}) {
    return (await http.get('/content/calendar', { params })).data.result;
}

export async function listSchedules(params = {}) {
    return (await http.get('/content/schedules', { params })).data.result;
}

export async function createSchedule(payload) {
    return (await http.post('/content/schedules', payload)).data.result;
}

export async function updateSchedule(id, payload) {
    return (await http.put(`/content/schedules/${id}`, payload)).data.result;
}

export async function deleteSchedule(id) {
    return (await http.delete(`/content/schedules/${id}`)).data.result;
}
