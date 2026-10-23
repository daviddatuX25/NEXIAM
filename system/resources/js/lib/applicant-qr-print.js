const PRINT_LOAD_TIMEOUT_MS = 20_000;
const PRINT_FRAME_CLEANUP_FALLBACK_MS = 60_000;

function buildApplicantQrUrl(url, currentUrl, { embedded = false, format = null } = {}) {
  const target = new URL(url, currentUrl);

  if (target.origin !== new URL(currentUrl).origin) {
    throw new Error('The applicant QR document must stay on this site.');
  }

  if (embedded) {
    target.searchParams.set('embedded', '1');
  }

  if (format) {
    target.searchParams.set('format', format);
  }

  return target;
}

function waitForImage(image) {
  if (image.complete) {
    return image.naturalWidth > 0
      ? Promise.resolve()
      : Promise.reject(new Error('A QR document image could not be loaded.'));
  }

  return new Promise((resolve, reject) => {
    image.addEventListener('load', resolve, { once: true });
    image.addEventListener('error', () => reject(new Error('A QR document image could not be loaded.')), {
      once: true,
    });
  }).then(() => {
    if (typeof image.decode === 'function') {
      return image.decode();
    }
  });
}

async function waitForDocumentAssets(printDocument) {
  if (!printDocument?.body || !printDocument.body.childElementCount) {
    throw new Error('The QR document was empty. Please try again.');
  }

  const printable = printDocument.querySelector('[data-applicant-qr-print]');
  if (!printable || !printable.childElementCount) {
    throw new Error('The approved QR document is incomplete. Please try again.');
  }

  if (printDocument.fonts?.ready) {
    await printDocument.fonts.ready;
  }

  await Promise.all(Array.from(printDocument.images ?? [], waitForImage));
}

/** Load an authenticated, same-origin QR HTML document in a hidden iframe and print it. */
export function printApplicantQr(url, {
  documentRef = document,
  windowRef = window,
  loadTimeoutMs = PRINT_LOAD_TIMEOUT_MS,
  cleanupFallbackMs = PRINT_FRAME_CLEANUP_FALLBACK_MS,
} = {}) {
  const target = buildApplicantQrUrl(url, windowRef.location.href, { embedded: true });
  const iframe = documentRef.createElement('iframe');
  iframe.setAttribute('title', 'Applicant QR print document');
  iframe.setAttribute('aria-hidden', 'true');
  iframe.tabIndex = -1;
  iframe.style.position = 'fixed';
  iframe.style.width = '1px';
  iframe.style.height = '1px';
  iframe.style.left = '-10000px';
  iframe.style.bottom = '0';
  iframe.style.border = '0';

  return new Promise((resolve, reject) => {
    let loadTimer;
    let cleanupTimer;
    let settled = false;

    const removeFrame = () => {
      windowRef.clearTimeout(cleanupTimer);
      iframe.removeEventListener('load', onLoad);
      iframe.removeEventListener('error', onError);
      iframe.contentWindow?.removeEventListener('afterprint', onAfterPrint);
      iframe.remove();
    };

    const fail = (error) => {
      if (settled) return;
      settled = true;
      windowRef.clearTimeout(loadTimer);
      removeFrame();
      reject(error instanceof Error ? error : new Error('The QR document could not be loaded. Please try again.'));
    };

    const onAfterPrint = () => removeFrame();

    const onError = () => fail(new Error('The QR document could not be loaded. Please try again.'));

    const onLoad = async () => {
      if (settled) return;

      try {
        const frameWindow = iframe.contentWindow;
        if (frameWindow.location.href === 'about:blank') return;
        const loadedUrl = new URL(frameWindow.location.href);
        if (loadedUrl.origin !== target.origin || loadedUrl.pathname !== target.pathname) {
          throw new Error('Your session may have expired. Sign in again, then retry printing.');
        }

        const printDocument = iframe.contentDocument;
        await waitForDocumentAssets(printDocument);
        if (settled) return;

        windowRef.clearTimeout(loadTimer);
        frameWindow.addEventListener('afterprint', onAfterPrint, { once: true });
        cleanupTimer = windowRef.setTimeout(removeFrame, cleanupFallbackMs);
        frameWindow.focus?.();
        frameWindow.print();
        settled = true;
        resolve();
      } catch (error) {
        fail(error);
      }
    };

    iframe.addEventListener('load', onLoad);
    iframe.addEventListener('error', onError);
    documentRef.body.append(iframe);
    loadTimer = windowRef.setTimeout(() => {
      fail(new Error('The QR document took too long to load. Check your connection and try again.'));
    }, loadTimeoutMs);
    iframe.src = target.toString();
  });
}

/** Start a same-tab download without navigating or opening another browsing context. */
export function downloadApplicantQrPdf(url, {
  documentRef = document,
  windowRef = window,
} = {}) {
  const target = buildApplicantQrUrl(url, windowRef.location.href, { format: 'pdf' });
  const link = documentRef.createElement('a');
  link.href = target.toString();
  link.download = 'my-qr-pass.pdf';
  link.hidden = true;
  documentRef.body.append(link);
  link.click();
  link.remove();
}
