import http from '@/repositories/client';

export async function updateAccount(payload) {
    return (await http.put('/account', payload)).data.result;
}
