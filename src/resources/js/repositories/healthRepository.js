import http from '@/repositories/client';

export async function fetchHealth() {
    return (await http.get('/health')).data.result;
}
