import axios from 'axios';

const http = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export const TOKEN_KEY = 'access_token';

http.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

// The API always answers { result, errors }. Repositories unwrap `result`; this
// flattens the error side so every caller gets one predictable Error.
http.interceptors.response.use(
    (response) => response,
    (failure) => {
        const status = failure.response?.status ?? 0;
        const errors = failure.response?.data?.errors ?? {};

        if (status === 401) {
            localStorage.removeItem(TOKEN_KEY);

            if (window.location.pathname !== '/login') {
                window.location.assign('/login');
            }
        }

        return Promise.reject(Object.assign(
            new Error(errors.message ?? 'No pudimos completar la operación. Intenta de nuevo.'),
            { status, errors },
        ));
    },
);

export default http;
