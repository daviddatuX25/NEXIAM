// @vitest-environment jsdom
import axios from 'axios';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { http } from '@inertiajs/svelte';
import { createStaffGenerationLifecycle, installStaffGenerationGuard, isStaffAccountChangedResponse } from '../../resources/js/lib/staff-account-generation';

const disposers: Array<() => void> = [];

afterEach(() => {
    while (disposers.length > 0) disposers.pop()?.();
    window.localStorage.clear();
    vi.unstubAllGlobals();
});

function install(generation = 'generation-current', navigate = vi.fn()) {
    const router = {
        on: vi.fn(() => vi.fn()),
    };
    const dispose = installStaffGenerationGuard({
        generation,
        router: router as never,
        axios: axios.create(),
        targetWindow: window,
        navigate,
    });
    disposers.push(dispose);
    return { navigate, router, dispose };
}

describe('staff account generation transport guard', () => {
    it('recognizes only the structured account-change conflict and tolerates malformed bodies', () => {
        expect(isStaffAccountChangedResponse(409, '{"error":"staff_account_changed"}')).toBe(true);
        expect(isStaffAccountChangedResponse(409, '{not-json')).toBe(false);
        expect(isStaffAccountChangedResponse(403, { error: 'staff_account_changed' })).toBe(false);
        expect(isStaffAccountChangedResponse(409, { error: 'forbidden' })).toBe(false);
    });
    it('removes the old request identity on logout before allowing another login', async () => {
        const navigate = vi.fn();
        const lifecycle = createStaffGenerationLifecycle(null, (generation) => install(generation, navigate).dispose, navigate);
        disposers.push(() => lifecycle.dispose());
        lifecycle.updateFromInertiaResponse(JSON.stringify({
            props: { auth: { staff_inactivity_generation: 'generation-first' } },
        }));
        const staffRequest = await http.processRequest({ method: 'get', url: '/admissions', headers: {} });
        expect(staffRequest.headers?.['X-Staff-Generation']).toBe('generation-first');

        lifecycle.updateFromInertiaResponse(JSON.stringify({
            props: { auth: { staff_inactivity_generation: null } },
        }));
        const loginRequest = await http.processRequest({ method: 'post', url: '/login', headers: {} });
        expect(loginRequest.headers?.['X-Staff-Generation']).toBeUndefined();

        lifecycle.update('generation-second');
        const currentRequest = await http.processRequest({ method: 'get', url: '/staff', headers: {} });
        expect(currentRequest.headers?.['X-Staff-Generation']).toBe('generation-second');
        expect(navigate).not.toHaveBeenCalled();
        lifecycle.update('generation-third');
        expect(navigate).toHaveBeenCalledWith('/staff');
    });
    it('recovers a tab already returned to staff login when another tab completes sign-in', () => {
        const navigate = vi.fn();
        const lifecycle = createStaffGenerationLifecycle(null, () => () => {}, navigate);
        disposers.push(() => lifecycle.dispose());
        window.history.replaceState(null, '', '/login');
        try {
            window.dispatchEvent(new StorageEvent('storage', {
                key: 'nexiam.staff.account-generation',
                newValue: 'generation-new-login',
            }));
            expect(navigate).toHaveBeenCalledWith('/staff');
            navigate.mockClear();
            window.history.replaceState(null, '', '/applicant/login');
            window.dispatchEvent(new StorageEvent('storage', {
                key: 'nexiam.staff.account-generation',
                newValue: 'generation-another-login',
            }));
            expect(navigate).not.toHaveBeenCalled();
        } finally {
            window.history.replaceState(null, '', '/');
        }
    });


    it('recovers a stale tab without overwriting the newer cross-tab generation', () => {
        window.localStorage.setItem('nexiam.staff.account-generation', 'generation-current');
        const { navigate } = install();
        window.localStorage.setItem('nexiam.staff.account-generation', 'generation-newer');

        window.dispatchEvent(new Event('focus'));

        expect(navigate).toHaveBeenCalledWith('/staff');
        expect(window.localStorage.getItem('nexiam.staff.account-generation')).toBe('generation-newer');
    });

    it('adds the captured generation to same-origin Request headers and leaves external requests untouched', async () => {
        const fetchMock = vi.fn(async (_input: RequestInfo | URL, _init?: RequestInit) => new Response('ok'));
        vi.stubGlobal('fetch', fetchMock);
        install();

        const request = new Request(`${window.location.origin}/staff/profile`, { headers: { Accept: 'application/json' } });
        await window.fetch(request);
        await window.fetch('https://outside.invalid/resource');

        const forwardedHeaders = new Headers(fetchMock.mock.calls[0]?.[1]?.headers);
        expect(forwardedHeaders.get('Accept')).toBe('application/json');
        expect(forwardedHeaders.get('X-Staff-Generation')).toBe('generation-current');
        expect(fetchMock.mock.calls[1]?.[1]).toBeUndefined();
    });
    it('adds the generation to same-origin Inertia transport only', async () => {
        install();

        const sameOrigin = await http.processRequest({
            method: 'get',
            url: '/staff/profile',
            headers: {},
        });
        const external = await http.processRequest({
            method: 'get',
            url: 'https://outside.invalid/resource',
            headers: {},
        });

        expect(sameOrigin.headers?.['X-Staff-Generation']).toBe('generation-current');
        expect(external.headers?.['X-Staff-Generation']).toBeUndefined();
    });

    it('restores the shared fetch and removes listeners on teardown', () => {
        const originalFetch = window.fetch;
        const { dispose, navigate } = install();
        expect(window.fetch).not.toBe(originalFetch);

        dispose();
        window.localStorage.setItem('nexiam.staff.account-generation', 'generation-newer');
        window.dispatchEvent(new Event('focus'));

        expect(window.fetch).toBe(originalFetch);
        expect(navigate).not.toHaveBeenCalled();
    });


    it('hard-navigates on the account-change response without replaying the request', async () => {
        const fetchMock = vi.fn(async () => new Response(JSON.stringify({ error: 'staff_account_changed' }), { status: 409 }));
        vi.stubGlobal('fetch', fetchMock);
        const { navigate } = install();

        const response = await window.fetch('/staff/mutate', { method: 'POST', body: 'submitted' });

        expect(response.status).toBe(409);
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(navigate).toHaveBeenCalledWith('/staff');
    });
});
