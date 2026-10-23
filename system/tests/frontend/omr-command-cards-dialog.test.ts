// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrCommandCardsDialog from '../../resources/js/Components/Omr/Workbench/OmrCommandCardsDialog.svelte';
import { OMR_QR_COMMAND_PAYLOADS } from '../../resources/js/lib/omrCapturePlugins';

const qr = vi.hoisted(() => ({ toCanvas: vi.fn(async () => undefined) }));
vi.mock('qrcode', () => ({ default: qr }));

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('OMR command-card print dialog', () => {
    it('renders all four semantic payloads and encodes each with a four-module quiet zone', async () => {
        render(OmrCommandCardsDialog, { props: { open: true } });
        await waitFor(() => expect(qr.toCanvas).toHaveBeenCalledTimes(4));

        expect(screen.getByRole('heading', { name: 'Print command cards' })).toBeTruthy();
        expect(screen.getByText('Capture', { selector: 'h3' })).toBeTruthy();
        expect(screen.getByText('Next page', { selector: 'h3' })).toBeTruthy();
        expect(screen.getByText('Next set', { selector: 'h3' })).toBeTruthy();
        expect(screen.getByText('Redo last page', { selector: 'h3' })).toBeTruthy();
        for (const payload of Object.values(OMR_QR_COMMAND_PAYLOADS)) {
            expect(screen.getByText(payload)).toBeTruthy();
            expect(qr.toCanvas).toHaveBeenCalledWith(
                expect.any(HTMLCanvasElement),
                payload,
                expect.objectContaining({ margin: 4, color: { dark: '#000000', light: '#ffffff' } }),
            );
        }
        expect(screen.getAllByText(/not an answer sheet or applicant code/)).toHaveLength(4);
    });

    it('prepares only selected command cards and invalidates the preview when selection changes', async () => {
        const BrowserUrl = URL;
        vi.stubGlobal('URL', class extends BrowserUrl {
            static createObjectURL(): string { return 'blob:command-print-preview'; }
            static revokeObjectURL(): void {}
        });
        vi.spyOn(HTMLCanvasElement.prototype, 'toDataURL').mockReturnValue(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS8sAAAAASUVORK5CYII=',
        );
        render(OmrCommandCardsDialog, {
            props: { open: true, initialSelection: ['CAPTURE'] },
        });
        await fireEvent.click(screen.getByRole('checkbox', { name: 'Include Next page card' }));
        const prepare = screen.getByRole('button', { name: 'Prepare print preview' });
        await waitFor(() => expect(prepare.hasAttribute('disabled')).toBe(false));
        await fireEvent.click(prepare);

        const frame = screen.getByTitle('Printable QR command cards') as HTMLIFrameElement;
        const document = new DOMParser().parseFromString(frame.srcdoc, 'text/html');
        expect([...document.querySelectorAll('article code')].map((node) => node.textContent)).toEqual([
            'NEXIAM:OMR:CMD:1:CAPTURE',
            'NEXIAM:OMR:CMD:1:NEXT_PAGE',
        ]);
        expect(document.querySelector('script')).toBeNull();

        await fireEvent.click(screen.getByRole('checkbox', { name: 'Include Next page card' }));
        expect(screen.queryByTitle('Printable QR command cards')).toBeNull();
        await fireEvent.click(screen.getAllByRole('button', { name: 'Close' })[0]);
        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
