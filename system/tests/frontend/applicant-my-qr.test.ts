// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';

const qrActions = vi.hoisted(() => ({
  printApplicantQr: vi.fn(),
  downloadApplicantQrPdf: vi.fn(),
}));

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
  default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));
vi.mock('qrcode', () => ({ default: { toCanvas: vi.fn(() => Promise.resolve()) } }));
vi.mock('@/lib/applicant-qr-print.js', () => qrActions);

import MyQr from '../../resources/js/Pages/Applicant/MyQr.svelte';

afterEach(() => {
  cleanup();
  vi.clearAllMocks();
});

function renderMyQr() {
  return render(MyQr, {
    props: {
      page_state: 'ready',
      attempt_id: 7,
      qr: 'own-applicant-locator',
      print_url: '/applicant/qr/print?attempt=7',
    },
  });
}

describe('My QR actions', () => {
  it('starts native print from the print action and disables it while the document is being prepared', async () => {
    let resolvePrint: (() => void) | undefined;
    qrActions.printApplicantQr.mockImplementation(() => new Promise<void>((resolve) => {
      resolvePrint = resolve;
    }));
    renderMyQr();

    const printButton = screen.getByRole('button', { name: 'Print Pass' });
    await fireEvent.click(printButton);

    expect(qrActions.printApplicantQr).toHaveBeenCalledWith('/applicant/qr/print?attempt=7');
    expect(screen.getByRole('button', { name: 'Preparing print…' }).hasAttribute('disabled')).toBe(true);

    resolvePrint?.();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Print Pass' }).hasAttribute('disabled')).toBe(false));
  });

  it('shows a recoverable print error and permits a later retry', async () => {
    qrActions.printApplicantQr
      .mockRejectedValueOnce(new Error('The QR document took too long to load.'))
      .mockResolvedValueOnce(undefined);
    renderMyQr();

    await fireEvent.click(screen.getByRole('button', { name: 'Print Pass' }));
    expect((await screen.findByRole('alert')).textContent).toContain('The QR document took too long to load.');

    await fireEvent.click(screen.getByRole('button', { name: 'Print Pass' }));
    await waitFor(() => expect(qrActions.printApplicantQr).toHaveBeenCalledTimes(2));
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('starts a PDF download without a new-window action', async () => {
    renderMyQr();

    await fireEvent.click(screen.getByRole('button', { name: 'Download PDF' }));

    expect(qrActions.downloadApplicantQrPdf).toHaveBeenCalledWith('/applicant/qr/print?attempt=7');
    expect(qrActions.printApplicantQr).not.toHaveBeenCalled();
  });
});
