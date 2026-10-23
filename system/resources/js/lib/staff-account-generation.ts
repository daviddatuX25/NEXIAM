import type { AxiosInstance, AxiosResponse } from 'axios';
import type { Router } from '@inertiajs/core';
import { http } from '@inertiajs/svelte';

const GENERATION_STORAGE_KEY = 'nexiam.staff.account-generation';
const GENERATION_HEADER = 'X-Staff-Generation';
const STAFF_HOME = '/staff';

type StaffGenerationGuardOptions = {
    generation: string;
    router: Router;
    axios: AxiosInstance | undefined;
    targetWindow?: Window;
    navigate?: (path: string) => void;
};

type ErrorPayload = { error?: unknown } | null;

function parseErrorPayload(data: unknown): ErrorPayload {
    if (typeof data === 'string') {
        try {
            const parsed: unknown = JSON.parse(data);
            return typeof parsed === 'object' && parsed !== null ? parsed as ErrorPayload : null;
        } catch {
            return null;
        }
    }

    return typeof data === 'object' && data !== null ? data as ErrorPayload : null;
}

export function isStaffAccountChangedResponse(status: number, data: unknown): boolean {
    return status === 409 && parseErrorPayload(data)?.error === 'staff_account_changed';
}

function isSameOriginUrl(value: string | URL, targetWindow: Window): boolean {
    try {
        return new URL(value.toString(), targetWindow.location.href).origin === targetWindow.location.origin;
    } catch {
        return false;
    }
}

function isCurrentGeneration(generation: string, targetWindow: Window): boolean {
    try {
        return targetWindow.localStorage.getItem(GENERATION_STORAGE_KEY) === generation;
    } catch {
        return true;
    }
}


function readResponsePayload(response: Response): Promise<unknown> {
    return response.clone().json().catch(() => null);
}

function isSameOriginAxiosRequest(url: string, baseURL: string | undefined, targetWindow: Window): boolean {
    try {
        const base = baseURL ? new URL(baseURL, targetWindow.location.href) : targetWindow.location.href;
        return new URL(url, base).origin === targetWindow.location.origin;
    } catch {
        return false;
    }
}

export function createStaffGenerationLifecycle(
    initialGeneration: string | null,
    install: (generation: string) => () => void,
    navigate: (path: string) => void,
): {
    update(generation: string | null): void;
    updateFromInertiaResponse(data: string): void;
    dispose(): void;
} {
    let generation = initialGeneration;
    let disposeGuard = initialGeneration === null ? undefined : install(initialGeneration);
    let navigating = false;
    const loginWindow = typeof window === 'undefined' ? null : window;
    const recoverStaffLogin = (event: StorageEvent): void => {
        if (
            generation === null
            && loginWindow?.location.pathname === '/login'
            && event.key === GENERATION_STORAGE_KEY
            && event.newValue
            && !navigating
        ) {
            navigating = true;
            navigate(STAFF_HOME);
        }
    };
    loginWindow?.addEventListener('storage', recoverStaffLogin);

    function update(nextGeneration: string | null): void {
        if (nextGeneration === null) {
            disposeGuard?.();
            disposeGuard = undefined;
            generation = null;
            navigating = false;
            return;
        }
        if (generation === null) {
            generation = nextGeneration;
            disposeGuard = install(nextGeneration);
            return;
        }
        if (nextGeneration !== generation && !navigating) {
            navigating = true;
            navigate(STAFF_HOME);
        }
    }

    function updateFromInertiaResponse(data: string): void {
        try {
            const payload: unknown = JSON.parse(data);
            if (typeof payload !== 'object' || payload === null || !('props' in payload)) return;
            const props = payload.props;
            if (typeof props !== 'object' || props === null || !('auth' in props)) return;
            const auth = props.auth;
            if (typeof auth !== 'object' || auth === null || !('staff_inactivity_generation' in auth)) return;
            const nextGeneration = auth.staff_inactivity_generation;
            if (nextGeneration === null) {
                update(null);
            } else if (typeof nextGeneration === 'string' && nextGeneration.trim() !== '') {
                update(nextGeneration);
            }
        } catch {
            // Non-page responses do not carry a generation.
        }
    }

    return {
        update,
        updateFromInertiaResponse,
        dispose() {
            loginWindow?.removeEventListener('storage', recoverStaffLogin);
            disposeGuard?.();
            disposeGuard = undefined;
        },
    };
}

export function installStaffGenerationGuard({
    generation,
    router,
    axios,
    targetWindow = window,
    navigate = (path) => targetWindow.location.replace(path),
}: StaffGenerationGuardOptions): () => void {
    let disposed = false;
    let navigating = false;
    const originalFetch = targetWindow.fetch;
    const disposers: Array<() => void> = [];

    const recoverToStaffHome = (): void => {
        if (disposed || navigating) return;
        navigating = true;
        navigate(STAFF_HOME);
    };

    const verifyGeneration = (): boolean => {
        if (isCurrentGeneration(generation, targetWindow)) return true;
        recoverToStaffHome();
        return false;
    };

    const publishInitialGeneration = (): void => {
        try {
            targetWindow.localStorage.setItem(GENERATION_STORAGE_KEY, generation);
        } catch {
            // The request header remains authoritative when browser storage is unavailable.
        }
    };

    publishInitialGeneration();

    const onFocus = (): void => { verifyGeneration(); };
    const onPageShow = (): void => { verifyGeneration(); };
    const onStorage = (event: StorageEvent): void => {
        if (event.key === GENERATION_STORAGE_KEY && event.newValue !== generation) {
            recoverToStaffHome();
        }
    };

    targetWindow.addEventListener('focus', onFocus);
    targetWindow.addEventListener('pageshow', onPageShow);
    targetWindow.addEventListener('storage', onStorage);

    disposers.push(router.on('before', (event) => {
        if (!verifyGeneration()) {
            event.preventDefault();
            return false;
        }
    }));

    disposers.push(http.onRequest((config) => {
        if (isSameOriginUrl(config.url, targetWindow)) {
            config.headers = { ...config.headers, [GENERATION_HEADER]: generation };
        }
        return config;
    }));

    disposers.push(http.onError((error) => {
        const response = 'response' in error ? error.response : undefined;
        if (response && isStaffAccountChangedResponse(response.status, response.data)) {
            recoverToStaffHome();
        }
    }));

    disposers.push(router.on('httpException', (event) => {
        const response = event.detail.response;
        if (isStaffAccountChangedResponse(response.status, response.data)) {
            event.preventDefault();
            recoverToStaffHome();
        }
    }));

    if (axios) {
        const requestId = axios.interceptors.request.use((config) => {
            if (config.url && isSameOriginAxiosRequest(config.url, config.baseURL, targetWindow)) {
                config.headers.set(GENERATION_HEADER, generation);
            }
            return config;
        });
        const responseId = axios.interceptors.response.use(
            (response) => response,
            (error: { response?: AxiosResponse; config?: { url?: string; baseURL?: string } }) => {
                const response = error.response;
                const config = error.config;
                if (response && config?.url && isSameOriginAxiosRequest(config.url, config.baseURL, targetWindow) && isStaffAccountChangedResponse(response.status, response.data)) {
                    recoverToStaffHome();
                }
                return Promise.reject(error);
            },
        );
        disposers.push(() => {
            axios.interceptors.request.eject(requestId);
            axios.interceptors.response.eject(responseId);
        });
    }

    const guardedFetch: typeof targetWindow.fetch = async (input, init) => {
        const inputUrl = input instanceof Request ? input.url : input.toString();
        if (!isSameOriginUrl(inputUrl, targetWindow)) {
            return originalFetch.call(targetWindow, input, init);
        }

        const headers = new Headers(input instanceof Request ? input.headers : undefined);
        new Headers(init?.headers).forEach((value, key) => headers.set(key, value));
        headers.set(GENERATION_HEADER, generation);
        const response = await originalFetch.call(targetWindow, input, { ...init, headers });
        if (response.status === 409 && isStaffAccountChangedResponse(response.status, await readResponsePayload(response))) {
            recoverToStaffHome();
        }
        return response;
    };

    targetWindow.fetch = guardedFetch;

    return () => {
        if (disposed) return;
        disposed = true;
        targetWindow.removeEventListener('focus', onFocus);
        targetWindow.removeEventListener('pageshow', onPageShow);
        targetWindow.removeEventListener('storage', onStorage);
        if (targetWindow.fetch === guardedFetch) targetWindow.fetch = originalFetch;
        for (const dispose of disposers) dispose();
    };
}
