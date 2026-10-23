// @vitest-environment jsdom
import { expect, it } from 'vitest';
import { noticePreviewDocument } from '../../resources/js/lib/noticePreviewDocument';

it('renders preview HTML in a CSP-restricted document without links or scripts', () => {
    const production = '<html><head><style>@media(max-width:600px){p{font-size:18px}}</style></head><body><p>Actual renderer</p><a href="https://example.test">Open</a><img src="data:image/png;base64,AA=="><script>throw 1</script></body></html>';
    const projected = new DOMParser().parseFromString(noticePreviewDocument(production), 'text/html');

    expect(projected.querySelector('meta[http-equiv="Content-Security-Policy"]')?.getAttribute('content')).toContain("default-src 'none'");
    expect(projected.querySelector('a')?.hasAttribute('href')).toBe(false);
    expect(projected.querySelector('script')).toBeNull();
    expect(projected.querySelector('style')?.textContent).toContain('@media');
    expect(projected.querySelector('img')?.getAttribute('src')).toBe('data:image/png;base64,AA==');
    expect(production).toContain('href="https://example.test"');
});
