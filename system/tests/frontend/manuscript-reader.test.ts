// @vitest-environment jsdom
import { createHash } from 'node:crypto';
import { mkdtemp, mkdir, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { resolveAllowedFile } from '../../bin/publish-manuscript.mjs';
import { safeMarkdownHtml } from '../../resources/js/lib/safeMarkdownHtml.js';
import diagramRegistry from '../../resources/manuscript-diagrams.json';

vi.mock('mermaid', () => ({ default: { initialize: vi.fn() } }));

const temporaryDirectories: string[] = [];

const referenceIndexFixture = {
    pages: [
        {
            id: 'pdf-2',
            pdfPage: 2,
            label: 'Printed page 15',
            printedPage: 15,
            group: 'Chapter 1',
            image: 'yuukee/page-002.jpg',
            text: 'The distinctive phrase is visible. <img src=x onerror="window.compromised=true">',
            status: 'pdf-text',
        },
        {
            id: 'pdf-1',
            pdfPage: 1,
            label: 'Printed page 17',
            printedPage: 17,
            group: 'Chapter 2',
            image: 'yuukee/page-001.jpg',
            text: 'short text',
            status: 'unusable-text',
        },
        {
            id: 'pdf-3',
            pdfPage: 3,
            label: 'Uncertain pagination',
            printedPage: null,
            group: 'Unclassified',
            image: 'yuukee/page-003.jpg',
            text: 'A second distinctive phrase.',
            status: 'ocr',
        },
    ],
    missingPages: [16],
    notes: ['Opening page labels do not follow PDF order.'],
};

const readerFixtureDocs = [
    { id: 'chapter-1-introduction', title: 'Chapter One', fileName: 'chapter-one.md', kind: 'section' },
    { id: 'chapter-2-methodology', title: 'Chapter Two', fileName: 'chapter-two.md', kind: 'section' },
];

async function mountManuscriptReader({
    pathname = '/published/index.html',
    storedState,
    referenceIndex = referenceIndexFixture,
    imageRequest,
    markdownResponse,
    artifactResponse,
    generation,
    waitForReaderReady = true,
    figureAssets = {},
}: {
    pathname?: string;
    storedState?: string | Record<string, unknown>;
    referenceIndex?: typeof referenceIndexFixture;
    imageRequest?: (url: URL, options: RequestInit) => Promise<Response>;
    markdownResponse?: (filename: string) => Promise<Response> | Response;
    artifactResponse?: (url: URL, options: RequestInit) => Response;
    generation?: Record<string, unknown>;
    waitForReaderReady?: boolean;
    figureAssets?: Record<string, string>;
} = {}) {
    const template = await readFile(path.resolve('resources/manuscript-reader.html'), 'utf8');
    document.body.innerHTML = template.split('<body>')[1].split('</body>')[0];
    window.history.replaceState({}, '', pathname);
    if (storedState !== undefined) {
        const storageKey = `nexiam:manuscript-reader:v1:${window.location.pathname}`;
        localStorage.setItem(storageKey, typeof storedState === 'string' ? storedState : JSON.stringify(storedState));
    }
    const article = document.querySelector<HTMLElement>('#document-content');
    if (article) article.scrollIntoView = vi.fn();
    const dialog = document.querySelector<HTMLDialogElement>('#reference-dialog');
    if (dialog) {
        Object.defineProperty(dialog, 'showModal', {
            configurable: true,
            value: () => dialog.setAttribute('open', ''),
        });
        Object.defineProperty(dialog, 'close', {
            configurable: true,
            value: () => {
                dialog.removeAttribute('open');
                dialog.dispatchEvent(new Event('close'));
            },
        });
    }
    const manifest = {
        publishedAt: '2026-10-13T00:00:00Z',
        docs: readerFixtureDocs,
        assets: figureAssets,
        ...(generation ? { generation } : {}),
        reference: {
            title: 'YuKee · Original research',
            pdfFile: 'yuukee-original.pdf',
            indexFile: 'yuukee/index.json',
        },
    };
    const markdownByFilename: Record<string, string> = {
        'chapter-one.md': '# Chapter One\n\nFirst section text.',
        'chapter-two.md': '# Chapter Two\n\nThe phrase appears twice: phrase.',
    };
    const fetchMock = vi.fn(async (input: RequestInfo | URL, options: RequestInit = {}) => {
        const url = new URL(String(input), window.location.href);
        if (url.pathname.endsWith('/manifest.json')) {
            return new Response(JSON.stringify(manifest), { headers: { 'content-type': 'application/json' } });
        }
        if (url.pathname.endsWith('/yuukee/index.json')) {
            return new Response(JSON.stringify(referenceIndex), { headers: { 'content-type': 'application/json' } });
        }
        if (/\/yuukee\/page-\d+\.jpg$/.test(url.pathname)) {
            return imageRequest
                ? imageRequest(url, options)
                : new Response('scan bytes', { headers: { 'content-type': 'image/jpeg' } });
        }
        if (url.pathname.includes('/api/dev/manuscript-annotations')) {
            return new Response(JSON.stringify({ annotations: [] }), { headers: { 'content-type': 'application/json' } });
        }
        if (/\/generation\/NEXIAM-(?:formatting-trial-\d{2,}|slice-\d{2,}-[a-z][a-z0-9-]*)\.(?:pdf|docx)$/.test(url.pathname)) {
            return artifactResponse?.(url, options) ?? new Response('', { status: 404 });
        }
        const filename = url.pathname.split('/').at(-1) ?? '';
        if (markdownByFilename[filename]) {
            return markdownResponse
                ? await markdownResponse(filename)
                : new Response(markdownByFilename[filename], { headers: { 'content-type': 'text/markdown' } });
        }
        return new Response('', { status: 404 });
    });
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('URL', class extends URL {
        static createObjectURL = vi.fn(() => 'blob:reader-test');
        static revokeObjectURL = vi.fn();
    });
    vi.resetModules();
    vi.doMock('../../resources/js/lib/safeMarkdownHtml.js', () => ({ safeMarkdownHtml }));
    // The reader binds DOM listeners during module evaluation; each isolated DOM fixture needs a fresh module instance.
    await import('../../resources/js/manuscript-reader.js');
    if (waitForReaderReady) {
        await vi.waitFor(() => {
            const article = document.querySelector<HTMLElement>('#document-content');
            expect(article?.querySelector('h1') || article?.textContent?.includes('Unable to load')).toBeTruthy();
        });
        await vi.waitFor(() => {
            expect(document.querySelector('#document-content')?.hasAttribute('aria-busy')).toBe(false);
        });
    }
    return fetchMock;
}

afterEach(async () => {
    await Promise.all(temporaryDirectories.splice(0).map((directory) => rm(directory, { recursive: true, force: true })));
    document.querySelector<HTMLDialogElement>('#reference-dialog[open]')?.close();
    document.body.removeAttribute('style');
    document.documentElement.removeAttribute('style');
    document.body.replaceChildren();
    localStorage.clear();
    window.history.replaceState({}, '', '/');
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('manuscript publication safety', () => {
    it('sanitizes executable markup, unsafe links, and remote resource loading', () => {
        const output = safeMarkdownHtml([
            '<script>window.compromised = true</script>',
            '<img src="https://tracker.invalid/pixel" onerror="window.compromised = true">',
            '<a href="javascript:window.compromised=true" onclick="window.compromised=true">unsafe link</a>',
            '<a href="https://example.com/reference">safe reference</a>',
        ].join('\n\n'));
        const container = document.createElement('div');
        container.innerHTML = output;

        expect(container.querySelector('script, img')).toBeNull();
        expect(container.querySelector('[onerror], [onclick]')).toBeNull();
        expect(container.querySelector('a[href^="javascript:"]')).toBeNull();
        expect(container.querySelector('a[href="https://example.com/reference"]')).not.toBeNull();
        expect(container.innerHTML).not.toContain('tracker.invalid');
    });

    it('renders the approved manuscript figure and removes executable image attributes', () => {
        const fileName = 'figure_3_2_current_state_flowchart.png';
        const source = `../../V2_RESOURCES/figures/${fileName}`;
        const output = safeMarkdownHtml([
            `![Reported admission workflow](${source})`,
            `<img src="${source}" onerror="window.compromised=true" srcset="https://tracker.invalid/pixel 2x">`,
        ].join('\n\n'), { [source]: fileName });
        const container = document.createElement('div');
        container.innerHTML = output;

        expect(container.querySelector('img')?.getAttribute('src')).toBe(fileName);
        expect(container.querySelector('img')?.getAttribute('alt')).toBe('Reported admission workflow');
        expect(container.querySelector('[onerror], [srcset]')).toBeNull();
        expect(container.innerHTML).not.toContain('tracker.invalid');
    });

    it('renders registered D2 diagrams without allowing forged remote or unregistered images', () => {
        const asset = diagramRegistry.assets[0];
        const source = `../../${asset.source}`;
        const remote = `https://tracker.invalid/${asset.fileName}`;
        const unknown = 'unregistered-domain-model.png';
        const container = document.createElement('div');
        container.innerHTML = safeMarkdownHtml([
            `![Domain model](${source})`,
            `![Remote model](${remote})`,
            `![Unknown model](${unknown})`,
        ].join('\n\n'), { [source]: asset.fileName, [remote]: asset.fileName, [unknown]: unknown });
        expect([...container.querySelectorAll('img')].map((image) => [image.getAttribute('src'), image.getAttribute('alt')]))
            .toEqual([[asset.fileName, 'Domain model']]);
    });

    it('rejects remote, data, traversal, and unknown figures even with a forged asset map', () => {
        const fileName = 'figure_3_1_evidence_to_process_mapping.png';
        const remote = `https://tracker.invalid/${fileName}`;
        const traversal = `../../../private/${fileName}`;
        const unknown = 'figure_3_1_unapproved.png';
        const output = safeMarkdownHtml([
            `![Remote](${remote})`,
            `![Traversal](${traversal})`,
            `![Unknown](V2_RESOURCES/figures/${unknown})`,
            '<img src="data:image/svg+xml,<svg onload=alert(1)></svg>">',
        ].join('\n\n'), {
            [remote]: fileName,
            [traversal]: fileName,
            [`V2_RESOURCES/figures/${unknown}`]: unknown,
        });
        const container = document.createElement('div');
        container.innerHTML = output;

        expect(container.querySelector('img')).toBeNull();
        expect(container.innerHTML).not.toContain('tracker.invalid');
    });

    it('keeps research tables readable without allowing response collection', () => {
        const output = safeMarkdownHtml([
            '# Questionnaire',
            '',
            '| Item | Strongly agree |',
            '| --- | --- |',
            '| The portal is easy to use. | [ ] |',
            '',
            '<form action=\"https://collector.invalid\"><input name=\"rating\"><button>Submit</button></form>',
        ].join('\n'));
        const container = document.createElement('div');
        container.innerHTML = output;

        expect(container.querySelector('h1')?.textContent).toBe('Questionnaire');
        expect(container.querySelector('tbody td')?.textContent).toBe('The portal is easy to use.');
        expect(container.querySelector('form, input, button, select, textarea')).toBeNull();
    });

    it('allows a fixed path within the manuscript root and rejects traversal or escaping symlinks', async () => {
        const root = await mkdtemp(path.join(os.tmpdir(), 'nexiam-manuscript-'));
        temporaryDirectories.push(root);
        const outside = await mkdtemp(path.join(os.tmpdir(), 'nexiam-outside-'));
        temporaryDirectories.push(outside);
        await mkdir(path.join(root, 'sections'));
        await writeFile(path.join(root, 'sections', 'allowed.md'), 'approved text');
        await writeFile(path.join(outside, 'private.md'), 'not publishable');
        await symlink(path.join(outside, 'private.md'), path.join(root, 'sections', 'linked.md'));

        expect(await resolveAllowedFile(root, 'sections/allowed.md')).toBe(path.join(root, 'sections', 'allowed.md'));
        await expect(resolveAllowedFile(root, '../outside/private.md')).rejects.toThrow(/Invalid allowlisted manuscript path/);
        await expect(resolveAllowedFile(root, 'sections/linked.md')).rejects.toThrow(/outside manuscript root/);
    });

    it('reads the manuscript instead of the ngrok gateway interstitial', async () => {
        const template = await readFile(path.resolve('resources/manuscript-reader.html'), 'utf8');
        document.body.innerHTML = template.split('<body>')[1].split('</body>')[0];
        const markdown = '# Source-backed chapter\n\nThe manuscript content belongs here.';
        vi.stubGlobal('fetch', vi.fn(async (input, options) => {
            if (String(input).endsWith('manifest.json')) {
                return new Response(JSON.stringify({
                    publishedAt: '2026-10-05T00:00:00Z',
                    docs: [{ id: 'chapter-2-full', title: 'Methodology', kind: 'chapter', fileName: 'chapter.md' }],
                }), { headers: { 'content-type': 'application/json' } });
            }
            if (new Headers(options?.headers).get('ngrok-skip-browser-warning') !== '1') {
                return new Response('<html><body>Gateway warning</body></html>', {
                    headers: { 'content-type': 'text/html', 'ngrok-error-code': 'ERR_NGROK_6024' },
                });
            }
            return new Response(markdown, { headers: { 'content-type': 'text/markdown' } });
        }));
        // Reload intentionally: a static import initializes the reader before this DOM fixture exists.
        vi.resetModules();
        vi.doMock('../../resources/js/lib/safeMarkdownHtml.js', () => ({ safeMarkdownHtml }));
        await import('../../resources/js/manuscript-reader.js');
        await vi.waitFor(() => {
            expect(document.querySelector('#document-content h1')?.textContent).toBe('Source-backed chapter');
        });
        expect(document.querySelector('#document-content')?.textContent).not.toContain('Gateway warning');
    });
});

    it('keeps the incumbent manuscript reader available when no generation is published', async () => {
        await mountManuscriptReader();

        expect(document.querySelector<HTMLElement>('#generation-panel')?.hidden).toBe(true);
        expect(document.querySelector('#document-content h1')?.textContent).toBe('Chapter One');
        expect(document.querySelector('#manuscript-search-input')).not.toBeNull();
    });

describe('generated manuscript artifacts', () => {
    const pdfBytes = new TextEncoder().encode('%PDF-1.7 test artifact');
    const docxBytes = new TextEncoder().encode('DOCX test artifact');
    const sha256 = (bytes: Uint8Array) => createHash('sha256').update(bytes).digest('hex');
    const generation = (pdfHash = sha256(pdfBytes), pdfSize = pdfBytes.length) => ({
        id: 'formatting-trial-02',
        title: 'Formatting trial 02',
        status: 'Formatting experiment — not final manuscript',
        notice: 'Review copy only.',
        generatedAt: '2026-10-23T12:00:00Z',
        files: {
            pdf: { fileName: 'generation/NEXIAM-formatting-trial-02.pdf', bytes: pdfSize, sha256: pdfHash },
            docx: { fileName: 'generation/NEXIAM-formatting-trial-02.docx', bytes: docxBytes.length, sha256: sha256(docxBytes) },
        },
    });
    const artifactResponse = (bytes = pdfBytes) => () => new Response(bytes, { headers: { 'content-type': 'application/octet-stream' } });

    it('keeps the Drive shortcut hidden when no Drive folder is published', async () => {
        const stylesheet = document.createElement('style');
        stylesheet.textContent = await readFile(path.resolve('resources/css/manuscript-reader.css'), 'utf8');
        document.head.append(stylesheet);

        try {
            await mountManuscriptReader({ generation: generation() });
            const driveLink = document.querySelector<HTMLAnchorElement>('#generation-drive-link');

            expect(driveLink?.hidden).toBe(true);
            expect(driveLink?.getAttribute('href')).toBeNull();
            expect(driveLink && window.getComputedStyle(driveLink).display).toBe('none');
        } finally {
            stylesheet.remove();
        }
    });

    it('integrity-checks a large DOCX assembled from valid byte ranges', async () => {
        const chunkSize = 4 * 1024 * 1024;
        const docxBytes = new Uint8Array(chunkSize + 1);
        docxBytes.set([0x50, 0x4b, 0x03, 0x04]);
        const selectedGeneration = generation();
        selectedGeneration.id = 'slice-07-cumulative-bulk-manuscript';
        selectedGeneration.files.pdf.fileName = 'generation/NEXIAM-slice-07-cumulative-bulk-manuscript.pdf';
        selectedGeneration.files.docx = {
            fileName: 'generation/NEXIAM-slice-07-cumulative-bulk-manuscript.docx',
            bytes: docxBytes.length,
            sha256: sha256(docxBytes),
        };
        const artifactResponse = (_url: URL, options: RequestInit) => {
            const range = new Headers(options.headers).get('Range');
            const match = range?.match(/^bytes=(\d+)-(\d+)$/);
            if (!match) return new Response('', { status: 416 });
            const start = Number(match[1]);
            const end = Number(match[2]);

            return new Response(docxBytes.slice(start, end + 1), {
                status: 206,
                headers: {
                    'content-range': `bytes ${start}-${end}/${docxBytes.length}`,
                    'content-type': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                },
            });
        };
        const fetchMock = await mountManuscriptReader({ generation: selectedGeneration, artifactResponse });

        document.querySelector<HTMLButtonElement>('#generation-download-docx')?.click();
        await vi.waitFor(() => {
            expect(document.querySelector('#generation-status')?.textContent).not.toBe('Preparing DOCX download…');
        });
        const status = document.querySelector('#generation-status')?.textContent;
        const ranges = fetchMock.mock.calls
            .filter(([input]) => String(input).includes('/generation/'))
            .map(([, options]) => new Headers(options?.headers).get('Range'));
        expect(status).toBe('DOCX download started.');

        expect(ranges).toEqual(['bytes=0-4194303', 'bytes=4194304-4194304']);
    });

    it('rejects a large DOCX when the server returns a mismatched byte range', async () => {
        const chunkSize = 4 * 1024 * 1024;
        const docxBytes = new Uint8Array(chunkSize + 1);
        const selectedGeneration = generation();
        selectedGeneration.id = 'slice-07-cumulative-bulk-manuscript';
        selectedGeneration.files.pdf.fileName = 'generation/NEXIAM-slice-07-cumulative-bulk-manuscript.pdf';
        selectedGeneration.files.docx = {
            fileName: 'generation/NEXIAM-slice-07-cumulative-bulk-manuscript.docx',
            bytes: docxBytes.length,
            sha256: sha256(docxBytes),
        };
        const artifactResponse = (_url: URL, options: RequestInit) => {
            const range = new Headers(options.headers).get('Range');
            const match = range?.match(/^bytes=(\d+)-(\d+)$/);
            if (!match) return new Response('', { status: 416 });
            const start = Number(match[1]);
            const end = Number(match[2]);

            return new Response(docxBytes.slice(start, end + 1), {
                status: 206,
                headers: {
                    'content-range': `bytes ${start + 1}-${end + 1}/${docxBytes.length}`,
                    'content-type': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                },
            });
        };
        const fetchMock = await mountManuscriptReader({ generation: selectedGeneration, artifactResponse });

        document.querySelector<HTMLButtonElement>('#generation-download-docx')?.click();
        await vi.waitFor(() => {
            expect(document.querySelector('#generation-status')?.textContent).not.toBe('Preparing DOCX download…');
        });

        expect(document.querySelector('#generation-status')?.textContent).toBe('Unable to download the DOCX file. Check your connection and try again.');
        expect(URL.createObjectURL).not.toHaveBeenCalled();
        expect(fetchMock.mock.calls.filter(([input]) => String(input).includes('/generation/'))).toHaveLength(1);
    });
    it('previews a validated same-origin PDF and closes it without changing selected Markdown', async () => {
        const selectedGeneration = generation();
        selectedGeneration.id = 'slice-07-cumulative-bulk-manuscript';
        selectedGeneration.status = 'Working manuscript slice — not final manuscript';
        selectedGeneration.files.pdf.fileName = 'generation/NEXIAM-slice-07-cumulative-bulk-manuscript.pdf';
        selectedGeneration.files.docx.fileName = 'generation/NEXIAM-slice-07-cumulative-bulk-manuscript.docx';
        await mountManuscriptReader({ generation: selectedGeneration, artifactResponse: artifactResponse() });
        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter One');
        document.querySelector<HTMLButtonElement>('#generation-preview-button')?.click();
        await vi.waitFor(() => {
            const previewUrl = new URL(document.querySelector<HTMLIFrameElement>('#generation-pdf-frame')?.getAttribute('src') ?? '', window.location.href);
            expect(previewUrl.origin).toBe(window.location.origin);
            expect(previewUrl.pathname).toBe('/published/generation/NEXIAM-slice-07-cumulative-bulk-manuscript.pdf');
        });
        document.querySelector<HTMLButtonElement>('#generation-close-preview')?.click();
        expect(document.querySelector('#generation-preview')?.hasAttribute('hidden')).toBe(true);
        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter One');
    });

    it.each([
        'https://example.invalid/generation/NEXIAM-formatting-trial-02.pdf',
        '../../../private/NEXIAM-formatting-trial-02.pdf',
        'generation/NEXIAM-formatting-trial-03.pdf',
    ])('hides generation with an unsafe or mismatched PDF path (%s) without requesting it', async (fileName) => {
        const unsafeGeneration = generation();
        unsafeGeneration.files.pdf.fileName = fileName;
        const fetchMock = await mountManuscriptReader({ generation: unsafeGeneration });
        expect(document.querySelector<HTMLElement>('#generation-panel')?.hidden).toBe(true);
        expect(fetchMock.mock.calls.some(([input]) => String(input).includes('formatting-trial-02.pdf'))).toBe(false);
    });

    it('rejects a same-size corrupted artifact, blocks download, and allows a valid preview retry', async () => {
        let responseBytes = pdfBytes.slice();
        responseBytes[responseBytes.length - 1] ^= 1;
        const fetchMock = await mountManuscriptReader({
            generation: generation(),
            artifactResponse: () => new Response(responseBytes),
        });
        document.querySelector<HTMLButtonElement>('#generation-download-pdf')?.click();
        await vi.waitFor(() => expect(document.querySelector('#generation-status')?.textContent).toContain('Unable to download'));
        document.querySelector<HTMLButtonElement>('#generation-preview-button')?.click();
        await vi.waitFor(() => expect(document.querySelector('#generation-preview-error')?.hasAttribute('hidden')).toBe(false));
        expect(document.querySelector('#generation-pdf-frame')?.getAttribute('src')).toBeNull();
        responseBytes = pdfBytes;
        document.querySelector<HTMLButtonElement>('#generation-preview-button')?.click();
        await vi.waitFor(() => {
            const previewUrl = new URL(document.querySelector<HTMLIFrameElement>('#generation-pdf-frame')?.getAttribute('src') ?? '', window.location.href);
            expect(previewUrl.origin).toBe(window.location.origin);
            expect(previewUrl.pathname).toBe('/published/generation/NEXIAM-formatting-trial-02.pdf');
        });
        expect(fetchMock.mock.calls.filter(([input]) => String(input).includes('/generation/')).length).toBe(3);
    });

    it('rejects an artifact whose declared byte size does not match the response', async () => {
        const fetchMock = await mountManuscriptReader({
            generation: generation(sha256(pdfBytes), pdfBytes.length + 1),
            artifactResponse: artifactResponse(),
        });
        document.querySelector<HTMLButtonElement>('#generation-download-pdf')?.click();
        await vi.waitFor(() => expect(document.querySelector('#generation-status')?.textContent).toContain('Unable to download'));
        expect(fetchMock.mock.calls.filter(([input]) => String(input).includes('/generation/'))).toHaveLength(1);
    });
});

describe('manuscript reader comparison and search', () => {
    it('uses a valid document URL over saved selection and restores the per-document reading position', async () => {
        const scrollTo = vi.fn();
        vi.stubGlobal('scrollTo', scrollTo);
        const fetchMock = await mountManuscriptReader({
            pathname: '/reading-copy/index.html?document=chapter-2-methodology',
            storedState: {
                documentId: 'chapter-1-introduction',
                documentPositions: {
                    'chapter-1-introduction': 120,
                    'chapter-2-methodology': 420,
                },
                referencePageId: 'pdf-3',
            },
        });

        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter Two');
        expect(scrollTo).toHaveBeenCalledWith(0, 420);
        expect(document.querySelector('#reference-navigation-group')?.hidden).toBe(false);
        document.querySelector<HTMLButtonElement>('#reference-open-btn')?.click();
        await vi.waitFor(() => {
            expect(document.querySelector<HTMLSelectElement>('#reference-page-select')?.value).toBe('pdf-3');
        });
        expect(document.querySelector('#reference-page-metadata')?.textContent).toContain('PDF page 3');
        expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith('/yuukee/index.json'))).toBe(true);
    });

    it('does not overwrite a saved reading position when the selected Markdown fails to load', async () => {
        await mountManuscriptReader({
            pathname: '/failed-copy/index.html?document=chapter-2-methodology',
            storedState: {
                documentId: 'chapter-1-introduction',
                documentPositions: {
                    'chapter-1-introduction': 70,
                    'chapter-2-methodology': 910,
                },
                referencePageId: 'pdf-3',
            },
            markdownResponse: () => new Response('temporarily unavailable', { status: 503 }),
        });

        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter Two');
        expect(document.querySelector('#document-content')?.textContent).toContain('Unable to load Chapter Two as Markdown');
        const savedState = JSON.parse(localStorage.getItem('nexiam:manuscript-reader:v1:/failed-copy/index.html') ?? '{}');
        expect(savedState.documentId).toBe('chapter-1-introduction');
        expect(savedState.documentPositions['chapter-2-methodology']).toBe(910);
    });

    it('applies a pending section scroll restoration while the reference dialog is open', async () => {
        let releaseMarkdown: (response: Response) => void = () => {};
        const pendingMarkdown = new Promise<Response>((resolve) => {
            releaseMarkdown = resolve;
        });
        const scrollTo = vi.fn((_left: number, top: number) => {
            vi.stubGlobal('scrollY', top);
        });
        vi.stubGlobal('scrollY', 0);
        vi.stubGlobal('scrollTo', scrollTo);
        const fetchMock = await mountManuscriptReader({
            pathname: '/pending-copy/index.html?document=chapter-2-methodology',
            storedState: {
                documentId: 'chapter-1-introduction',
                documentPositions: { 'chapter-2-methodology': 910 },
            },
            markdownResponse: () => pendingMarkdown,
            waitForReaderReady: false,
        });
        await vi.waitFor(() => expect(document.querySelector('#reference-navigation-group')?.hidden).toBe(false));
        await vi.waitFor(() => {
            expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith('/chapter-two.md'))).toBe(true);
        });

        document.querySelector<HTMLButtonElement>('#reference-open-btn')?.click();
        const dialog = document.querySelector<HTMLDialogElement>('#reference-dialog');
        const article = document.querySelector<HTMLElement>('#document-content');
        if (!dialog || !article) throw new Error('The reader or reference dialog was not rendered.');
        await vi.waitFor(() => expect(dialog.open).toBe(true));
        await vi.waitFor(() => {
            expect(document.querySelector<HTMLSelectElement>('#reference-page-select')?.options).toHaveLength(3);
        });
        releaseMarkdown(new Response('# Chapter Two\n\nThe restored section is ready.', {
            headers: { 'content-type': 'text/markdown' },
        }));
        await vi.waitFor(() => expect(article.querySelector('h1')?.textContent).toBe('Chapter Two'));
        await vi.waitFor(() => expect(article.hasAttribute('aria-busy')).toBe(false));

        expect(document.body.style.position).toBe('fixed');
        expect(document.body.style.top).toBe('-910px');
        document.querySelector<HTMLButtonElement>('#reference-close-btn')?.click();
        expect(document.body.style.position).toBe('');
        expect(scrollTo).toHaveBeenCalledWith(0, 910);
        const savedState = JSON.parse(localStorage.getItem('nexiam:manuscript-reader:v1:/pending-copy/index.html') ?? '{}');
        expect(savedState.documentPositions['chapter-2-methodology']).toBe(910);
    });

    it('reserves approved manuscript figure dimensions before lazy images decode', async () => {
        const figureFile = 'figure_3_1_evidence_to_process_mapping.png';
        const figureSource = `../../V2_RESOURCES/figures/${figureFile}`;
        await mountManuscriptReader({
            pathname: '/figure-layout/index.html',
            figureAssets: { [figureSource]: figureFile },
            markdownResponse: () => new Response(
                `# Chapter One\n\n![Evidence mapping](${figureSource})`,
                { headers: { 'content-type': 'text/markdown' } },
            ),
        });

        const image = document.querySelector<HTMLImageElement>('#document-content img');
        expect(image?.getAttribute('width')).toBe('888');
        expect(image?.getAttribute('height')).toBe('1810');
        expect(image?.style.width).toBe('888px');
    });

    it('keeps section selection synchronized through browser Back and Forward', async () => {
        await mountManuscriptReader({ pathname: '/history-copy/index.html' });
        const secondSection = document.querySelector<HTMLAnchorElement>('a[data-document-id="chapter-2-methodology"]');
        if (!secondSection) throw new Error('The second manuscript section was not rendered.');
        secondSection.click();
        await vi.waitFor(() => expect(document.querySelector('#document-title')?.textContent).toBe('Chapter Two'));
        expect(window.location.search).toContain('chapter-2-methodology');

        window.history.back();
        await vi.waitFor(() => expect(window.location.search).toBe(''));
        await vi.waitFor(() => expect(document.querySelector('#document-title')?.textContent).toBe('Chapter One'));

        window.history.forward();
        await vi.waitFor(() => expect(window.location.search).toContain('chapter-2-methodology'));
        await vi.waitFor(() => expect(document.querySelector('#document-title')?.textContent).toBe('Chapter Two'));
    });

    it('falls back to the default section for unknown stored IDs and keeps reading available with corrupt or blocked storage', async () => {
        await mountManuscriptReader({
            storedState: {
                documentId: 'removed-document',
                documentPositions: { 'removed-document': 800 },
                referencePageId: 'removed-page',
            },
        });
        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter One');

        document.body.replaceChildren();
        localStorage.setItem('nexiam:manuscript-reader:v1:/published/index.html', '{corrupt');
        await mountManuscriptReader();
        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter One');

        document.body.replaceChildren();
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new DOMException('Storage access blocked.');
        });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('Storage access blocked.');
        });
        await mountManuscriptReader({ pathname: '/blocked-storage/index.html' });
        expect(document.querySelector('#document-content h1')?.textContent).toBe('Chapter One');
        const search = document.querySelector<HTMLInputElement>('#manuscript-search-input');
        if (!search) throw new Error('Manuscript Find input was not rendered.');
        search.value = 'section';
        search.dispatchEvent(new Event('input', { bubbles: true }));
        expect(document.querySelector('#manuscript-search-count')?.textContent).toBe('1 of 1 matches');
    });

    it('highlights visible manuscript text without wrapping review pins or disturbing annotations and diagrams', async () => {
        await mountManuscriptReader({ pathname: '/searchable-copy/index.html?document=chapter-2-methodology' });
        const content = document.querySelector<HTMLElement>('#document-content');
        const search = document.querySelector<HTMLInputElement>('#manuscript-search-input');
        if (!content || !search) throw new Error('Manuscript search surface was not rendered.');

        const annotation = document.createElement('span');
        annotation.className = 'annotation-highlight';
        annotation.textContent = 'phrase in a saved review note';
        const diagram = document.createElement('div');
        diagram.className = 'mermaid-diagram-card';
        diagram.textContent = 'phrase inside a diagram';
        const pin = document.createElement('button');
        pin.className = 'annotation-pin';
        pin.textContent = 'phrase note pin';
        content.append(annotation, diagram, pin);

        search.value = 'PHRASE';
        search.dispatchEvent(new Event('input', { bubbles: true }));
        expect(document.querySelector('#manuscript-search-count')?.textContent).toBe('1 of 3 matches');
        expect(content.querySelectorAll('[data-reader-search-highlight]')).toHaveLength(3);
        expect(annotation.querySelector('[data-reader-search-highlight]')?.textContent).toBe('phrase');
        expect(diagram.querySelector('[data-reader-search-highlight]')).toBeNull();
        expect(pin.isConnected).toBe(true);
        document.querySelector<HTMLButtonElement>('#manuscript-search-next')?.click();
        expect(document.querySelector('#manuscript-search-count')?.textContent).toBe('2 of 3 matches');

        search.value = '';
        search.dispatchEvent(new Event('input', { bubbles: true }));
        expect(annotation.classList.contains('annotation-highlight')).toBe(true);
        expect(annotation.querySelector('[data-reader-search-highlight]')).toBeNull();
        expect(diagram.textContent).toBe('phrase inside a diagram');
        expect(pin.isConnected).toBe(true);
    });

    it('searches reference text across original PDF positions and reports unavailable OCR and verified pagination gaps', async () => {
        await mountManuscriptReader({ pathname: '/reference-search/index.html' });
        document.querySelector<HTMLButtonElement>('#reference-open-btn')?.click();
        const pageSelect = document.querySelector<HTMLSelectElement>('#reference-page-select');
        await vi.waitFor(() => expect(pageSelect?.options).toHaveLength(3));

        expect(pageSelect?.value).toBe('pdf-2');
        expect(document.querySelector('#reference-missing-pages-note')?.textContent).toContain('16');
        expect(document.querySelector('#reference-index-notes')?.textContent).toContain('Opening page labels do not follow PDF order.');
        expect(document.querySelector('#reference-page-metadata')?.textContent).toContain('PDF page 2');
        expect(document.querySelector('#reference-page-metadata')?.textContent).toContain('Printed page 15');

        const search = document.querySelector<HTMLInputElement>('#reference-search-input');
        if (!search || !pageSelect) throw new Error('Reference search surface was not rendered.');
        search.value = 'distinctive phrase';
        search.dispatchEvent(new Event('input', { bubbles: true }));
        expect(document.querySelector('#reference-search-count')?.textContent).toContain('1 of 2 matches');
        expect(document.querySelector('#reference-page-text')?.querySelector('img, script')).toBeNull();
        expect(document.querySelector('#reference-page-text .reader-search-highlight')?.textContent).toBe('distinctive phrase');

        document.querySelector<HTMLButtonElement>('#reference-search-next')?.click();
        expect(pageSelect.value).toBe('pdf-3');
        expect(document.querySelector('#reference-page-metadata')?.textContent).toContain('PDF page 3');
        expect(document.querySelector('#reference-page-metadata')?.textContent).toContain('Printed page unverified');
        expect(document.querySelector('#reference-page-metadata')?.textContent).toContain('Unclassified');
        expect(document.querySelector('#reference-search-count')?.textContent).toContain('2 of 2 matches');
        expect(document.querySelector('#reference-page-text .reader-search-highlight')?.textContent).toBe('distinctive phrase');

        document.querySelector<HTMLButtonElement>('#reference-search-previous')?.click();
        expect(pageSelect.value).toBe('pdf-2');
        pageSelect.value = 'pdf-1';
        pageSelect.dispatchEvent(new Event('change', { bubbles: true }));
        expect(document.querySelector('#reference-text-status')?.textContent).toContain('Search unavailable for this page');
        expect(document.querySelector('#reference-search-count')?.textContent).toContain('no usable text to search');
        const savedState = JSON.parse(localStorage.getItem('nexiam:manuscript-reader:v1:/reference-search/index.html') ?? '{}');
        expect(savedState.referencePageId).toBe('pdf-1');
    });

    it('restores focus after closing the native comparison dialog and routes Ctrl/Cmd+F to the active surface', async () => {
        const scrollTo = vi.fn();
        vi.stubGlobal('scrollTo', scrollTo);
        vi.stubGlobal('scrollY', 275);
        await mountManuscriptReader({ pathname: '/focus-copy/index.html' });
        const trigger = document.querySelector<HTMLButtonElement>('#reference-open-btn');
        const dialog = document.querySelector<HTMLDialogElement>('#reference-dialog');
        const referenceSearch = document.querySelector<HTMLInputElement>('#reference-search-input');
        const manuscriptSearch = document.querySelector<HTMLInputElement>('#manuscript-search-input');
        const content = document.querySelector<HTMLElement>('#document-content');
        if (!trigger || !dialog || !referenceSearch || !manuscriptSearch || !content) {
            throw new Error('Reader controls were not rendered.');
        }
        trigger.click();
        await vi.waitFor(() => expect(dialog.open).toBe(true));
        expect(document.body.style.position).toBe('fixed');
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'f', ctrlKey: true, bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(referenceSearch);
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        expect(dialog.open).toBe(false);
        expect(document.body.style.position).toBe('');
        expect(scrollTo).toHaveBeenCalledWith(0, 275);
        expect(document.activeElement).toBe(trigger);
        expect(document.querySelector('#document-title')?.textContent).toBe('Chapter One');
        const savedState = JSON.parse(localStorage.getItem('nexiam:manuscript-reader:v1:/focus-copy/index.html') ?? '{}');
        expect(savedState.documentPositions['chapter-1-introduction']).toBe(275);

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'f', metaKey: true, bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(manuscriptSearch);
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(content);
    });

    it('rejects reference assets outside the publication and offers retry after a failed scan request', async () => {
        const unsafeIndex = {
            ...referenceIndexFixture,
            pages: referenceIndexFixture.pages.map((page, index) => index === 0
                ? { ...page, image: '../outside/page.jpg' }
                : page),
        };
        const unsafeFetch = await mountManuscriptReader({
            pathname: '/bounded-reference/index.html',
            referenceIndex: unsafeIndex,
        });
        document.querySelector<HTMLButtonElement>('#reference-open-btn')?.click();
        await vi.waitFor(() => {
            expect(document.querySelector('#reference-load-error')?.hidden).toBe(false);
        });
        expect(document.querySelector('#reference-load-error-message')?.textContent).toContain('outside the publication');
        expect(unsafeFetch.mock.calls.some(([input]) => /\/yuukee\/page-\d+\.jpg$/.test(String(input)))).toBe(false);

        document.body.replaceChildren();
        localStorage.clear();
        let attempts = 0;
        const retryFetch = await mountManuscriptReader({
            pathname: '/retry-reference/index.html',
            imageRequest: async () => {
                attempts += 1;
                if (attempts === 1) return new Response('unavailable', { status: 503 });
                return new Response('scan bytes', { headers: { 'content-type': 'image/jpeg' } });
            },
        });
        document.querySelector<HTMLButtonElement>('#reference-open-btn')?.click();
        await vi.waitFor(() => expect(document.querySelector('#reference-image-error')?.hidden).toBe(false));
        const imageRequests = retryFetch.mock.calls.filter(([input]) => /\/yuukee\/page-\d+\.jpg$/.test(String(input)));
        expect(new Headers(imageRequests[0]?.[1]?.headers).get('ngrok-skip-browser-warning')).toBe('1');
        document.querySelector<HTMLButtonElement>('#reference-retry-image')?.click();
        await vi.waitFor(() => {
            expect(document.querySelector<HTMLImageElement>('#reference-page-image')?.hidden, document.querySelector('#reference-image-error-message')?.textContent ?? '').toBe(false);
        });
        expect(attempts).toBe(2);
    });
});
