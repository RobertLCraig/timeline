const API_BASE = import.meta.env.VITE_API_URL || '/api';

function getCsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

class ApiClient {
    constructor() {
        this.baseUrl = API_BASE;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'Accept': 'application/json',
            ...options.headers,
        };

        // Include CSRF token for all state-changing requests
        const method = (options.method ?? 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            const csrf = getCsrfToken();
            if (csrf) headers['X-XSRF-TOKEN'] = csrf;
        }

        // Don't set Content-Type for FormData (browser sets boundary automatically)
        if (!(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, {
            ...options,
            credentials: 'include',
            headers,
        });

        if (response.status === 401) {
            window.dispatchEvent(new Event('auth:logout'));
            throw new Error('Session expired');
        }

        const data = await response.json();

        if (!response.ok) {
            const error = new Error(data.message || 'Request failed');
            error.status = response.status;
            error.data = data;
            throw error;
        }

        return data;
    }

    get(endpoint) {
        return this.request(endpoint);
    }

    post(endpoint, body) {
        if (body instanceof FormData) {
            return this.request(endpoint, { method: 'POST', body });
        }
        return this.request(endpoint, { method: 'POST', body: JSON.stringify(body) });
    }

    put(endpoint, body) {
        return this.request(endpoint, { method: 'PUT', body: JSON.stringify(body) });
    }

    delete(endpoint, body) {
        const opts = { method: 'DELETE' };
        if (body !== undefined) opts.body = JSON.stringify(body);
        return this.request(endpoint, opts);
    }
}

const api = new ApiClient();
export default api;
