import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

// Get CSRF cookie before making requests
axios.get('/sanctum/csrf-cookie').catch(() => {
    // CSRF cookie endpoint may not be available on first load
});

export {};

