// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const hooks = vi.hoisted(() => ({ scan: null as null | ((value: string) => void), post: vi.fn(), visit: vi.fn() }));
vi.mock('@inertiajs/svelte', () => ({
    usePage: () => ({ props: { auth: { user: { id: 7, scope: {} }, staff_inactivity_generation: 'current' } } }),
    router: { visit: hooks.visit },
}));
vi.mock('@/Components/qr', () => ({ QrScanner: (_anchor: unknown, props: { onScan: (value: string) => void }) => { hooks.scan = props.onScan; } }));
vi.mock('@/Components/DocumentTemplate/PrintDocumentsSlot.svelte', () => ({ default: () => null }));
vi.mock('@/Components/Admission/ApplicantLookupSearch.svelte', () => ({ default: () => null }));
import ApplicantScanModal from '../../resources/js/Components/kit/ApplicantScanModal.svelte';

beforeEach(() => { hooks.post.mockReset(); hooks.visit.mockReset(); window.axios = { post: hooks.post } as never; });
afterEach(() => cleanup());
const voucher = () => new URL('/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef', window.location.href).href;

it('detects a voucher without resolving an applicant or replacing the current session', async () => {
    render(ApplicantScanModal, { open: true, general: true, canResolveApplicant: true });
    expect(screen.getByRole('heading', { name: 'Scan QR code' })).toBeTruthy();
    hooks.scan!(voucher());
    expect(await screen.findByText('Staff setup voucher detected')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Open voucher setup' }).getAttribute('href')).toBe(voucher());
    expect(screen.getByText(/Redeeming this voucher will sign you out/)).toBeTruthy();
    expect(hooks.post).not.toHaveBeenCalled();
    expect(hooks.visit).not.toHaveBeenCalled();
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel voucher' }));
    expect(screen.queryByRole('link', { name: 'Open voucher setup' })).toBeNull();
});

it('retains server-authorized applicant resolution from the general scanner', async () => {
    hooks.post.mockResolvedValue({ data: { scan_result: { state: 'resolved' }, scanned_attempt: { id: 9, name: 'QA Applicant', reference_code: 'AY26-00009' }, destinations: [{ key: 'application', label: 'Application', url: '/registrar/admissions/9' }], default_destination: { key: 'application' }, requires_choice: false } });
    render(ApplicantScanModal, { open: true, general: true, canResolveApplicant: true });
    hooks.scan!('AY26-00009');
    await waitFor(() => expect(hooks.visit).toHaveBeenCalledWith('/registrar/admissions/9'));
    expect(hooks.post).toHaveBeenCalledWith('/staff/scan/resolve', { code: 'AY26-00009' });
});

it('does not expose or call applicant lookup for voucher-only staff', async () => {
    render(ApplicantScanModal, { open: true, general: true, canResolveApplicant: false });
    expect(screen.queryByRole('tab', { name: 'Name' })).toBeNull();
    expect(screen.queryByRole('tab', { name: 'Reference' })).toBeNull();
    hooks.scan!('AY26-00009');
    expect(await screen.findByText(/Applicant lookup is not available/)).toBeTruthy();
    expect(hooks.post).not.toHaveBeenCalled();
});

it('rejects vouchers in contextual applicant actions and rejects external QR navigation', async () => {
    render(ApplicantScanModal, { open: true, general: false, canResolveApplicant: true, preselect: { key: 'application', label: 'Open application' } });
    hooks.scan!(voucher());
    expect(await screen.findByText(/Use the general Scan QR code entry/)).toBeTruthy();
    expect(hooks.post).not.toHaveBeenCalled();
    hooks.scan!('https://outside.example/staff/activate#v=ABCDEFGH23&t=0123456789abcdef0123456789abcdef');
    expect(hooks.post).not.toHaveBeenCalled();
    expect(hooks.visit).not.toHaveBeenCalled();
});
