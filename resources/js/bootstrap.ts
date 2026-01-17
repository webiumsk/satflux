import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// Get CSRF cookie on app boot
// This ensures CSRF token is available for authenticated requests
export const csrfReady = axios.get('/sanctum/csrf-cookie', { withCredentials: true })
    .then(() => true)
    .catch(() => false);








