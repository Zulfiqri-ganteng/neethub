// Shared auth/fetch helper for every NetHub dashboard page (owner + customer
// layouts both load this). Handles the access-token/refresh-token dance so
// individual pages never have to think about it.
//
// Why this exists: access tokens expire after 15 minutes (by design, short-
// lived for security). Without this, the very first API call after expiry
// got a 401 and immediately logged the user out mid-session — annoying for
// anyone actively using the dashboard. Fix: on a 401, try the refresh
// endpoint once (rotates both tokens) and retry the original request before
// giving up. Only a failed refresh (refresh token itself expired/invalid,
// ~7 days) actually logs the user out.

let nethubToken = localStorage.getItem('nethub_access_token');
if (!nethubToken) window.location.href = '/login';

function nethubLogout() {
    localStorage.clear();
    window.location.href = '/login';
}

async function nethubRefreshToken() {
    const refreshToken = localStorage.getItem('nethub_refresh_token');
    if (!refreshToken) return false;

    try {
        const res = await fetch('/api/auth/refresh', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: refreshToken }),
        });
        const body = await res.json();
        if (!body.success) return false;

        nethubToken = body.data.access_token;
        localStorage.setItem('nethub_access_token', body.data.access_token);
        localStorage.setItem('nethub_refresh_token', body.data.refresh_token);
        return true;
    } catch (err) {
        return false;
    }
}

async function nethubFetch(url, options = {}, isRetry = false) {
    options.headers = Object.assign({}, options.headers, { Authorization: 'Bearer ' + nethubToken });
    const res = await fetch(url, options);

    if (res.status === 401) {
        if (!isRetry && await nethubRefreshToken()) {
            return nethubFetch(url, options, true);
        }
        nethubLogout();
        throw new Error('Unauthenticated');
    }

    return res.json();
}
