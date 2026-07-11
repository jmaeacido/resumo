import axios from 'axios';

declare global {
    interface Window {
        axios: typeof axios;
        Ziggy?: {
            url?: string;
        };
    }
}

export function appBasePath(): string {
    const ziggyUrl = window.Ziggy?.url;

    if (!ziggyUrl) {
        return '';
    }

    try {
        return new URL(ziggyUrl).pathname.replace(/\/$/, '');
    } catch {
        return '';
    }
}

export function normalizeAppUrl(url: string): string {
    if (/^https?:\/\//i.test(url)) {
        return url;
    }

    const basePath = appBasePath();
    const path = url.startsWith('/') ? url : `/${url}`;

    if (basePath && (path === basePath || path.startsWith(`${basePath}/`))) {
        return `${window.location.origin}${path}`;
    }

    return `${window.location.origin}${basePath}${path}`;
}

const basePath = appBasePath();

window.axios = axios;
window.axios.defaults.baseURL = basePath;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

if (typeof window.route === 'function') {
    const originalRoute = window.route;

    window.route = ((name?: unknown, params?: unknown, absolute?: unknown, config?: unknown) => {
        const result = originalRoute(
            name as never,
            params as never,
            absolute as never,
            config as never,
        );

        if (typeof result === 'string') {
            return normalizeAppUrl(result);
        }

        return {
            ...result,
            toString: () => normalizeAppUrl(String(result)),
        };
    }) as typeof window.route;
}
