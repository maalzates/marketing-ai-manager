import axios from 'axios';

window.axios = axios;
window.axios.defaults.baseURL = '/api';
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

window.axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('access_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

// The API always answers { result, errors }. Repositories unwrap `result`; this
// interceptor flattens the error side so callers get one predictable Error.
window.axios.interceptors.response.use(
    (response) => response,
    (error) => Promise.reject(
        Object.assign(
            new Error(error.response?.data?.errors?.message ?? error.message),
            {
                status: error.response?.status ?? 0,
                errors: error.response?.data?.errors ?? {},
            },
        ),
    ),
);

export default window.axios;
