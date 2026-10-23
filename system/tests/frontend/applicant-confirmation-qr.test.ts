// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const qrActions = vi.hoisted(() => ({
    printApplicantQr: vi.fn(),
    downloadApplicantQrPdf: vi.fn(),
}));
const portalVisit = vi.hoisted(() => vi.fn());
vi.mock('@/lib/applicant-qr-print.js', () => qrActions);
vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal()),
    router: { post: portalVisit },
    Link: () => null,
}));
vi.mock('@/Layouts/GuestLayout.svelte', () => ({
    default: (anchor, { children }) => children?.(anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({
    default: () => null,
}));

import Confirmation from '../../resources/js/Pages/Admission/Confirmation.svelte';
import QRCode from 'qrcode';

let pixels;

beforeEach(() => {
    pixels = undefined;
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({
        createImageData: (width, height) => ({
            data: new Uint8ClampedArray(width * height * 4),
            width,
            height,
        }),
        clearRect: () => {},
        putImageData: (image) => {
            pixels = image;
        },
    });
});
afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

async function expectEncodedQr(url) {
    await waitFor(() => expect(pixels?.width).toBe(176));
    const image = pixels;
    const qr = QRCode.create(url);
    const scale = image.width / (qr.modules.size + 4);
    for (let row = 0; row < qr.modules.size; row++) {
        for (let col = 0; col < qr.modules.size; col++) {
            const x = Math.floor((col + 2.5) * scale);
            const y = Math.floor((row + 2.5) * scale);
            const offset = (y * image.width + x) * 4;
            expect(Array.from(image.data.slice(offset, offset + 4))).toEqual(
                qr.modules.get(row, col)
                    ? [0, 0, 0, 255]
                    : [255, 255, 255, 255],
            );
        }
    }
}

it('renders a sign-in QR without replaying credentials after a direct visit or reload', async () => {
    render(Confirmation);
    await fireEvent.click(
        screen.getByRole('button', { name: 'View QR Code & Printable Slip' }),
    );
    await expectEncodedQr(`${window.location.origin}/applicant/login`);
    expect(screen.getByText(/saved access key and birthdate/i)).toBeTruthy();
    expect(screen.queryByLabelText('Access Key')).toBeNull();
    await fireEvent.click(
        screen.getByRole('button', { name: 'View QR Code & Printable Slip' }),
    );
    pixels = undefined;
    await fireEvent.click(
        screen.getByRole('button', { name: 'View QR Code & Printable Slip' }),
    );
    expect(
        screen
            .getByRole('button', { name: 'View QR Code & Printable Slip' })
            .getAttribute('aria-expanded'),
    ).toBe('true');
    await expectEncodedQr(`${window.location.origin}/applicant/login`);
});

it('preserves the supplied one-time credential QR rather than replacing it with generic sign-in', async () => {
    const qrUrl =
        'https://admissions.example.test/applicant/login#v=synthetic-voucher&k=TEST-ABCD';
    render(Confirmation, {
        props: {
            access_key: 'TEST-ABCD',
            credential_handoff: {
                qr_url: qrUrl,
                voucher_token: 'synthetic-voucher',
            },
        },
    });
    await fireEvent.click(
        screen.getByRole('button', { name: 'View QR Code & Printable Slip' }),
    );
    await expectEncodedQr(qrUrl);
    expect(screen.queryByText(/saved access key and birthdate/i)).toBeNull();
});

it('enters the portal through the session-bound action without a voucher prop or a second sign-in form', async () => {
    render(Confirmation, {
        props: {
            portal_entry_error:
                'This browser is signed in to a different account.',
        },
    });
    expect(screen.getByRole('alert').textContent).toBe(
        'This browser is signed in to a different account.',
    );
    expect(screen.queryByText('Sign in to applicant portal')).toBeNull();
    await fireEvent.click(
        screen.getByRole('button', { name: /Proceed to Applicant Portal/i }),
    );
    expect(portalVisit).toHaveBeenCalledWith(
        '/apply/confirmation/enter',
        {},
        expect.any(Object),
    );
});

it('renders document template preview iframe and triggers print and download actions', async () => {
    const printUrl =
        '/apply/confirmation/print?reference=APP-2026-00012&hash=mockhash';
    render(Confirmation, {
        props: {
            reference_code: 'APP-2026-00012',
            print_url: printUrl,
        },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'View QR Code & Printable Slip' }),
    );

    // Iframe preview rendered
    const iframe = screen.getByTitle(
        'Official Admission Slip Document Preview',
    );
    expect(iframe.getAttribute('src')).toBe(`${printUrl}&embedded=1`);

    // Print action triggers printApplicantQr
    const printBtn = screen.getByRole('button', {
        name: 'Print Admission Slip',
    });
    await fireEvent.click(printBtn);
    expect(qrActions.printApplicantQr).toHaveBeenCalledWith(printUrl);

    // Download PDF triggers downloadApplicantQrPdf
    const downloadBtn = screen.getByRole('button', { name: 'Download PDF' });
    await fireEvent.click(downloadBtn);
    expect(qrActions.downloadApplicantQrPdf).toHaveBeenCalledWith(printUrl);
});
