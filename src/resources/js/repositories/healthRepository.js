import axios from '@/bootstrap';

export async function fetchHealth() {
    return (await axios.get('/health')).data.result;
}
