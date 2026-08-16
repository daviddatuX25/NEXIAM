import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Stale-CSRF resilience (founder report 2026-08-15): a 419 usually means the
// page's CSRF token predates a session rotation (e.g. logging in another tab).
// Reload once so Inertia re-syncs the token; the user simply resubmits.
let refreshingOn419 = false;
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419 && !refreshingOn419) {
            refreshingOn419 = true;
            window.location.reload();
        }
        return Promise.reject(error);
    },
);
