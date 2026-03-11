import axios from 'axios';

// Attach token on every request
axios.interceptors.request.use((config) => {
    const token = localStorage.getItem('auth_token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

// Redirect to login on 401
axios.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            window.location.href = '/login';
        }
        return Promise.reject(err);
    }
);

const BASE = '/api/v1';

export function useApi() {
    const get      = (path, params = {}) => axios.get(`${BASE}${path}`, { params });
    const post     = (path, data = {})   => axios.post(`${BASE}${path}`, data);
    const postForm = (path, formData)    => axios.post(`${BASE}${path}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    const putForm  = (path, formData)    => axios.post(`${BASE}${path}`, formData, {
        // Laravel resource updates via POST + _method spoofing for multipart
        headers: { 'Content-Type': 'multipart/form-data' },
        params:  { _method: 'PUT' },
    });
    const put  = (path, data = {})   => axios.put(`${BASE}${path}`, data);
    const del  = (path)              => axios.delete(`${BASE}${path}`);
    return { get, post, postForm, putForm, put, del };
}
