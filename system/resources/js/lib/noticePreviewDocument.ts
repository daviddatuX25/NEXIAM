export const NOTICE_PREVIEW_CSP = "default-src 'none'; img-src data:; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; navigate-to 'none'";

/** CSP is first in the document, before any renderer-owned markup can load resources. */
export function noticePreviewDocument(html: string): string {
    let head = '';
    let body = html;

    if (typeof DOMParser !== 'undefined') {
        const document = new DOMParser().parseFromString(html, 'text/html');

        for (const link of document.querySelectorAll('a,area')) {
            link.removeAttribute('href');
            link.removeAttribute('target');
        }

        for (const node of document.querySelectorAll('script,base,meta[http-equiv="refresh"]')) {
            node.remove();
        }

        head = document.head.innerHTML;
        body = document.body.innerHTML;
    }

    return `<!doctype html><html><head><meta http-equiv="Content-Security-Policy" content="${NOTICE_PREVIEW_CSP}"><meta name="referrer" content="no-referrer">${head}</head><body>${body}</body></html>`;
}
