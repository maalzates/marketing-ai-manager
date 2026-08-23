import http from '@/repositories/client';

export async function fetchUsage(params = {}) {
    return (await http.get('/usage', { params })).data.result;
}

export async function listActionLogs(params = {}) {
    return (await http.get('/action-logs', { params })).data.result;
}
