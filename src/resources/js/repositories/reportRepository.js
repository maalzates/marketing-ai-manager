import http from '@/repositories/client';

export async function listReports(params = {}) {
    return (await http.get('/reports', { params })).data.result;
}

export async function showReport(id) {
    return (await http.get(`/reports/${id}`)).data.result;
}
