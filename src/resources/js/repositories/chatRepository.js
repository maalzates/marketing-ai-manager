import http from '@/repositories/client';

export async function listConversations(params = {}) {
    return (await http.get('/chat/conversations', { params })).data.result;
}

export async function createConversation(payload = {}) {
    return (await http.post('/chat/conversations', payload)).data.result;
}

export async function showConversation(id) {
    return (await http.get(`/chat/conversations/${id}`)).data.result;
}

export async function sendMessage(payload) {
    return (await http.post('/chat', payload)).data.result;
}
