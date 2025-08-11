import axios from 'axios';

// Configure axios defaults
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Set up CSRF token
const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Add request interceptor to ensure CSRF token is always included
axios.interceptors.request.use(
    (config) => {
        // Ensure CSRF token is always present
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken && config.headers) {
            config.headers['X-CSRF-TOKEN'] = csrfToken;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Add response interceptor for better error handling
axios.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        // Handle common HTTP errors
        if (error.response?.status === 419) {
            console.error('CSRF token mismatch. Please refresh the page.');
        } else if (error.response?.status === 401) {
            console.error('Unauthorized. Please log in again.');
        } else if (error.response?.status === 403) {
            console.error('Forbidden. You do not have permission to perform this action.');
        }
        
        return Promise.reject(error);
    }
);

export default axios;
