import http from '@/repositories/client';

export async function suggest(payload) {
    return (await http.post('/ai/suggest', payload)).data.result;
}
