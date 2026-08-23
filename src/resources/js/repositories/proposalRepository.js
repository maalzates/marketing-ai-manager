import http from '@/repositories/client';

export async function listProposals(params = {}) {
    return (await http.get('/proposals', { params })).data.result;
}

export async function showProposal(id) {
    return (await http.get(`/proposals/${id}`)).data.result;
}

export async function acceptProposal(id) {
    return (await http.post(`/proposals/${id}/accept`)).data.result;
}

export async function rejectProposal(id, payload = {}) {
    return (await http.post(`/proposals/${id}/reject`, payload)).data.result;
}
