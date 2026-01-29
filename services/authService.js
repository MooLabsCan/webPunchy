// for prod use const apiUrl = 'https://liap.ca/api/check_session.php'
// for dev use '/back/api/check_session.php'
const apiUrl = '/back/api/check_session.php'

const getSessionData = async (payload = {}) => {
    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });
        return await response.json();
    } catch (error) {
        console.error('Session check failed:', error);
        return null;
    }
};

function getCurrentLang() {
    try {
        const fromStorage = localStorage.getItem('lang') || sessionStorage.getItem('lang')
        if (fromStorage) return fromStorage.toUpperCase()
    } catch {}
    try {
        const nav = (navigator.language || navigator.userLanguage || 'en').slice(0,2).toUpperCase()
        return nav === 'FR' ? 'FR' : 'EN'
    } catch {}
    return 'EN'
}

export function buildLoginUrl() {
    const hostname = window.location.hostname
    const base = hostname === 'localhost'
      ? 'http://localhost/mapmoo/login.php'
      : 'https://liap.ca/login.php'
    const params = new URLSearchParams({ site: 'punchy', lang: getCurrentLang() })
    // carry referral from current page if present
    try {
        const cur = new URL(window.location.href)
        const ref = cur.searchParams.get('ref')
        if (ref) {
            params.set('og', ref)
            try { localStorage.setItem('pending_referrer', ref) } catch {}
        }
    } catch {}
    return `${base}?${params.toString()}`
}

function getToken() {
    // 1) Prefer URL param provided by external login: ?au=TOKEN
    try {
        const url = new URL(window.location.href)
        const au = url.searchParams.get('au')
        if (au) {
            // Persist to our own storage for subsequent visits
            try {
                localStorage.setItem('auth_token', au)
                sessionStorage.setItem('auth_token', au)
            } catch {}
            // Remove token from the URL to avoid leaking it via referrers/logs
            try {
                url.searchParams.delete('au')
                const clean = url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash
                window.history.replaceState({}, document.title, clean)
            } catch {}
            return au
        }
    } catch {}

    // 2) Try multiple places to locate a previously saved token
    try {
        const t = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token')
        if (t) return t
    } catch {}

    // 3) Optionally support cookies named token or auth_token
    try {
        const match = document.cookie.match(/(?:^|; )(?:auth_token|token)=([^;]+)/)
        if (match) return decodeURIComponent(match[1])
    } catch {}

    return null
}

function isValidAuthResponse(resp) {
    if (!resp || typeof resp !== 'object') return false
    if (resp.error) return false
    if (resp.valid === true || resp.success === true || resp.ok === true) return true
    // Heuristic: presence of common identity fields
    const keys = Object.keys(resp)
    const identityHints = ['user', 'user_id', 'uid', 'email', 'name']
    return identityHints.some(k => keys.includes(k))
}

export async function ensureAuthenticated() {
    const token = getToken()
    if (!token) {
        window.location.href = buildLoginUrl()
        return Promise.reject(new Error('No token'))
    }
    const resp = await getSessionData({ token })
    if (!isValidAuthResponse(resp)) {
        window.location.href = buildLoginUrl()
        return Promise.reject(new Error('Invalid token'))
    }
    return resp
}

export const authService = {
    getSessionData,
    ensureAuthenticated,
    buildLoginUrl
};