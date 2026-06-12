import axios from 'axios';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.withCredentials = true;

export { ensureCsrfCookie, csrfReady } from './services/csrf';
