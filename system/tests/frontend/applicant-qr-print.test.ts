// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { downloadApplicantQrPdf, printApplicantQr } from '../../resources/js/lib/applicant-qr-print.js';

afterEach(() => {
  vi.restoreAllMocks();
});

type ApplicantPrintWindow = EventTarget & {
  location: { href: string };
  focus: () => void;
  print: () => void;
};

function createPrintDocument({ hasQrCodes = true, qrCodeCount = 2 } = {}) {
  const printWindow = new EventTarget() as ApplicantPrintWindow;
  printWindow.location = { href: 'about:blank' };
  printWindow.focus = vi.fn();
  printWindow.print = vi.fn();

  const printable = {
    childElementCount: hasQrCodes ? qrCodeCount : 0,
  };
  const printBody = { childElementCount: 1 };
  const printDocument = {
    body: printBody,
    fonts: { ready: Promise.resolve() },
    images: [],
    querySelector: vi.fn(() => hasQrCodes ? printable : null),
  };

  const events = new Map<string, EventListener>();
  let src = '';
  const iframe = {
    style: {} as CSSStyleDeclaration,
    tabIndex: -1,
    contentWindow: printWindow,
    contentDocument: printDocument,
    setAttribute: vi.fn(),
    addEventListener: vi.fn((type: string, listener: EventListener) => events.set(type, listener)),
    removeEventListener: vi.fn((type: string) => events.delete(type)),
    remove: vi.fn(),
    get src() {
      return src;
    },
    set src(value: string) {
      src = value;
      printWindow.location.href = value;
      queueMicrotask(() => events.get('load')?.(new Event('load')));
    },
  };

  return {
    iframe,
    printWindow,
    printDocument,
    documentRef: {
      createElement: vi.fn(() => iframe),
      body: { append: vi.fn(() => events.get('load')?.(new Event('load'))) },
    },
    dispatchAfterPrint: () => printWindow.dispatchEvent(new Event('afterprint')),
  };
}

describe('applicant QR print helper', () => {
  it('loads the authenticated embedded document in a hidden same-origin iframe and prints after assets are ready', async () => {
    const fixture = createPrintDocument();
    const windowRef = {
      location: { href: 'https://nexiam.test/applicant/qr' },
      setTimeout: vi.fn(() => 1),
      clearTimeout: vi.fn(),
    };

    await printApplicantQr('/applicant/qr/print?attempt=7', {
      documentRef: fixture.documentRef as unknown as Document,
      windowRef: windowRef as unknown as Window,
    });

    expect(new URL(fixture.iframe.src).searchParams.get('embedded')).toBe('1');
    expect(new URL(fixture.iframe.src).searchParams.get('attempt')).toBe('7');
    expect(fixture.printWindow.focus).toHaveBeenCalledOnce();
    expect(fixture.printWindow.print).toHaveBeenCalledOnce();
    expect(fixture.iframe.remove).not.toHaveBeenCalled();

    fixture.dispatchAfterPrint();
    expect(fixture.iframe.remove).toHaveBeenCalledOnce();
  });

  it('prints a valid Registrar-owned custom template with one QR code', async () => {
    const fixture = createPrintDocument({ qrCodeCount: 1 });
    const windowRef = {
      location: { href: 'https://nexiam.test/applicant/qr' },
      setTimeout: vi.fn(() => 1),
      clearTimeout: vi.fn(),
    };

    await printApplicantQr('/applicant/qr/print', {
      documentRef: fixture.documentRef as unknown as Document,
      windowRef: windowRef as unknown as Window,
    });

    expect(fixture.printWindow.print).toHaveBeenCalledOnce();
    fixture.dispatchAfterPrint();
    expect(fixture.iframe.remove).toHaveBeenCalledOnce();
  });

  it('rejects incomplete printable content and removes the iframe without invoking print', async () => {
    const fixture = createPrintDocument({ hasQrCodes: false });
    const windowRef = {
      location: { href: 'https://nexiam.test/applicant/qr' },
      setTimeout: vi.fn(() => 1),
      clearTimeout: vi.fn(),
    };

    await expect(printApplicantQr('/applicant/qr/print', {
      documentRef: fixture.documentRef as unknown as Document,
      windowRef: windowRef as unknown as Window,
    })).rejects.toThrow('approved QR document is incomplete');

    expect(fixture.printWindow.print).not.toHaveBeenCalled();
    expect(fixture.iframe.remove).toHaveBeenCalledOnce();
  });

  it('downloads the PDF through a same-origin download link without navigating or opening a tab', () => {
    const link = { href: '', download: '', hidden: false, click: vi.fn(), remove: vi.fn() };
    const documentRef = { createElement: vi.fn(() => link), body: { append: vi.fn() } };

    downloadApplicantQrPdf('/applicant/qr/print?attempt=7', {
      documentRef: documentRef as unknown as Document,
      windowRef: { location: { href: 'https://nexiam.test/applicant/qr' } } as unknown as Window,
    });

    expect(new URL(link.href).searchParams.get('attempt')).toBe('7');
    expect(new URL(link.href).searchParams.get('format')).toBe('pdf');
    expect(link.download).toBe('my-qr-pass.pdf');
    expect(link.click).toHaveBeenCalledOnce();
    expect(link.remove).toHaveBeenCalledOnce();
  });
});
