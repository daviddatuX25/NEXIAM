// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const { routerGet, routerReload } = vi.hoisted(() => ({
    routerGet: vi.fn(),
    routerReload: vi.fn(),
}));

vi.mock('@inertiajs/svelte', () => ({
    Link: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
    router: { get: routerGet, reload: routerReload },
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));
vi.mock('@/lib/toast.js', () => ({ success: vi.fn(), error: vi.fn() }));

import Quotas from '../../resources/js/Pages/Setup/Quotas.svelte';

afterEach(() => {
    cleanup();
});

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
    routerGet.mockReset();
    routerReload.mockReset();
});

const projection = (writeEnabled = true) => [{
    program: { id: 14, code: 'BSIT', name: 'Information Technology' },
    quota: { slots: 18, version: 3, last_notice: null },
    commitments: 11,
    remaining: 7,
    over_capacity: 0,
    can_manage: true,
    write_enabled: writeEnabled,
    hub_url: '/setup/programs/14/hub?cycle_id=8&tab=quotas',
}];

const renderQuotas = (writeEnabled = true) => render(Quotas, {
    cycle: { id: 8, name: '2026–2027 · First semester', staff_label: '2026–2027 · First semester' },
    cycles: [{ id: 8, staff_label: '2026–2027 · First semester' }],
    quota_projections: projection(writeEnabled),
});

async function openQuotaDialog() {
    const buttons = await screen.findAllByRole('button', { name: 'Edit Quota' });
    await fireEvent.click(buttons[0]);
    return screen.findByRole('dialog');
}

async function fillQuota(capacity = '24', changeReason = 'Approved cohort capacity review') {
    await fireEvent.input(screen.getByLabelText('Capacity slots'), { target: { value: capacity } });
    await fireEvent.input(screen.getByLabelText('Reason for change'), { target: { value: changeReason } });
}

function jsonResponse(status: number, body: unknown) {
    return Promise.resolve(new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    }));
}

describe('quota Edit/Set dialog request recovery', () => {
    it('submits the reason, current version, and idempotency key, then refreshes the page projection', async () => {
        vi.mocked(fetch).mockImplementationOnce(() => jsonResponse(200, {
            data: { id: 4, program_id: 14, academic_cycle_id: 8, slots: 24, version: 4 },
            receipt: { change_id: 9, version: 4 },
        }));
        renderQuotas();
        await openQuotaDialog();
        expect(screen.getByTestId('quota-version').textContent).toContain('3');
        await fillQuota();
        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));

        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));
        const [url, request] = vi.mocked(fetch).mock.calls[0] as [string, RequestInit];
        const payload = JSON.parse(String(request.body));
        expect(url).toBe('/setup/quotas');
        expect(request.method).toBe('POST');
        expect(request.headers).toMatchObject({ Accept: 'application/json', 'Content-Type': 'application/json' });
        expect(payload).toMatchObject({
            program_id: 14,
            academic_cycle_id: 8,
            slots: 24,
            reason: 'Approved cohort capacity review',
            expected_version: 3,
        });
        expect(payload.idempotency_key).toMatch(/^[0-9a-f-]{36}$/i);
        await waitFor(() => expect(routerReload).toHaveBeenCalledWith({ preserveScroll: true }));
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    });

    it('binds bulk quota members to their own versions and an outer idempotency identity', async () => {
        vi.mocked(fetch).mockImplementationOnce(() => jsonResponse(200, { data: [{ slots: 24, version: 4 }] }));
        renderQuotas();
        await fireEvent.click(screen.getByRole('checkbox', { name: 'Select all manageable programs' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Bulk Set Quota (1)' }));
        await screen.findByRole('dialog');
        await fillQuota();
        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));

        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));
        const [url, request] = vi.mocked(fetch).mock.calls[0] as [string, RequestInit];
        const payload = JSON.parse(String(request.body));
        expect(url).toBe('/setup/quotas/bulk');
        expect(payload.academic_cycle_id).toBe(8);
        expect(payload.idempotency_key).toMatch(/^[0-9a-f-]{36}$/i);
        expect(payload.quotas).toHaveLength(1);
        expect(payload.quotas[0]).toMatchObject({
            program_id: 14,
            slots: 24,
            reason: 'Approved cohort capacity review',
            expected_version: 3,
        });
        await waitFor(() => expect(routerReload).toHaveBeenCalled());
    });

    it('preserves entered capacity and reason on conflict and only resubmits after explicit review with a new key', async () => {
        vi.mocked(fetch)
            .mockImplementationOnce(() => jsonResponse(409, {
                code: 'quota_version_conflict',
                message: 'This quota changed after you opened it.',
                current: { slots: 20, version: 4 },
            }))
            .mockImplementationOnce(() => jsonResponse(200, { data: { slots: 24, version: 5 } }));
        renderQuotas();
        await openQuotaDialog();
        await fillQuota();
        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));

        expect(await screen.findByTestId('quota-conflict')).toBeTruthy();
        expect(screen.getByText(/saved capacity 20; current version is 4/i)).toBeTruthy();
        expect((screen.getByLabelText('Capacity slots') as HTMLInputElement).value).toBe('24');
        expect((screen.getByLabelText('Reason for change') as HTMLTextAreaElement).value).toBe('Approved cohort capacity review');
        const firstKey = JSON.parse(String(vi.mocked(fetch).mock.calls[0][1]?.body)).idempotency_key;
        expect(screen.getByRole('button', { name: 'Resubmit reviewed quota' })).toHaveProperty('disabled', true);

        await fireEvent.click(screen.getByRole('button', { name: 'Review current version' }));
        expect(screen.getByTestId('quota-version').textContent).toContain('4');
        await fireEvent.click(screen.getByRole('button', { name: 'Resubmit reviewed quota' }));
        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2));
        const secondPayload = JSON.parse(String(vi.mocked(fetch).mock.calls[1][1]?.body));
        expect(secondPayload.expected_version).toBe(4);
        expect(secondPayload.idempotency_key).not.toBe(firstKey);
        expect(secondPayload).toMatchObject({ slots: 24, reason: 'Approved cohort capacity review' });
        await waitFor(() => expect(routerReload).toHaveBeenCalled());
    });

    it('retries an unknown network outcome with the exact same payload and idempotency key', async () => {
        vi.mocked(fetch)
            .mockRejectedValueOnce(new TypeError('Network unavailable'))
            .mockImplementationOnce(() => jsonResponse(200, { data: { slots: 24, version: 4 } }));
        renderQuotas();
        await openQuotaDialog();
        await fillQuota();
        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
        expect(await screen.findByTestId('quota-unknown-error')).toBeTruthy();
        const original = vi.mocked(fetch).mock.calls[0][1]?.body;

        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
        await waitFor(() => expect(fetch).toHaveBeenCalledTimes(2));
        expect(vi.mocked(fetch).mock.calls[1][1]?.body).toBe(original);
        await waitFor(() => expect(routerReload).toHaveBeenCalled());
    });

    it('distinguishes validation and denied responses without discarding the form', async () => {
        vi.mocked(fetch)
            .mockImplementationOnce(() => jsonResponse(422, { message: 'Capacity is invalid.', errors: { slots: ['Capacity must be a whole number.'] } }))
            .mockImplementationOnce(() => jsonResponse(403, { message: 'Forbidden.' }));
        renderQuotas();
        await openQuotaDialog();
        await fillQuota();
        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
        expect(await screen.findByText('Capacity must be a whole number.')).toBeTruthy();
        expect((screen.getByLabelText('Capacity slots') as HTMLInputElement).value).toBe('24');
        const originalKey = JSON.parse(String(vi.mocked(fetch).mock.calls[0][1]?.body)).idempotency_key;
        await fireEvent.input(screen.getByLabelText('Capacity slots'), { target: { value: '25' } });
        await fireEvent.click(screen.getByRole('button', { name: 'Save quota' }));
        expect(await screen.findByTestId('quota-denied-error')).toBeTruthy();
        const changedPayload = JSON.parse(String(vi.mocked(fetch).mock.calls[1][1]?.body));
        expect(changedPayload.idempotency_key).not.toBe(originalKey);
        expect(changedPayload.slots).toBe(25);
        expect((screen.getByLabelText('Reason for change') as HTMLTextAreaElement).value).toBe('Approved cohort capacity review');
    });

    it('cancels without sending a request and omits write controls when the server projection is read-only', async () => {
        renderQuotas();
        await openQuotaDialog();
        await fillQuota();
        await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(fetch).not.toHaveBeenCalled();

        cleanup();
        renderQuotas(false);
        expect(screen.queryByRole('button', { name: 'Edit Quota' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Set Quota' })).toBeNull();
    });
});
