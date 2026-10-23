// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

import FastOnboardingModal from '../../resources/js/Pages/Staff/Personnel/FastOnboardingModal.svelte';
import RolloverModal from '../../resources/js/Pages/Staff/Personnel/RolloverModal.svelte';

const roles = [
    { name: 'program_head', display_name: 'Program Head' },
    { name: 'registrar_clerk', display_name: 'Registrar Clerk' },
    { name: 'registrar_support_staff', display_name: 'Registrar Support' },
    { name: 'guidance_counselor', display_name: 'Guidance Counselor' },
    { name: 'guidance_clerk', display_name: 'Guidance Clerk' },
    { name: 'guidance_support_staff', display_name: 'Guidance Support' },
    { name: 'institutional_admin', display_name: 'Admin Office Staff' },
];
const cycles = [{ code: 'AY27', name: '2027-2028', is_active: true }];
const programs = [{ code: 'BSIT', name: 'Information Technology' }, { code: 'BSCS', name: 'Computer Science' }];

let uuidCounter = 0;
beforeEach(() => {
    uuidCounter = 0;
    vi.stubGlobal('fetch', vi.fn());
    vi.stubGlobal('crypto', {
        ...crypto,
        randomUUID: vi.fn(() => `00000000-0000-4000-8000-${String(++uuidCounter).padStart(12, '0')}`),
    });
    document.body.innerHTML = '<meta name="csrf-token" content="test">';
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it('keeps one-time issued vouchers visible through parent-prop reload and submits stable operation identities', async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementationOnce(async (_url, init) => {
        const payload = JSON.parse(String(init?.body));
        return { ok: true, json: async () => ({ rows: [{
            operation_id: payload.recipients[0].operation_id, user_id: 5, name: 'Sam Staff', office: 'program', status: 'issued',
            public_reference: 'REF-5', token: 'one-time-token', fallback: 'ABCD-EFGH', expires_at_display: 'Never', print_hash: 'hash',
        }] }) } as Response;
    });
    const onSuccess = vi.fn();
    const view = render(FastOnboardingModal, { props: {
        open: true, cycles, programs, roleOptions: roles, can_full_hr: true, onSuccess,
    } });
    await fireEvent.input(screen.getByPlaceholderText('Full Name'), { target: { value: 'Sam Staff' } });
    await fireEvent.click(screen.getByRole('button', { name: /Commit & Generate Vouchers/ }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
    await waitFor(() => expect(screen.getByText(/ABCD-EFGH/)).toBeTruthy());
    expect(onSuccess).toHaveBeenCalledOnce();
    const request = JSON.parse(fetchMock.mock.calls[0][1]?.body as string);
    const rowId = request.recipients[0].operation_id;
    const batchId = request.operation_id;

    await view.rerender({ open: true, cycles: [{ ...cycles[0], name: 'Changed after reload' }], programs, roleOptions: roles, can_full_hr: true });
    expect(screen.getByText(/ABCD-EFGH/)).toBeTruthy();
    expect(screen.queryByPlaceholderText('Full Name')).toBeNull();
    expect(batchId).toBeTruthy();
    expect(rowId).toBeTruthy();
});

it('resets incompatible office role, corrects failed and conflicted rows, and keeps callbacks and operation identities correct', async () => {
    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementationOnce(async (_url, init) => {
        const payload = JSON.parse(String(init?.body));
        return { ok: true, json: async () => ({ rows: [
            { operation_id: payload.recipients[0].operation_id, name: 'Ada', office: 'registrar', status: 'failed', message: 'Correct role and retry.' },
            { operation_id: 'other-operation', name: 'Lin', office: 'registrar', status: 'already_committed', message: 'Previously provisioned.' },
            { operation_id: 'old-conflict-id', name: 'Max', office: 'registrar', status: 'conflicted', message: 'Use a fresh operation identity.' },
        ] }) } as Response;
    });
    fetchMock.mockImplementationOnce(async (_url, init) => {
        const payload = JSON.parse(String(init?.body));
        return { ok: true, json: async () => ({ rows: payload.recipients.map((row, index) => ({
            operation_id: row.operation_id, name: row.name, office: row.office, status: 'issued',
            user_id: index + 8, public_reference: `REF-${index + 8}`, token: `token-${index}`, fallback: `CODE-${index}`, print_hash: 'hash',
        })) }) } as Response;
    });
    const onSuccess = vi.fn();
    render(FastOnboardingModal, { props: { open: true, cycles, programs, roleOptions: roles, can_full_hr: true, onSuccess } });
    await fireEvent.input(screen.getByPlaceholderText('Full Name'), { target: { value: 'Ada' } });
    const selects = screen.getAllByRole('combobox');
    await fireEvent.change(selects[2], { target: { value: 'registrar' } });
    expect(Array.from(selects[3].querySelectorAll('option')).map((option) => option.value)).toEqual(['registrar_clerk', 'registrar_support_staff']);
    await fireEvent.click(screen.getByRole('button', { name: /Commit & Generate Vouchers/ }));
    await waitFor(() => expect(screen.getByText('failed')).toBeTruthy());
    expect(screen.getByText('already committed')).toBeTruthy();
    expect(screen.getByText('conflicted')).toBeTruthy();
    expect(screen.getByText(/one-time voucher details cannot be recovered/i)).toBeTruthy();
    expect(onSuccess).toHaveBeenCalledOnce();
    const firstRequest = JSON.parse(fetchMock.mock.calls[0][1]?.body as string);
    const correctionButtons = screen.getAllByRole('button', { name: 'Correct this row' });
    await fireEvent.click(correctionButtons[1]);
    expect((screen.getByRole('combobox', { name: 'Role for row 2' }) as HTMLSelectElement).value).toBe('registrar_clerk');
    await fireEvent.click(screen.getByRole('button', { name: /Commit & Generate Vouchers/ }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    const retryRequest = JSON.parse(fetchMock.mock.calls[1][1]?.body as string);
    expect(retryRequest.operation_id).not.toBe(firstRequest.operation_id);
    expect(retryRequest.recipients[0].operation_id).toBe(firstRequest.recipients[0].operation_id);
    expect(retryRequest.recipients[1].operation_id).not.toBe('old-conflict-id');
    await waitFor(() => expect(onSuccess).toHaveBeenCalledTimes(2));
    expect(screen.queryByRole('option', { name: 'Dean' })).toBeNull();
});

it('excludes inactive, unmanaged, and self targets while preserving multi-program rotation selections', async () => {
    const personnel = [
        { id: 1, name: 'Eligible', office: 'program', status: 'active', can_manage_user: true, is_self: false, program_codes: ['BSIT', 'BSCS'] },
        { id: 2, name: 'Self', office: 'program', status: 'active', can_manage_user: true, is_self: true, program_codes: [] },
        { id: 3, name: 'Inactive', office: 'program', status: 'inactive', can_manage_user: true, is_self: false, program_codes: [] },
        { id: 4, name: 'Unmanageable', office: 'program', status: 'active', can_manage_user: false, is_self: false, program_codes: [] },
    ];
    const fetchMock = vi.mocked(fetch);
    fetchMock.mockResolvedValueOnce({ ok: true, json: async () => ({ retained: 0, rotated: 1, archived: 0 }) } as Response);
    render(RolloverModal, { props: { open: true, cycles, programs, personnel } });
    expect(screen.getByText('Eligible')).toBeTruthy();
    expect(screen.queryByText('Self')).toBeNull();
    expect(screen.queryByText('Inactive')).toBeNull();
    expect(screen.queryByText('Unmanageable')).toBeNull();
    await fireEvent.change(screen.getByRole('combobox', { name: 'Disposition for Eligible' }), { target: { value: 'rotate' } });
    const programPicker = screen.getByRole('listbox', { name: 'Programs for Eligible' });
    expect(Array.from(programPicker.querySelectorAll('option')).filter((option) => (option as HTMLOptionElement).selected).map((option) => (option as HTMLOptionElement).value)).toEqual(['BSIT', 'BSCS']);
    await fireEvent.click(screen.getByRole('button', { name: /Confirm Rollover/ }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
    const payload = JSON.parse(fetchMock.mock.calls[0][1]?.body as string);
    expect(payload.dispositions).toEqual([{ user_id: 1, disposition: 'rotate', program_codes: ['BSIT', 'BSCS'] }]);
});
