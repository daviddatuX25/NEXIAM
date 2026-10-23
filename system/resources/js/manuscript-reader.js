import mermaid from 'mermaid';
import { safeMarkdownHtml } from './lib/safeMarkdownHtml.js';

if (typeof window !== 'undefined' && typeof mermaid?.initialize === 'function') {
    try {
        mermaid.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            theme: 'neutral',
            flowchart: { htmlLabels: false },
        });
    } catch {
        // Safe fallback in minimal test environments without full SVG canvas support
    }
}

const manifestUrl = new URL('manifest.json', window.location.href);
const navigation = document.querySelector('#document-navigation');
window.addEventListener('resize', () => closeDiffPopover());
const selector = document.querySelector('#document-selector');
const title = document.querySelector('#document-title');
const content = document.querySelector('#document-content');
const publishedAt = document.querySelector('#publication-time');
const selectedDownload = document.querySelector('#download-selected');
const chapterDownloads = document.querySelector('#chapter-download-links');
const outline = document.querySelector('#heading-outline');
let documentRequestId = 0;
let activeDocument = null;
let publishedDocs = [];
const downloadStatus = document.querySelector('#download-status');
const requestOptions = {
    credentials: 'omit',
    cache: 'no-store',
    headers: { 'ngrok-skip-browser-warning': '1' },
};
const ARTIFACT_RANGE_CHUNK_BYTES = 4 * 1024 * 1024;
const markdownRequests = new Map();
let figureAssets = {};
const figureImageDimensions = {
    'figure_3_1_evidence_to_process_mapping.png': { width: 888, height: 1810 },
    'figure_3_2_current_state_flowchart.png': { width: 1714, height: 2462 },
};
const manuscriptSearchInput = document.querySelector('#manuscript-search-input');
const manuscriptSearchPrevious = document.querySelector('#manuscript-search-previous');
const manuscriptSearchNext = document.querySelector('#manuscript-search-next');
const manuscriptSearchCount = document.querySelector('#manuscript-search-count');
const referenceNavigationGroup = document.querySelector('#reference-navigation-group');
const referenceOpenButton = document.querySelector('#reference-open-btn');
const referenceDialog = document.querySelector('#reference-dialog');
const referenceCloseButton = document.querySelector('#reference-close-btn');
const referenceLoadStatus = document.querySelector('#reference-load-status');
const referenceLoadError = document.querySelector('#reference-load-error');
const referenceLoadErrorMessage = document.querySelector('#reference-load-error-message');
const referenceRetryIndexButton = document.querySelector('#reference-retry-index');
const referenceIndexNotes = document.querySelector('#reference-index-notes');
const referenceMissingPagesNote = document.querySelector('#reference-missing-pages-note');
const referenceNotesList = document.querySelector('#reference-notes-list');
const referencePageSelect = document.querySelector('#reference-page-select');
const referencePagePrevious = document.querySelector('#reference-page-previous');
const referencePageNext = document.querySelector('#reference-page-next');
const referenceZoomOut = document.querySelector('#reference-zoom-out');
const referenceZoomIn = document.querySelector('#reference-zoom-in');
const referenceZoomLevel = document.querySelector('#reference-zoom-level');
const referencePdfDownload = document.querySelector('#reference-pdf-download');
const referenceDownloadStatus = document.querySelector('#reference-download-status');
const referencePageMetadata = document.querySelector('#reference-page-metadata');
const referenceImageLoading = document.querySelector('#reference-image-loading');
const referencePageImage = document.querySelector('#reference-page-image');
const referenceImageError = document.querySelector('#reference-image-error');
const referenceImageErrorMessage = document.querySelector('#reference-image-error-message');
const referenceRetryImageButton = document.querySelector('#reference-retry-image');
const referenceSearchInput = document.querySelector('#reference-search-input');
const referenceSearchPrevious = document.querySelector('#reference-search-previous');
const referenceSearchNext = document.querySelector('#reference-search-next');
const referenceSearchCount = document.querySelector('#reference-search-count');
const referenceTextStatus = document.querySelector('#reference-text-status');
const referencePageText = document.querySelector('#reference-page-text');
let readerStorageKey = `nexiam:manuscript-reader:v1:${window.location.pathname}`;
let readerState = { documentId: null, documentPositions: {}, referencePageId: null };
let documentPositionReady = false;
let positionSaveTimer = null;
let manuscriptMatches = [];
let manuscriptMatchIndex = -1;
let referenceConfiguration = null;
let referenceIndex = null;
let referencePageIndex = -1;
let referenceMatches = [];
let referenceMatchIndex = -1;
let referenceIndexPromise = null;
let referenceImageController = null;
let referenceImageRequestId = 0;
let referenceImageObjectUrl = '';
let referenceImagePath = '';
let referenceZoom = 100;
let referenceReturnScrollPosition = null;
let referenceScrollLockStyles = null;

const generationPanel = document.querySelector('#generation-panel');
const generationNotice = document.querySelector('#generation-notice');
const generationMetadata = document.querySelector('#generation-metadata');
const generationStatus = document.querySelector('#generation-status');
const generationPreview = document.querySelector('#generation-preview');
const generationFrame = document.querySelector('#generation-pdf-frame');
const generationPreviewError = document.querySelector('#generation-preview-error');
const generationOpenPdf = document.querySelector('#generation-open-pdf');
const generationDriveLink = document.querySelector('#generation-drive-link');
let generationArtifacts = null;
let generationPreviewRequest = 0;

function generationFileUrl(file, extension, generationId) {
    if (typeof file?.fileName !== 'string' || file.fileName !== `generation/NEXIAM-${generationId}.${extension}`
        || !Number.isSafeInteger(file.bytes) || file.bytes <= 0
        || typeof file.sha256 !== 'string' || !/^[a-f0-9]{64}$/i.test(file.sha256)) {
        return null;
    }
    const url = new URL(file.fileName.split('/').map(encodeURIComponent).join('/'), window.location.href);
    if (url.origin !== window.location.origin || url.pathname !== `${window.location.pathname.replace(/[^/]*$/, '')}${file.fileName}`) return null;
    return url;
}

function setupGeneration(generation) {
    if (!generation || typeof generation !== 'object'
        || typeof generation.id !== 'string' || !/^(?:formatting-trial-\d{2,}|slice-\d{2,}-[a-z][a-z0-9-]*)$/.test(generation.id)
        || !['Formatting experiment — not final manuscript', 'Working manuscript slice — not final manuscript'].includes(generation.status)) return;
    const pdf = generationFileUrl(generation.files?.pdf, 'pdf', generation.id);
    const docx = generationFileUrl(generation.files?.docx, 'docx', generation.id);
    if (!pdf || !docx || typeof generation.title !== 'string' || typeof generation.notice !== 'string'
        || typeof generation.generatedAt !== 'string' || Number.isNaN(Date.parse(generation.generatedAt))) return;

    generationArtifacts = { pdf, docx, files: generation.files };
    generationPanel.hidden = false;
    generationNotice.textContent = generation.notice;
    generationMetadata.textContent = `${generation.title} · Generated ${new Date(generation.generatedAt).toLocaleString()} · PDF ${generation.files.pdf.bytes.toLocaleString()} bytes · Word ${generation.files.docx.bytes.toLocaleString()} bytes`;
    const drive = generation.drive;
    if (drive?.status === 'uploaded' && typeof drive.folderName === 'string'
        && typeof drive.folderUrl === 'string'
        && /^https:\/\/drive\.google\.com\/drive\/folders\/[A-Za-z0-9_-]+$/.test(drive.folderUrl)) {
        generationDriveLink.href = drive.folderUrl;
        generationDriveLink.textContent = `Open Drive review folder: ${drive.folderName}`;
        generationDriveLink.hidden = false;
    }
    const previewButton = document.querySelector('#generation-preview-button');
    const closeButton = document.querySelector('#generation-close-preview');
    previewButton.addEventListener('click', () => {
        const requestId = ++generationPreviewRequest;
        generationPreview.hidden = false;
        generationPreviewError.hidden = true;
        generationPreviewError.textContent = '';
        generationFrame.hidden = true;
        generationFrame.removeAttribute('src');
        generationOpenPdf.href = pdf.href;
        generationStatus.textContent = 'Loading PDF preview…';
        void fetchArtifact(generation.files.pdf, pdf, 'application/pdf').then(() => {
            if (requestId !== generationPreviewRequest) return;
            generationFrame.src = pdf.href;
            generationFrame.hidden = false;
            generationStatus.textContent = 'PDF preview ready.';
        }).catch(() => {
            if (requestId !== generationPreviewRequest) return;
            generationPreviewError.textContent = 'The PDF preview could not be loaded. Check your connection and try again, or open the PDF separately.';
            generationPreviewError.hidden = false;
            generationStatus.textContent = 'PDF preview unavailable.';
        });
    });
    closeButton.addEventListener('click', () => {
        generationPreviewRequest += 1;
        generationFrame.removeAttribute('src');
        generationFrame.hidden = true;
        generationPreview.hidden = true;
        generationStatus.textContent = '';
    });
    document.querySelector('#generation-download-pdf').addEventListener('click', () => void downloadArtifact('pdf'));
    document.querySelector('#generation-download-docx').addEventListener('click', () => void downloadArtifact('docx'));
}

async function fetchArtifact(file, url, contentType) {
    const blob = file.bytes > ARTIFACT_RANGE_CHUNK_BYTES
        ? await fetchArtifactRanges(file, url, contentType)
        : await fetchArtifactWhole(url);
    if (blob.size !== file.bytes) throw new Error('Artifact size mismatch.');
    const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
    const hash = [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    if (hash.toLowerCase() !== file.sha256.toLowerCase()) throw new Error('Artifact integrity check failed.');
    return blob.type === contentType ? blob : new Blob([blob], { type: contentType });
}

async function fetchArtifactWhole(url) {
    const response = await fetch(url, requestOptions);
    if (!response.ok) throw new Error('Artifact request failed.');
    return response.blob();
}

async function fetchArtifactRanges(file, url, contentType) {
    const chunks = [];
    for (let start = 0; start < file.bytes; start += ARTIFACT_RANGE_CHUNK_BYTES) {
        const end = Math.min(start + ARTIFACT_RANGE_CHUNK_BYTES - 1, file.bytes - 1);
        const response = await fetch(url, {
            ...requestOptions,
            headers: { ...requestOptions.headers, Range: `bytes=${start}-${end}` },
        });
        const expectedContentRange = `bytes ${start}-${end}/${file.bytes}`;
        if (!response.ok || response.status !== 206 || response.headers.get('content-range') !== expectedContentRange) {
            throw new Error('Artifact range request failed.');
        }
        const chunk = await response.arrayBuffer();
        if (chunk.byteLength !== end - start + 1) throw new Error('Artifact range size mismatch.');
        chunks.push(chunk);
    }
    return new Blob(chunks, { type: contentType });
}

async function downloadArtifact(kind) {
    if (!generationArtifacts) return;
    const file = generationArtifacts.files[kind];
    const url = generationArtifacts[kind];
    generationStatus.textContent = `Preparing ${kind.toUpperCase()} download…`;
    try {
        const blob = await fetchArtifact(file, url, kind === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        downloadBlob(blob, file.fileName.split('/').at(-1));
        generationStatus.textContent = `${kind.toUpperCase()} download started.`;
    } catch {
        generationStatus.textContent = `Unable to download the ${kind.toUpperCase()} file. Check your connection and try again.`;
    }
}

// Annotation state
let currentAnnotations = [];
let pendingQuote = '';
let pendingHeadingId = '';
const annotationsToggleBtn = document.querySelector('#annotations-toggle-btn');
const annotationsBadge = document.querySelector('#annotations-badge');
const annotationsDrawer = document.querySelector('#annotations-drawer');
const annotationsCloseBtn = document.querySelector('#annotations-close-btn');
const annotationsCreateBtn = document.querySelector('#annotations-create-btn');
const annotationsList = document.querySelector('#annotations-list');
const annotationPopover = document.querySelector('#annotation-popover');
const popoverCreateBtn = document.querySelector('#popover-create-btn');
const annotationDialog = document.querySelector('#annotation-dialog');
const annotationDialogForm = document.querySelector('#annotation-dialog-form');
const annotationQuoteBox = document.querySelector('#annotation-quote-box');
const annotationAuthorInput = document.querySelector('#annotation-author-input');
const annotationCategorySelect = document.querySelector('#annotation-category-select');
const annotationCommentInput = document.querySelector('#annotation-comment-input');
const annotationCancelBtn = document.querySelector('#annotation-cancel-btn');

function loadMarkdown(doc) {
    if (!markdownRequests.has(doc.fileName)) {
        const request = fetch(new URL(encodeURIComponent(doc.fileName), window.location.href), requestOptions)
            .then((response) => {
                if (!response.ok || response.headers.get('content-type')?.includes('text/html')) {
                    throw new Error(`Unable to load ${doc.title} as Markdown. Reload the published reader.`);
                }
                return response.text();
            });
        markdownRequests.set(doc.fileName, request);
    }
    return markdownRequests.get(doc.fileName);
}

async function downloadMarkdown(doc) {
    downloadStatus.textContent = 'Preparing download…';
    try {
        const markdown = await loadMarkdown(doc);
        const url = URL.createObjectURL(new Blob([markdown], { type: 'text/markdown;charset=utf-8' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = doc.fileName;
        document.body.append(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        downloadStatus.textContent = 'Download started.';
    } catch (error) {
        downloadStatus.textContent = error.message;
    }
}

function decoratePlaceholders() {
    const walk = document.createTreeWalker(content, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walk.nextNode()) {
        if (/\[(?:(?:FIGURE|PLATE|TABLE)\s+(?:[A-Z]\.)?[\d.]+|EVIDENCE):/i.test(walk.currentNode.textContent)) {
            nodes.push(walk.currentNode);
        }
    }
    for (const node of nodes) {
        const fragment = document.createDocumentFragment();
        const expression = /(\[(?:(?:FIGURE|PLATE|TABLE)\s+(?:[A-Z]\.)?[\d.]+|EVIDENCE):[^\]]+\])/gi;
        let start = 0;
        for (const match of node.textContent.matchAll(expression)) {
            fragment.append(document.createTextNode(node.textContent.slice(start, match.index)));
            const marker = document.createElement('span');
            marker.className = 'placeholder-marker';
            marker.textContent = match[0];
            fragment.append(marker);
            start = match.index + match[0].length;
        }
        fragment.append(document.createTextNode(node.textContent.slice(start)));
        node.replaceWith(fragment);
    }
    content.querySelectorAll('blockquote').forEach((quote) => {
        if (/review draft|prepared \d{4}-\d{2}-\d{2}|slot metadata|working note/i.test(quote.textContent)) {
            quote.classList.add('metadata-note');
        }
    });
    const publicationBuildHash = new URLSearchParams(document.querySelector('script[src*="reader.js"]')?.getAttribute('src')?.split('?')[1] || '').get('v');
    content.querySelectorAll('img').forEach((image) => {
        const dimensions = figureImageDimensions[image.getAttribute('src')];
        if (dimensions) {
            image.width = dimensions.width;
            image.style.width = `${dimensions.width}px`;
            image.height = dimensions.height;
            image.style.aspectRatio = `${dimensions.width} / ${dimensions.height}`;
        }
        if (publicationBuildHash && !image.src.includes('?v=')) {
            const separator = image.src.includes('?') ? '&' : '?';
            image.src = `${image.src}${separator}v=${publicationBuildHash}`;
        }
        const link = document.createElement('a');
        link.href = image.src;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        link.setAttribute('aria-label', `Open figure at full size: ${image.alt}`);
        image.replaceWith(link);
        link.append(image);
    });
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function exportDiagramSvg(svgEl, filename) {
    if (!svgEl) return;
    const serializer = new XMLSerializer();
    let source = serializer.serializeToString(svgEl);
    if (!source.match(/^<svg[^>]+xmlns="http:\/\/www\.w3\.org\/2000\/svg"/)) {
        source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
    }
    const blob = new Blob([source], { type: 'image/svg+xml;charset=utf-8' });
    downloadBlob(blob, filename);
}

function exportDiagramPng(svgEl, filename) {
    if (!svgEl) return;
    const serializer = new XMLSerializer();
    let source = serializer.serializeToString(svgEl);
    if (!source.match(/^<svg[^>]+xmlns="http:\/\/www\.w3\.org\/2000\/svg"/)) {
        source = source.replace(/^<svg/, '<svg xmlns="http://www.w3.org/2000/svg"');
    }
    const encoded = btoa(unescape(encodeURIComponent(source)));
    const dataUrl = `data:image/svg+xml;base64,${encoded}`;

    const img = new Image();
    img.onload = () => {
        const scale = 2;
        const box = svgEl.viewBox?.baseVal;
        const width = (box && box.width > 0 ? box.width : (svgEl.clientWidth || 800)) * scale;
        const height = (box && box.height > 0 ? box.height : (svgEl.clientHeight || 600)) * scale;

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (ctx) {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.drawImage(img, 0, 0, width, height);
            canvas.toBlob((blob) => {
                if (blob) downloadBlob(blob, filename);
            }, 'image/png');
        }
    };
    img.onerror = () => {
        exportDiagramSvg(svgEl, filename.replace(/\.png$/, '.svg'));
    };
    img.src = dataUrl;
}

let diagramIdCounter = 0;

async function renderMermaidDiagrams(docId) {
    if (typeof mermaid?.render !== 'function') {
        return;
    }
    const codeBlocks = [...content.querySelectorAll('pre > code.language-mermaid')];
    for (const [index, code] of codeBlocks.entries()) {
        const pre = code.parentElement;
        const source = code.textContent.trim();
        const diagramId = `mermaid-${docId.replace(/[^a-zA-Z0-9_-]/g, '_')}-${++diagramIdCounter}-${index}`;

        const card = document.createElement('figure');
        card.className = 'mermaid-diagram-card';

        const toolbar = document.createElement('div');
        toolbar.className = 'diagram-toolbar';

        const titleSpan = document.createElement('span');
        titleSpan.className = 'diagram-toolbar-title';
        titleSpan.textContent = `Mermaid Diagram · ${docId}`;
        toolbar.append(titleSpan);

        const actions = document.createElement('div');
        actions.className = 'diagram-toolbar-actions';

        const svgContainer = document.createElement('div');
        svgContainer.className = 'diagram-svg-container';

        try {
            const { svg } = await mermaid.render(diagramId, source);
            svgContainer.innerHTML = svg;
            const svgEl = svgContainer.querySelector('svg');

            const btnExportSvg = document.createElement('button');
            btnExportSvg.type = 'button';
            btnExportSvg.className = 'diagram-action-btn';
            btnExportSvg.textContent = '📥 Export SVG';
            btnExportSvg.title = 'Download vector SVG image';
            btnExportSvg.onclick = () => exportDiagramSvg(svgEl, `${diagramId}.svg`);
            actions.append(btnExportSvg);

            const btnExportPng = document.createElement('button');
            btnExportPng.type = 'button';
            btnExportPng.className = 'diagram-action-btn';
            btnExportPng.textContent = '🖼️ Export PNG';
            btnExportPng.title = 'Download 2x high-resolution PNG image';
            btnExportPng.onclick = () => exportDiagramPng(svgEl, `${diagramId}.png`);
            actions.append(btnExportPng);

            const details = document.createElement('details');
            details.className = 'diagram-source-details';
            const summary = document.createElement('summary');
            summary.textContent = 'View Diagram Source Code';
            details.append(summary, pre.cloneNode(true));

            toolbar.append(actions);
            card.append(toolbar, svgContainer, details);
            pre.replaceWith(card);
        } catch (error) {
            const errDiv = document.createElement('div');
            errDiv.className = 'diagram-render-error';
            errDiv.textContent = `Mermaid Diagram Syntax Notice: ${error.message}`;
            pre.before(errDiv);
        }
    }
}

function buildOutline() {
    outline.replaceChildren();
    const headings = [...content.querySelectorAll('h2, h3, h4')];
    headings.forEach((heading, index) => {
        const id = `section-${index + 1}`;
        heading.id = id;
        const item = document.createElement('li');
        item.className = `outline-level-${heading.tagName.toLowerCase()}`;
        const link = document.createElement('a');
        link.href = `#${id}`;
        link.textContent = heading.textContent;
        item.append(link);
        outline.append(item);
    });
    outline.closest('.outline').hidden = headings.length === 0;
}

function setSelectedNavigation(docId) {
    navigation.querySelectorAll('[data-document-id]').forEach((link) => {
        if (link.dataset.documentId === docId) {
            link.setAttribute('aria-current', 'page');
        } else {
            link.removeAttribute('aria-current');
        }
    });
    selector.value = docId;
}

// ==========================================
// Annotations Manager (Team & Agent Feedback)
// ==========================================

async function fetchAnnotations(docId) {
    try {
        const response = await fetch(`/api/dev/manuscript-annotations?document_id=${encodeURIComponent(docId)}`, {
            headers: { 'Accept': 'application/json' },
            cache: 'no-cache',
        });
        if (response.ok) {
            const data = await response.json();
            if (Array.isArray(data.annotations)) {
                try {
                    localStorage.setItem(`manuscript_ann_${docId}`, JSON.stringify(data.annotations));
                } catch { /* storage full */ }
                return data.annotations;
            }
        }
    } catch {
        // Fallback to local cache if dev server or network unreachable
    }

    try {
        const cached = localStorage.getItem(`manuscript_ann_${docId}`);
        return cached ? JSON.parse(cached) : [];
    } catch {
        return [];
    }
}

async function persistAnnotation(payload) {
    try {
        const response = await fetch('/api/dev/manuscript-annotations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (response.ok) {
            return await response.json();
        }
    } catch {
        // Local fallback
    }

    const fallbackRecord = {
        id: `ann_local_${Date.now()}`,
        ...payload,
        status: 'open',
        replies: [],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
    };
    return fallbackRecord;
}

async function updateAnnotationStatus(id, newStatus) {
    try {
        await fetch(`/api/dev/manuscript-annotations/${encodeURIComponent(id)}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ status: newStatus }),
        });
    } catch { /* local */ }
}

async function removeAnnotation(id) {
    try {
        await fetch(`/api/dev/manuscript-annotations/${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' },
        });
    } catch { /* local */ }
}

function updateAnnotationsBadge() {
    if (!annotationsBadge) return;
    const openCount = currentAnnotations.filter((a) => a.status !== 'resolved' && a.status !== 'dismissed').length;
    annotationsBadge.textContent = String(openCount);
    annotationsBadge.style.display = openCount > 0 ? 'inline-flex' : 'none';
}

function renderAnnotationsDrawer() {
    if (!annotationsList) return;
    annotationsList.replaceChildren();

    if (!currentAnnotations.length) {
        const p = document.createElement('p');
        p.className = 'empty-notes-notice';
        p.textContent = 'No annotations on this document yet. Select text or click "+ Add Note" to create one.';
        annotationsList.append(p);
        updateAnnotationsBadge();
        return;
    }

    for (const ann of currentAnnotations) {
        const item = document.createElement('article');
        item.className = 'annotation-item';
        item.dataset.annotationId = ann.id;

        const header = document.createElement('div');
        header.className = 'annotation-item-header';

        const authorSpan = document.createElement('span');
        authorSpan.className = 'annotation-author';
        authorSpan.textContent = ann.author || 'Reviewer';

        const catBadge = document.createElement('span');
        catBadge.className = `annotation-badge-category cat-${ann.category || 'todo'}`;
        catBadge.textContent = ann.category || 'todo';

        const timeSpan = document.createElement('span');
        timeSpan.className = 'annotation-time';
        timeSpan.textContent = ann.created_at ? new Date(ann.created_at).toLocaleDateString() : '';

        header.append(authorSpan, catBadge, timeSpan);

        item.append(header);

        if (ann.quote) {
            const quoteEl = document.createElement('blockquote');
            quoteEl.className = 'annotation-quote';
            quoteEl.textContent = `“${ann.quote}”`;
            item.append(quoteEl);
        }

        const body = document.createElement('p');
        body.className = 'annotation-body';
        body.textContent = ann.comment;
        item.append(body);

        const actions = document.createElement('div');
        actions.className = 'annotation-actions';

        if (ann.status === 'open') {
            const resolveBtn = document.createElement('button');
            resolveBtn.type = 'button';
            resolveBtn.className = 'annotation-action-btn';
            resolveBtn.textContent = '✓ Resolve';
            resolveBtn.onclick = async () => {
                ann.status = 'resolved';
                await updateAnnotationStatus(ann.id, 'resolved');
                renderAnnotationsDrawer();
                renderDocumentPins();
            };
            actions.append(resolveBtn);
        } else {
            const reopenBtn = document.createElement('button');
            reopenBtn.type = 'button';
            reopenBtn.className = 'annotation-action-btn';
            reopenBtn.textContent = '↺ Reopen';
            reopenBtn.onclick = async () => {
                ann.status = 'open';
                await updateAnnotationStatus(ann.id, 'open');
                renderAnnotationsDrawer();
                renderDocumentPins();
            };
            actions.append(reopenBtn);
        }

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'annotation-action-btn';
        deleteBtn.textContent = 'Delete';
        deleteBtn.onclick = async () => {
            currentAnnotations = currentAnnotations.filter((a) => a.id !== ann.id);
            await removeAnnotation(ann.id);
            renderAnnotationsDrawer();
            renderDocumentPins();
        };
        actions.append(deleteBtn);

        item.append(actions);
        annotationsList.append(item);
    }

    updateAnnotationsBadge();
}

function renderDocumentPins() {
    content.querySelectorAll('.annotation-pin').forEach((pin) => pin.remove());

    const activeNotes = currentAnnotations.filter((a) => a.status === 'open');
    if (!activeNotes.length) return;

    const headingMap = new Map();
    for (const note of activeNotes) {
        const key = note.heading_id || 'top';
        headingMap.set(key, (headingMap.get(key) || 0) + 1);
    }

    for (const [headingId, count] of headingMap.entries()) {
        const target = headingId !== 'top' ? content.querySelector(`#${headingId}`) : content.querySelector('h1, h2');
        if (target) {
            const pin = document.createElement('button');
            pin.type = 'button';
            pin.className = 'annotation-pin';
            pin.textContent = `💬 ${count}`;
            pin.title = `${count} open review note(s)`;
            pin.onclick = () => {
                if (annotationsDrawer) {
                    annotationsDrawer.hidden = false;
                    annotationsToggleBtn?.setAttribute('aria-expanded', 'true');
                }
            };
            target.append(pin);
        }
    }
}

function setupAnnotationListeners() {
    annotationsToggleBtn?.addEventListener('click', () => {
        if (!annotationsDrawer) return;
        const willOpen = annotationsDrawer.hidden;
        annotationsDrawer.hidden = !willOpen;
        annotationsToggleBtn.setAttribute('aria-expanded', String(willOpen));
    });

    annotationsCloseBtn?.addEventListener('click', () => {
        if (annotationsDrawer) {
            annotationsDrawer.hidden = true;
            annotationsToggleBtn?.setAttribute('aria-expanded', 'false');
        }
    });

    annotationsCreateBtn?.addEventListener('click', () => {
        pendingQuote = '';
        pendingHeadingId = '';
        if (annotationQuoteBox) {
            annotationQuoteBox.hidden = true;
            annotationQuoteBox.textContent = '';
        }
        if (annotationDialog) {
            annotationDialog.showModal();
        }
    });

    popoverCreateBtn?.addEventListener('click', () => {
        annotationPopover?.setAttribute('hidden', '');
        if (annotationQuoteBox) {
            annotationQuoteBox.hidden = !pendingQuote;
            annotationQuoteBox.textContent = pendingQuote ? `“${pendingQuote}”` : '';
        }
        if (annotationDialog) {
            annotationDialog.showModal();
        }
    });

    annotationCancelBtn?.addEventListener('click', () => {
        annotationDialog?.close();
    });

    annotationDialogForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const author = annotationAuthorInput?.value?.trim() || 'Reviewer';
        const category = annotationCategorySelect?.value || 'todo';
        const comment = annotationCommentInput?.value?.trim();

        if (!comment || !activeDocument) return;

        try {
            localStorage.setItem('manuscript_last_author', author);
        } catch { /* ignore */ }

        const payload = {
            document_id: activeDocument.id,
            author,
            category,
            comment,
            quote: pendingQuote || null,
            heading_id: pendingHeadingId || null,
        };

        const created = await persistAnnotation(payload);
        currentAnnotations.unshift(created);
        renderAnnotationsDrawer();
        renderDocumentPins();

        if (annotationCommentInput) annotationCommentInput.value = '';
        pendingQuote = '';
        pendingHeadingId = '';
        annotationDialog?.close();

        if (annotationsDrawer) {
            annotationsDrawer.hidden = false;
            annotationsToggleBtn?.setAttribute('aria-expanded', 'true');
        }
    });

    // Populate remembered author name if stored
    try {
        const rememberedAuthor = localStorage.getItem('manuscript_last_author');
        if (rememberedAuthor && annotationAuthorInput) {
            annotationAuthorInput.value = rememberedAuthor;
        }
    } catch { /* ignore */ }

    // Text selection listener for popover
    document.addEventListener('selectionchange', () => {
        const selection = window.getSelection();
        if (!selection || selection.isCollapsed) {
            annotationPopover?.setAttribute('hidden', '');
            return;
        }

        const selectedText = selection.toString().trim();
        if (selectedText.length < 3) {
            annotationPopover?.setAttribute('hidden', '');
            return;
        }

        const anchorNode = selection.anchorNode;
        if (!content.contains(anchorNode)) {
            annotationPopover?.setAttribute('hidden', '');
            return;
        }

        // Store selected quote
        pendingQuote = selectedText.slice(0, 300);

        // Find nearest preceding heading
        let currentEl = anchorNode.nodeType === Node.ELEMENT_NODE ? anchorNode : anchorNode.parentElement;
        let headingId = null;
        while (currentEl && currentEl !== content) {
            if (/^H[1-4]$/i.test(currentEl.tagName) && currentEl.id) {
                headingId = currentEl.id;
                break;
            }
            if (currentEl.previousElementSibling) {
                const prevHead = currentEl.previousElementSibling.matches('h1, h2, h3, h4')
                    ? currentEl.previousElementSibling
                    : currentEl.previousElementSibling.querySelector('h1, h2, h3, h4');
                if (prevHead?.id) {
                    headingId = prevHead.id;
                    break;
                }
            }
            currentEl = currentEl.parentElement;
        }
        pendingHeadingId = headingId || '';

        // Position popover
        try {
            const range = selection.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            if (annotationPopover) {
                annotationPopover.style.top = `${window.scrollY + rect.top - 8}px`;
                annotationPopover.style.left = `${window.scrollX + rect.left + rect.width / 2}px`;
                annotationPopover.removeAttribute('hidden');
            }
        } catch {
            annotationPopover?.setAttribute('hidden', '');
        }
    });
}

// ==========================================
// Main Document Rendering Lifecycle
// ==========================================
function normalizeBlockComparisonText(element) {
    return (element.textContent || '')
        .replace(/\bAlternative 1:\s*/gi, '')
        .replace(/\b3\.3[A-D]\b/g, '3.3')
        .replace(/\bSpecific Objective 6\b/gi, 'Specific Objective 3')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

function extractSectionKey(text) {
    const match = (text || '').trim().match(/^(\d+\.\d+[A-D]?)\b/i);
    if (!match) return null;
    const key = match[1].toUpperCase();
    if (key === '3.3D') return '3.3';
    return key;
}

function tokenizeComparisonWords(text) {
    return new Set(
        (text || '')
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, ' ')
            .split(/\s+/)
            .filter((word) => word.length >= 4 && !['this', 'that', 'with', 'from', 'into', 'under', 'their', 'these', 'those', 'which', 'where', 'while'].includes(word)),
    );
}

function wordOverlapScore(textA, textB) {
    const wordsA = tokenizeComparisonWords(textA);
    const wordsB = tokenizeComparisonWords(textB);
    if (!wordsA.size || !wordsB.size) return 0;
    let shared = 0;
    for (const word of wordsA) {
        if (wordsB.has(word)) shared++;
    }
    return shared / Math.min(wordsA.size, wordsB.size);
}

let activeDiffPopover = null;
let activeDiffTrigger = null;

function ensureDiffPopover() {
    let popover = document.querySelector('#alt-diff-popover');
    if (!popover) {
        popover = document.createElement('div');
        popover.id = 'alt-diff-popover';
        popover.className = 'alt-diff-popover';
        popover.hidden = true;
        document.body.append(popover);
        document.addEventListener('click', (event) => {
            if (!popover.hidden && !popover.contains(event.target) && !event.target.closest?.('.alt-info-chip')) {
                closeDiffPopover();
            }
        });
    }
    return popover;
}

function closeDiffPopover() {
    if (activeDiffPopover) {
        activeDiffPopover.hidden = true;
    }
    if (activeDiffTrigger) {
        activeDiffTrigger.setAttribute('aria-expanded', 'false');
        activeDiffTrigger = null;
    }
}

function showDiffPopover(trigger, payload) {
    const popover = ensureDiffPopover();
    activeDiffPopover = popover;
    if (activeDiffTrigger && activeDiffTrigger !== trigger) {
        activeDiffTrigger.setAttribute('aria-expanded', 'false');
    }
    activeDiffTrigger = trigger;
    trigger.setAttribute('aria-expanded', 'true');
    popover.replaceChildren();

    const head = document.createElement('div');
    head.className = 'alt-diff-popover-head';
    const badge = document.createElement('span');
    badge.className = `alt-diff-status-badge status-${payload.status}`;
    badge.textContent = payload.label;
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'alt-diff-popover-close';
    closeBtn.textContent = '×';
    closeBtn.setAttribute('aria-label', 'Close original text comparison');
    closeBtn.onclick = () => closeDiffPopover();
    head.append(badge, closeBtn);

    const body = document.createElement('div');
    body.className = 'alt-diff-popover-body';
    const titleEl = document.createElement('p');
    titleEl.className = 'alt-diff-popover-title';
    titleEl.textContent = payload.heading || 'Original manuscript text:';
    const quoteEl = document.createElement('div');
    quoteEl.className = 'alt-diff-popover-quote';
    quoteEl.textContent = payload.originalText;
    body.append(titleEl, quoteEl);

    popover.append(head, body);
    popover.hidden = false;

    const rect = trigger.getBoundingClientRect();
    const maxLeft = Math.max(12, window.innerWidth - Math.min(440, window.innerWidth - 24) - 12);
    const left = Math.min(Math.max(12, rect.left), maxLeft);
    popover.style.left = `${window.scrollX + left}px`;
    popover.style.top = `${window.scrollY + rect.bottom + 6}px`;
}

function attachDiffInfoChip(target, payload) {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = `alt-info-chip status-${payload.status}`;
    chip.setAttribute('aria-expanded', 'false');
    chip.title = `${payload.heading || 'Original text'}: ${payload.originalText}`;
    chip.textContent = payload.shortLabel || 'ⓘ Original';

    chip.addEventListener('mouseenter', () => {
        if (window.matchMedia('(hover: hover)').matches) {
            showDiffPopover(chip, payload);
        }
    });
    chip.addEventListener('click', (event) => {
        event.stopPropagation();
        if (activeDiffTrigger === chip && activeDiffPopover && !activeDiffPopover.hidden) {
            closeDiffPopover();
        } else {
            showDiffPopover(chip, payload);
        }
    });

    target.append(' ', chip);
}

function collectStructuredOriginalData(root, defaultSectionKey = 'root') {
    const exactBlocks = new Set();
    const sectionMap = new Map();
    const rowMap = new Map();
    let currentKey = defaultSectionKey;

    const ensureSection = (key, headingText = '') => {
        if (!sectionMap.has(key)) {
            sectionMap.set(key, { heading: headingText, paragraphs: [] });
        } else if (headingText && !sectionMap.get(key).heading) {
            sectionMap.get(key).heading = headingText;
        }
        return sectionMap.get(key);
    };
    ensureSection(currentKey);

    for (const el of root.querySelectorAll('h1, h2, h3, h4, p, li, tbody tr, pre, blockquote')) {
        const norm = normalizeBlockComparisonText(el);
        if (norm.length >= 8) {
            exactBlocks.add(`${el.tagName}:${norm}`);
        }
        if (/^H[1-2]$/.test(el.tagName)) {
            const key = extractSectionKey(el.textContent) || currentKey;
            currentKey = key;
            ensureSection(currentKey, el.textContent.trim());
            continue;
        }
        if (el.tagName === 'P' || el.tagName === 'LI') {
            const text = el.textContent.trim();
            if (text.length >= 12) {
                ensureSection(currentKey).paragraphs.push(text);
            }
        } else if (el.tagName === 'TR') {
            const cells = [...el.querySelectorAll('th, td')].map((c) => c.textContent.trim());
            if (cells.length && cells[0]) {
                rowMap.set(cells[0].toLowerCase(), cells);
            }
        }
    }

    return { exactBlocks, sectionMap, rowMap };
}

async function highlightAlternativeChanges(doc, docs) {
    closeDiffPopover();
    const isAlternativeDoc = Boolean(doc.alternative || doc.id.includes('-alternative-'));
    if (!isAlternativeDoc) return;

    const baseId = doc.baseId !== undefined
        ? doc.baseId
        : (doc.id.endsWith('-alternative-1') ? doc.id.replace(/-alternative-1$/, '') : null);
    const baseDoc = baseId ? docs.find((item) => item.id === baseId) : null;

    const defaultKey = extractSectionKey(doc.title) || 'root';
    let originalData = { exactBlocks: new Set(), sectionMap: new Map(), rowMap: new Map() };

    if (baseDoc) {
        try {
            const baseMarkdown = await loadMarkdown(baseDoc);
            const temp = document.createElement('div');
            temp.innerHTML = safeMarkdownHtml(baseMarkdown, figureAssets);
            originalData = collectStructuredOriginalData(temp, defaultKey);
        } catch {
            // Fallback to empty original set if unavailable.
        }
    }

    let currentSectionKey = defaultKey;
    let sectionParagraphIndex = 0;
    let changedCount = 0;

    for (const block of content.querySelectorAll('h2, h3, h4, p, li, tbody tr')) {
        if (
            block.closest('.metadata-note')
            || block.closest('.diagram-toolbar')
            || block.closest('.diagram-source-details')
            || block.closest('.mermaid-diagram-card')
        ) {
            continue;
        }

        if (block.tagName === 'H2') {
            const key = extractSectionKey(block.textContent) || currentSectionKey;
            currentSectionKey = key;
            sectionParagraphIndex = 0;
        }

        const normalized = normalizeBlockComparisonText(block);
        if (normalized.length < 8) continue;
        if (originalData.exactBlocks.has(`${block.tagName}:${normalized}`)) {
            if (block.tagName === 'P' || block.tagName === 'LI') {
                sectionParagraphIndex++;
            }
            continue;
        }

        if (block.tagName === 'TR') {
            const cells = [...block.querySelectorAll('th, td')];
            if (!cells.length) continue;
            const rowKey = (cells[0].textContent || '').trim().toLowerCase();
            const origCells = originalData.rowMap.get(rowKey);
            if (origCells) {
                let rowChanged = false;
                cells.forEach((cell, idx) => {
                    const newVal = cell.textContent.trim();
                    const oldVal = origCells[idx] ?? '';
                    if (oldVal && newVal.toLowerCase() !== oldVal.toLowerCase()) {
                        cell.classList.add('alt-changed-cell');
                        attachDiffInfoChip(cell, {
                            status: 'modified',
                            label: 'Modified cell',
                            shortLabel: 'ⓘ Original',
                            heading: `Original cell (${cells[0].textContent.trim()}):`,
                            originalText: oldVal,
                        });
                        rowChanged = true;
                    }
                });
                if (rowChanged) changedCount++;
            } else {
                const lastCell = cells[cells.length - 1];
                block.classList.add('alt-changed-row');
                attachDiffInfoChip(lastCell, {
                    status: 'added',
                    label: 'New table row',
                    shortLabel: 'ⓘ New row',
                    heading: 'Original table status:',
                    originalText: baseDoc
                        ? `This row ("${cells[0].textContent.trim()}") was added in Alternative 1 and does not appear in ${baseDoc.title}.`
                        : 'This table belongs to a newly added Alternative 1 section.',
                });
                changedCount++;
            }
            continue;
        }

        const origSection = originalData.sectionMap.get(currentSectionKey);
        if (/^H[2-4]$/.test(block.tagName)) {
            block.classList.add('alt-changed-heading');
            if (block.id) {
                outline.querySelector(`a[href="#${block.id}"]`)?.classList.add('outline-changed');
            }
            if (origSection && origSection.paragraphs.length) {
                attachDiffInfoChip(block, {
                    status: 'modified',
                    label: 'Revised section',
                    shortLabel: 'ⓘ Original section',
                    heading: `Original ${origSection.heading || currentSectionKey} text:`,
                    originalText: origSection.paragraphs.join('\n\n'),
                });
            } else {
                attachDiffInfoChip(block, {
                    status: 'added',
                    label: 'Added in Alternative 1',
                    shortLabel: 'ⓘ New section',
                    heading: 'Original manuscript status:',
                    originalText: 'This heading and section were added in Alternative 1 (splitting component evaluation out of SO2/SO3).',
                });
            }
            changedCount++;
            continue;
        }

        if (block.tagName === 'P' || block.tagName === 'LI') {
            let bestOrig = null;
            let bestScore = 0;
            const candidates = origSection?.paragraphs || [];
            for (const cand of candidates) {
                const score = wordOverlapScore(block.textContent, cand);
                if (score > bestScore) {
                    bestScore = score;
                    bestOrig = cand;
                }
            }
            if (!bestOrig && candidates[sectionParagraphIndex]) {
                bestOrig = candidates[sectionParagraphIndex];
            }
            sectionParagraphIndex++;
            block.classList.add('alt-changed-block');
            if (bestOrig) {
                attachDiffInfoChip(block, {
                    status: 'modified',
                    label: 'Revised from original',
                    shortLabel: 'ⓘ Original text',
                    heading: `Original text (${currentSectionKey !== 'root' ? `Section ${currentSectionKey}` : baseDoc?.title || 'Original'}):`,
                    originalText: bestOrig,
                });
            } else {
                attachDiffInfoChip(block, {
                    status: 'added',
                    label: 'Added in Alternative 1',
                    shortLabel: 'ⓘ Added',
                    heading: 'Original manuscript status:',
                    originalText: origSection
                        ? `Added in Alternative 1 beyond the original ${origSection.paragraphs.length}-paragraph section.`
                        : 'Added in Alternative 1 as a new component evaluation passage.',
                });
            }
            changedCount++;
        }
    }

    const banner = document.createElement('div');
    banner.className = 'alt-diff-banner';
    const label = document.createElement('span');
    label.className = 'alt-diff-legend';
    label.textContent = baseDoc
        ? `Comparison markers (ⓘ) show where this alternative differs from ${baseDoc.title} (${changedCount} marked items). Hover or click any ⓘ badge to read the original text.`
        : 'Comparison markers (ⓘ) indicate newly added Alternative 1 component evaluation content. Hover or click any ⓘ badge for details.';
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'alt-diff-toggle';
    toggleBtn.textContent = 'Hide ⓘ markers';
    toggleBtn.onclick = () => {
        closeDiffPopover();
        const hidden = content.classList.toggle('hide-alt-highlights');
        toggleBtn.textContent = hidden ? 'Show ⓘ markers' : 'Hide ⓘ markers';
    };
    banner.append(label, toggleBtn);
    content.prepend(banner);
}


async function showDocument(doc, { position = null, scrollToDocument = false } = {}) {
    const requestId = ++documentRequestId;
    let rendered = false;
    activeDocument = doc;
    documentPositionReady = false;
    title.textContent = doc.title;
    selectedDownload.href = new URL(encodeURIComponent(doc.fileName), window.location.href).href;
    selectedDownload.download = doc.fileName;
    selectedDownload.onclick = (event) => {
        event.preventDefault();
        void downloadMarkdown(doc);
    };
    setSelectedNavigation(doc.id);
    content.classList.remove('hide-alt-highlights');
    content.setAttribute('aria-busy', 'true');
    content.replaceChildren(Object.assign(document.createElement('p'), { textContent: `Loading ${doc.title}…` }));
    outline.replaceChildren();
    outline.closest('.outline').hidden = true;
    try {
        const markdown = await loadMarkdown(doc);
        if (requestId !== documentRequestId) {
            return;
        }
        content.innerHTML = safeMarkdownHtml(markdown, figureAssets);
        decoratePlaceholders();
        await renderMermaidDiagrams(doc.id);
        buildOutline();
        await highlightAlternativeChanges(doc, publishedDocs);

        // Load annotations for this document
        currentAnnotations = await fetchAnnotations(doc.id);
        renderAnnotationsDrawer();
        renderDocumentPins();
        rendered = true;
    } catch (error) {
        if (requestId !== documentRequestId) {
            return;
        }
        content.replaceChildren(Object.assign(document.createElement('p'), { textContent: error.message }));
        outline.replaceChildren();
        outline.closest('.outline').hidden = true;
    } finally {
        if (requestId === documentRequestId) {
            content.removeAttribute('aria-busy');
            if (rendered) {
                if (scrollToDocument) {
                    scrollReaderContentToStart();
                } else if (Number.isFinite(position)) {
                    restoreReaderPosition(position);
                }
                documentPositionReady = true;
            }
            updateManuscriptSearch(false);
            if (rendered) persistReaderState();
        }
    }
}

function appendDownloadLinks(docs) {
    for (const doc of docs.filter((item) => item.kind === 'chapter')) {
        const link = document.createElement('a');
        link.href = new URL(encodeURIComponent(doc.fileName), window.location.href).href;
        link.download = doc.fileName;
        link.textContent = doc.title;
        link.addEventListener('click', (event) => {
            event.preventDefault();
            void downloadMarkdown(doc);
        });
        chapterDownloads.append(link);
    }
}

function appendNavigation(docs) {
    for (const group of [
        ['Full chapter reading copies · Original', docs.filter((doc) => doc.kind === 'chapter' && !doc.alternative)],
        ['Full chapter reading copies · Alternative 1', docs.filter((doc) => doc.kind === 'chapter' && doc.alternative)],
        ['Chapter 1 · Introduction', docs.filter((doc) => doc.kind === 'section' && doc.id.startsWith('chapter-1-'))],
        ['Chapter 2 · Methodology', docs.filter((doc) => doc.kind === 'section' && doc.id.startsWith('chapter-2-'))],
        ['Chapter 3 · Results and Discussion', docs.filter((doc) => doc.kind === 'section' && doc.id.startsWith('chapter-3-'))],
        ['Appendices', docs.filter((doc) => doc.kind === 'appendix')],
    ]) {
        if (!group[1].length) continue;
        const section = document.createElement('section');
        section.className = 'navigation-group';
        const heading = document.createElement('h3');
        heading.textContent = group[0];
        section.append(heading);
        const list = document.createElement('ul');
        for (const doc of group[1]) {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = `?document=${encodeURIComponent(doc.id)}#document-content`;
            link.dataset.documentId = doc.id;
            if (doc.alternative || doc.id.includes('-alternative-')) {
                link.classList.add('nav-link-alternative');
            }
            link.textContent = doc.title;
            link.addEventListener('click', (event) => {
                event.preventDefault();
                navigateToDocument(doc, link.href);
            });
            item.append(link);
            list.append(item);
            const option = document.createElement('option');
            option.value = doc.id;
            option.textContent = doc.title;
            selector.append(option);
        }
        section.append(list);
        navigation.append(section);
    }
}

function readStoredReaderState() {
    const fallback = { documentId: null, documentPositions: {}, referencePageId: null };
    try {
        const stored = window.localStorage.getItem(readerStorageKey);
        if (!stored) return fallback;
        const parsed = JSON.parse(stored);
        if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return fallback;
        const documentPositions = Object.create(null);
        if (parsed.documentPositions && typeof parsed.documentPositions === 'object' && !Array.isArray(parsed.documentPositions)) {
            for (const [id, position] of Object.entries(parsed.documentPositions)) {
                if (typeof id === 'string' && Number.isFinite(position) && position >= 0 && position <= 10000000) {
                    documentPositions[id] = position;
                }
            }
        }
        return {
            documentId: typeof parsed.documentId === 'string' ? parsed.documentId : null,
            documentPositions,
            referencePageId: typeof parsed.referencePageId === 'string' ? parsed.referencePageId : null,
        };
    } catch {
        return fallback;
    }
}

function storedPositionFor(documentId) {
    const positions = readerState.documentPositions ?? {};
    const position = Object.hasOwn(positions, documentId) ? positions[documentId] : null;
    return Number.isFinite(position) && position >= 0 && position <= 10000000 ? position : null;
}
function currentReadingPosition() {
    const position = referenceReturnScrollPosition ?? window.scrollY;
    return Number.isFinite(position) && position >= 0 ? position : null;
}
function restoreReaderPosition(position) {
    const safePosition = Math.max(0, position);
    if (referenceReturnScrollPosition !== null) {
        referenceReturnScrollPosition = safePosition;
        document.body.style.top = `-${safePosition}px`;
        return;
    }
    if (typeof window.scrollTo === 'function') window.scrollTo(0, safePosition);
}

function scrollReaderContentToStart() {
    if (referenceReturnScrollPosition !== null) {
        const documentPosition = content.getBoundingClientRect().top + referenceReturnScrollPosition;
        restoreReaderPosition(documentPosition);
    } else if (typeof content.scrollIntoView === 'function') {
        content.scrollIntoView({ block: 'start' });
    }
}

function persistReaderState(updateHistory = true) {
    const position = currentReadingPosition();
    if (activeDocument && documentPositionReady) {
        readerState.documentId = activeDocument.id;
        if (position !== null) readerState.documentPositions[activeDocument.id] = position;
    }
    if (referenceIndex && referencePageIndex >= 0) {
        readerState.referencePageId = referenceIndex.pages[referencePageIndex]?.id ?? readerState.referencePageId;
    }
    try {
        window.localStorage.setItem(readerStorageKey, JSON.stringify(readerState));
    } catch {
        // Reading remains available when browser storage is blocked or full.
    }
    if (updateHistory && activeDocument && documentPositionReady) {
        try {
            const currentState = history.state && typeof history.state === 'object' && !Array.isArray(history.state) ? history.state : {};
            history.replaceState({
                ...currentState,
                documentId: activeDocument.id,
                readerScrollY: position ?? currentState.readerScrollY ?? null,
            }, '', window.location.href);
        } catch {
            // The URL may be hosted in an embedded context that disallows history writes.
        }
    }
}

function safePublicationAssetUrl(relativePath) {
    if (typeof relativePath !== 'string' || !relativePath || relativePath.trim() !== relativePath
        || relativePath.startsWith('/') || relativePath.includes('\\') || relativePath.includes('?') || relativePath.includes('#')) {
        return null;
    }
    const segments = relativePath.split('/');
    if (segments.some((segment) => !segment || segment === '.' || segment === '..' || segment.includes(':'))) {
        return null;
    }
    try {
        const publicationRoot = new URL('.', manifestUrl);
        const url = new URL(segments.map((segment) => encodeURIComponent(segment)).join('/'), manifestUrl);
        if (url.origin !== publicationRoot.origin || !url.pathname.startsWith(publicationRoot.pathname)) {
            return null;
        }
        return url;
    } catch {
        return null;
    }
}

function focusWithoutScroll(element) {
    if (!element || typeof element.focus !== 'function') return;
    try {
        element.focus({ preventScroll: true });
    } catch {
        element.focus();
    }
}

function clearSearchHighlights(root) {
    root.querySelectorAll('[data-reader-search-highlight]').forEach((highlight) => {
        highlight.replaceWith(...Array.from(highlight.childNodes));
    });
    root.normalize();
}

function findTextRanges(text, query) {
    if (!query) return [];
    const expression = new RegExp(query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'giu');
    return Array.from(text.matchAll(expression), (match) => ({
        start: match.index,
        end: match.index + match[0].length,
    }));
}

function collectManuscriptTextGroups() {
    const groups = new Map();
    const walker = document.createTreeWalker(content, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (!node.textContent) continue;
        const parent = node.parentElement;
        if (!parent || parent.closest('script, style, noscript, svg, .mermaid-diagram-card, .annotation-pin')) continue;
        const block = parent.closest('p, li, td, th, h1, h2, h3, h4, h5, h6, blockquote, pre, dt, dd') ?? content;
        if (!groups.has(block)) groups.set(block, []);
        groups.get(block).push(node);
    }
    return Array.from(groups.values());
}

function mapRangeToTextNodes(nodes, start, end) {
    const segments = [];
    let cursor = 0;
    for (const node of nodes) {
        const nodeEnd = cursor + node.textContent.length;
        const segmentStart = Math.max(start, cursor);
        const segmentEnd = Math.min(end, nodeEnd);
        if (segmentStart < segmentEnd) {
            segments.push({ node, start: segmentStart - cursor, end: segmentEnd - cursor });
        }
        cursor = nodeEnd;
        if (cursor >= end) break;
    }
    return segments;
}

function buildManuscriptMatches(query) {
    const matches = [];
    for (const nodes of collectManuscriptTextGroups()) {
        const text = nodes.map((node) => node.textContent).join('');
        for (const range of findTextRanges(text, query)) {
            matches.push({ segments: mapRangeToTextNodes(nodes, range.start, range.end) });
        }
    }
    return matches;
}

function applySearchHighlights(matches, currentIndex, matchIndices = null) {
    const rangesByNode = new Map();
    matches.forEach((match, matchIndex) => {
        const resultIndex = matchIndices?.[matchIndex] ?? matchIndex;
        for (const segment of match.segments) {
            if (!rangesByNode.has(segment.node)) rangesByNode.set(segment.node, []);
            rangesByNode.get(segment.node).push({ ...segment, matchIndex: resultIndex });
        }
    });
    for (const [node, ranges] of rangesByNode) {
        ranges.sort((left, right) => right.start - left.start);
        for (const range of ranges) {
            if (!node.isConnected || range.start < 0 || range.end > node.textContent.length || range.start >= range.end) continue;
            const matchedText = node.splitText(range.start);
            matchedText.splitText(range.end - range.start);
            const highlight = document.createElement('span');
            highlight.className = 'reader-search-highlight';
            highlight.dataset.readerSearchHighlight = 'true';
            highlight.dataset.readerMatch = String(range.matchIndex);
            if (range.matchIndex === currentIndex) highlight.classList.add('is-current');
            matchedText.replaceWith(highlight);
            highlight.append(matchedText);
        }
    }
}

function scrollToSearchMatch(root, matchIndex) {
    const highlight = root.querySelector(`[data-reader-match="${matchIndex}"]`);
    if (highlight && typeof highlight.scrollIntoView === 'function') {
        highlight.scrollIntoView({ block: 'center', behavior: 'auto' });
    }
}

function updateManuscriptSearch(scrollToMatch = false) {
    if (!manuscriptSearchInput || !manuscriptSearchCount) return;
    clearSearchHighlights(content);
    const query = manuscriptSearchInput.value.trim();
    if (!query) {
        manuscriptMatches = [];
        manuscriptMatchIndex = -1;
        manuscriptSearchCount.textContent = 'Enter text to find.';
        manuscriptSearchPrevious.disabled = true;
        manuscriptSearchNext.disabled = true;
        return;
    }
    manuscriptMatches = buildManuscriptMatches(query);
    manuscriptMatchIndex = manuscriptMatches.length ? 0 : -1;
    manuscriptSearchPrevious.disabled = !manuscriptMatches.length;
    manuscriptSearchNext.disabled = !manuscriptMatches.length;
    if (!manuscriptMatches.length) {
        manuscriptSearchCount.textContent = 'No matches in this section.';
        return;
    }
    applySearchHighlights(manuscriptMatches, manuscriptMatchIndex);
    manuscriptSearchCount.textContent = `1 of ${manuscriptMatches.length} matches`;
    if (scrollToMatch) scrollToSearchMatch(content, manuscriptMatchIndex);
}

function moveManuscriptMatch(direction) {
    if (!manuscriptMatches.length) return;
    manuscriptMatchIndex = (manuscriptMatchIndex + direction + manuscriptMatches.length) % manuscriptMatches.length;
    content.querySelectorAll('[data-reader-search-highlight]').forEach((highlight) => {
        highlight.classList.toggle('is-current', highlight.dataset.readerMatch === String(manuscriptMatchIndex));
    });
    manuscriptSearchCount.textContent = `${manuscriptMatchIndex + 1} of ${manuscriptMatches.length} matches`;
    scrollToSearchMatch(content, manuscriptMatchIndex);
}

function setupManuscriptSearch() {
    manuscriptSearchInput?.addEventListener('input', () => updateManuscriptSearch(true));
    manuscriptSearchPrevious?.addEventListener('click', () => moveManuscriptMatch(-1));
    manuscriptSearchNext?.addEventListener('click', () => moveManuscriptMatch(1));
    window.addEventListener('scroll', () => {
        if (!activeDocument || !documentPositionReady) return;
        if (positionSaveTimer !== null) window.clearTimeout(positionSaveTimer);
        positionSaveTimer = window.setTimeout(() => {
            positionSaveTimer = null;
            persistReaderState();
        }, 120);
    }, { passive: true });
    window.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'f') {
            event.preventDefault();
            const input = referenceDialog?.open ? referenceSearchInput : manuscriptSearchInput;
            focusWithoutScroll(input);
            input?.select?.();
            return;
        }
        if (event.key === 'Escape' && referenceDialog?.open) {
            event.preventDefault();
            referenceDialog.close();
            return;
        }
        if (event.key === 'Escape' && document.activeElement === manuscriptSearchInput) {
            event.preventDefault();
            focusWithoutScroll(content);
        }
    });
}

function pageHasUsableText(page) {
    return (page.status === 'pdf-text' || page.status === 'ocr') && typeof page.text === 'string' && Boolean(page.text.trim());
}

function normalizeReferenceIndex(data) {
    if (!data || typeof data !== 'object' || !Array.isArray(data.pages) || !data.pages.length
        || !Array.isArray(data.missingPages) || !Array.isArray(data.notes)) {
        throw new Error('The reference index does not match the published page contract.');
    }
    const ids = new Set();
    const pages = data.pages.map((page) => {
        if (!page || typeof page !== 'object' || typeof page.id !== 'string' || !page.id
            || ids.has(page.id) || !Number.isInteger(page.pdfPage) || page.pdfPage < 1
            || typeof page.label !== 'string' || (page.printedPage !== null && (!Number.isInteger(page.printedPage) || page.printedPage < 1))
            || typeof page.group !== 'string' || typeof page.text !== 'string' || typeof page.status !== 'string') {
            throw new Error('The reference index contains an invalid page entry.');
        }
        const imageUrl = safePublicationAssetUrl(page.image);
        if (!imageUrl) throw new Error(`Reference image path for PDF page ${page.pdfPage} is outside the publication.`);
        ids.add(page.id);
        return { ...page, imageUrl: imageUrl.href };
    });
    if (data.missingPages.some((page) => !Number.isInteger(page) || page < 1)
        || data.notes.some((note) => typeof note !== 'string')) {
        throw new Error('The reference index contains invalid pagination notes.');
    }
    return { pages, missingPages: data.missingPages, notes: data.notes };
}

function configureReference(reference) {
    if (!reference || typeof reference !== 'object' || Array.isArray(reference)) {
        if (referenceNavigationGroup) referenceNavigationGroup.hidden = true;
        return;
    }
    const titleText = typeof reference.title === 'string' && reference.title.trim()
        ? reference.title.trim()
        : 'YuKee · Original research';
    const indexUrl = safePublicationAssetUrl(reference.indexFile);
    const pdfUrl = safePublicationAssetUrl(reference.pdfFile);
    referenceConfiguration = {
        title: titleText,
        indexFile: reference.indexFile,
        pdfFile: reference.pdfFile,
        indexUrl: indexUrl?.href ?? null,
        pdfUrl: pdfUrl?.href ?? null,
    };
    referenceOpenButton.textContent = titleText;
    const dialogTitle = referenceDialog.querySelector('#reference-dialog-title');
    if (dialogTitle) dialogTitle.textContent = titleText;
    referenceNavigationGroup.hidden = false;
    referencePdfDownload.disabled = !referenceConfiguration.pdfUrl;
    referenceRetryIndexButton.hidden = !referenceConfiguration.indexUrl;
}

function getReferencePage() {
    return referenceIndex && referencePageIndex >= 0 ? referenceIndex.pages[referencePageIndex] ?? null : null;
}

function renderReferenceIndexNotes() {
    referenceMissingPagesNote.textContent = referenceIndex.missingPages.length
        ? `Verified gaps between readable printed-page labels: ${referenceIndex.missingPages.join(', ')}. These label gaps do not establish pages missing from the complete manuscript.`
        : 'The index declares no verified gaps between readable printed-page labels.';
    referenceNotesList.replaceChildren();
    for (const note of referenceIndex.notes) {
        const item = document.createElement('li');
        item.textContent = note;
        referenceNotesList.append(item);
    }
    referenceIndexNotes.hidden = false;
}

function loadReferenceIndex() {
    if (referenceIndex) return Promise.resolve(referenceIndex);
    if (referenceIndexPromise) return referenceIndexPromise;
    referenceIndexPromise = (async () => {
        referenceLoadError.hidden = true;
        referenceLoadStatus.textContent = 'Loading reference page index…';
        referenceRetryIndexButton.disabled = true;
        try {
            if (!referenceConfiguration?.indexUrl) {
                throw new Error('The published reference index path is invalid.');
            }
            const response = await fetch(referenceConfiguration.indexUrl, requestOptions);
            if (!response.ok || response.headers.get('content-type')?.includes('text/html')) {
                throw new Error(`Unable to load the reference page index (HTTP ${response.status}).`);
            }
            referenceIndex = normalizeReferenceIndex(await response.json());
            referencePageSelect.replaceChildren();
            for (const [index, page] of referenceIndex.pages.entries()) {
                const option = document.createElement('option');
                option.value = page.id;
                const printed = page.printedPage === null ? 'printed page unverified' : `printed page ${page.printedPage}`;
                option.textContent = `PDF page ${page.pdfPage} · ${page.label} · ${printed} · ${page.group}`;
                option.dataset.pageIndex = String(index);
                referencePageSelect.append(option);
            }
            const storedPageIndex = referenceIndex.pages.findIndex((page) => page.id === readerState.referencePageId);
            referencePageIndex = storedPageIndex >= 0 ? storedPageIndex : 0;
            renderReferenceIndexNotes();
            referencePageSelect.disabled = false;
            referenceSearchInput.disabled = false;
            referencePdfDownload.disabled = !referenceConfiguration.pdfUrl;
            referencePagePrevious.disabled = referencePageIndex <= 0;
            referencePageNext.disabled = referencePageIndex >= referenceIndex.pages.length - 1;
            referenceZoomOut.disabled = false;
            referenceZoomIn.disabled = false;
            referenceLoadStatus.textContent = `${referenceIndex.pages.length} original PDF pages indexed.`;
            referencePageSelect.value = referenceIndex.pages[referencePageIndex].id;
            persistReaderState();
            renderReferenceCurrentPage();
            if (referenceSearchInput.value.trim()) updateReferenceSearch(false);
            return referenceIndex;
        } catch (error) {
            referenceLoadErrorMessage.textContent = error instanceof Error ? error.message : 'Unable to load the reference page index.';
            referenceLoadError.hidden = false;
            referenceLoadStatus.textContent = 'Reference index unavailable.';
            return null;
        } finally {
            referenceRetryIndexButton.disabled = false;
            referenceIndexPromise = null;
        }
    })();
    return referenceIndexPromise;
}

function clearReferenceImage() {
    referenceImageRequestId += 1;
    referenceImageController?.abort();
    referenceImageController = null;
    if (referenceImageObjectUrl) URL.revokeObjectURL(referenceImageObjectUrl);
    referenceImageObjectUrl = '';
    referenceImagePath = '';
    referencePageImage.removeAttribute('src');
    referencePageImage.hidden = true;
    referenceImageError.hidden = true;
    referenceImageLoading.hidden = true;
}

async function loadReferenceImage(page) {
    if (!page || referenceImagePath === page.image) return;
    clearReferenceImage();
    const requestId = ++referenceImageRequestId;
    const controller = new AbortController();
    referenceImageController = controller;
    referenceImagePath = page.image;
    referencePageImage.alt = `Scanned original, PDF page ${page.pdfPage}; ${page.printedPage === null ? 'printed page label unverified' : `printed page ${page.printedPage}`}.`;
    referenceImageLoading.textContent = `Loading scan for PDF page ${page.pdfPage}…`;
    referenceImageLoading.hidden = false;
    referenceImageError.hidden = true;
    referenceRetryImageButton.disabled = true;
    try {
        const response = await fetch(page.imageUrl, { ...requestOptions, signal: controller.signal });
        const contentType = response.headers.get('content-type') ?? '';
        if (!response.ok || contentType.includes('text/html') || (contentType && !contentType.toLowerCase().startsWith('image/'))) {
            throw new Error(`Unable to load the scan for PDF page ${page.pdfPage} (HTTP ${response.status}).`);
        }
        const blob = await response.blob();
        if (requestId !== referenceImageRequestId) return;
        if (!blob.size) throw new Error(`The scan for PDF page ${page.pdfPage} is empty.`);
        referenceImageObjectUrl = URL.createObjectURL(blob);
        referencePageImage.src = referenceImageObjectUrl;
        referencePageImage.hidden = false;
        referenceImageLoading.hidden = true;
    } catch (error) {
        if (controller.signal.aborted || requestId !== referenceImageRequestId) return;
        referenceImageErrorMessage.textContent = error instanceof Error ? error.message : 'Unable to load this scan.';
        referenceImageError.hidden = false;
        referenceImageLoading.hidden = true;
    } finally {
        if (requestId === referenceImageRequestId) {
            referenceImageController = null;
            referenceRetryImageButton.disabled = false;
        }
    }
}

function renderReferenceSearchCount() {
    const query = referenceSearchInput.value.trim();
    if (!referenceIndex) {
        referenceSearchCount.textContent = query
            ? 'Reference index is loading; your search will run when it is available.'
            : 'Load the reference index to search.';
        return;
    }
    const page = getReferencePage();
    referenceSearchPrevious.disabled = referenceMatches.length === 0;
    referenceSearchNext.disabled = referenceMatches.length === 0;
    if (!query) {
        referenceSearchCount.textContent = 'Enter text to search the extracted pages.';
        return;
    }
    const searchablePageCount = referenceIndex.pages.filter(pageHasUsableText).length;
    if (!referenceMatches.length) {
        if (!searchablePageCount) {
            referenceSearchCount.textContent = 'Search unavailable: no page has usable extracted text.';
        } else if (page && !pageHasUsableText(page)) {
            referenceSearchCount.textContent = `No matches in available extracted text; PDF page ${page.pdfPage} has no usable text to search.`;
        } else {
            referenceSearchCount.textContent = 'No matches in available extracted text.';
        }
        return;
    }
    const currentMatch = referenceMatches[referenceMatchIndex];
    if (currentMatch.pageIndex === referencePageIndex) {
        referenceSearchCount.textContent = `${referenceMatchIndex + 1} of ${referenceMatches.length} matches · current PDF page has matches.`;
    } else if (page && !pageHasUsableText(page)) {
        referenceSearchCount.textContent = `${referenceMatchIndex + 1} of ${referenceMatches.length} matches · this PDF page has no usable text to search.`;
    } else {
        referenceSearchCount.textContent = `${referenceMatchIndex + 1} of ${referenceMatches.length} matches · current PDF page has no matches.`;
    }
}

function renderReferenceCurrentPage() {
    const page = getReferencePage();
    if (!page) return;
    referencePageSelect.value = page.id;
    referencePagePrevious.disabled = referencePageIndex <= 0;
    referencePageNext.disabled = referencePageIndex >= referenceIndex.pages.length - 1;
    referencePageMetadata.textContent = `PDF page ${page.pdfPage} · ${page.label} · ${page.printedPage === null ? 'Printed page unverified' : `Printed page ${page.printedPage}`} · Group: ${page.group} · ${page.status}`;
    referenceTextStatus.textContent = pageHasUsableText(page)
        ? `${page.status === 'ocr' ? 'OCR text' : 'PDF text extraction'} is available and may be inaccurate. Verify against the original scan.`
        : `Search unavailable for this page (${page.status || 'no extraction status'}). Use the scan; this page has no usable extracted text.`;
    referencePageText.replaceChildren();
    if (page.text.trim()) {
        const textNode = document.createTextNode(page.text);
        referencePageText.append(textNode);
        if (pageHasUsableText(page)) {
            const ranges = referenceMatches
                .map((match, matchIndex) => ({ ...match, matchIndex }))
                .filter((match) => match.pageIndex === referencePageIndex);
            applySearchHighlights(ranges.map((match) => ({
                segments: [{ node: textNode, start: match.start, end: match.end }],
            })), referenceMatchIndex, ranges.map((match) => match.matchIndex));
        }
    } else {
        referencePageText.textContent = 'No extracted text is available for this page. The original scan remains available.';
    }
    renderReferenceSearchCount();
    referenceZoomLevel.textContent = `${referenceZoom}%`;
    document.querySelector('.reference-scan-frame')?.style.setProperty('--reference-zoom', `${referenceZoom}%`);
    if (referenceDialog.open && referenceImagePath !== page.image) void loadReferenceImage(page);
}

function setReferencePage(index, preserveMatch = false) {
    if (!referenceIndex || index < 0 || index >= referenceIndex.pages.length) return;
    if (index !== referencePageIndex) {
        referencePageIndex = index;
        clearReferenceImage();
    }
    if (!preserveMatch && referenceMatches.length) {
        const matchIndex = referenceMatches.findIndex((match) => match.pageIndex === referencePageIndex);
        if (matchIndex >= 0) referenceMatchIndex = matchIndex;
    }
    readerState.referencePageId = referenceIndex.pages[referencePageIndex].id;
    persistReaderState();
    renderReferenceCurrentPage();
}

function updateReferenceSearch(scrollToMatch = false) {
    const query = referenceSearchInput.value.trim();
    if (!referenceIndex) {
        referenceSearchPrevious.disabled = true;
        referenceSearchNext.disabled = true;
        referenceSearchCount.textContent = query
            ? 'Reference index is loading; your search will run when it is available.'
            : 'Load the reference index to search.';
        return;
    }
    referenceMatches = [];
    referenceMatchIndex = -1;
    if (query) {
        referenceIndex.pages.forEach((page, pageIndex) => {
            if (!pageHasUsableText(page)) return;
            for (const range of findTextRanges(page.text, query)) {
                referenceMatches.push({ pageIndex, start: range.start, end: range.end });
            }
        });
    }
    if (referenceMatches.length) {
        referenceMatchIndex = 0;
        const nextPageIndex = referenceMatches[0].pageIndex;
        if (nextPageIndex !== referencePageIndex) {
            referencePageIndex = nextPageIndex;
            clearReferenceImage();
        }
        readerState.referencePageId = referenceIndex.pages[referencePageIndex].id;
        persistReaderState();
    }
    renderReferenceCurrentPage();
    if (scrollToMatch && referenceMatchIndex >= 0) {
        scrollToSearchMatch(referencePageText, referenceMatchIndex);
    }
}

function moveReferenceMatch(direction) {
    if (!referenceMatches.length) return;
    referenceMatchIndex = (referenceMatchIndex + direction + referenceMatches.length) % referenceMatches.length;
    const match = referenceMatches[referenceMatchIndex];
    if (match.pageIndex !== referencePageIndex) {
        referencePageIndex = match.pageIndex;
        clearReferenceImage();
    }
    readerState.referencePageId = referenceIndex.pages[referencePageIndex].id;
    persistReaderState();
    renderReferenceCurrentPage();
    scrollToSearchMatch(referencePageText, referenceMatchIndex);
}

async function downloadReferencePdf() {
    if (!referenceConfiguration?.pdfUrl) return;
    referencePdfDownload.disabled = true;
    referenceDownloadStatus.textContent = 'Preparing the original PDF download…';
    try {
        const response = await fetch(referenceConfiguration.pdfUrl, requestOptions);
        const contentType = response.headers.get('content-type') ?? '';
        if (!response.ok || contentType.includes('text/html')) {
            throw new Error(`Unable to download the original PDF (HTTP ${response.status}).`);
        }
        const blob = await response.blob();
        if (!blob.size) throw new Error('The original PDF download was empty.');
        const filename = referenceConfiguration.pdfFile.split('/').at(-1) || 'yuukee-original.pdf';
        downloadBlob(blob, filename);
        referenceDownloadStatus.textContent = 'Original PDF download started.';
    } catch (error) {
        referenceDownloadStatus.textContent = error instanceof Error ? error.message : 'Unable to download the original PDF.';
    } finally {
        referencePdfDownload.disabled = false;
    }
}

function lockReferenceBackground() {
    if (referenceReturnScrollPosition !== null) return;
    referenceReturnScrollPosition = Number.isFinite(window.scrollY) ? Math.max(0, window.scrollY) : 0;
    referenceScrollLockStyles = {
        bodyPosition: document.body.style.position,
        bodyTop: document.body.style.top,
        bodyLeft: document.body.style.left,
        bodyRight: document.body.style.right,
        bodyWidth: document.body.style.width,
        bodyOverflow: document.body.style.overflow,
        documentOverflow: document.documentElement.style.overflow,
    };
    document.body.style.position = 'fixed';
    document.body.style.top = `-${referenceReturnScrollPosition}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
}

function unlockReferenceBackground() {
    if (referenceReturnScrollPosition === null) return;
    const position = referenceReturnScrollPosition;
    const styles = referenceScrollLockStyles;
    document.body.style.position = styles.bodyPosition;
    document.body.style.top = styles.bodyTop;
    document.body.style.left = styles.bodyLeft;
    document.body.style.right = styles.bodyRight;
    document.body.style.width = styles.bodyWidth;
    document.body.style.overflow = styles.bodyOverflow;
    document.documentElement.style.overflow = styles.documentOverflow;
    referenceReturnScrollPosition = null;
    referenceScrollLockStyles = null;
    if (typeof window.scrollTo === 'function') window.scrollTo(0, position);
}

function openReferenceDialog() {
    if (!referenceConfiguration || !referenceDialog) return;
    persistReaderState();
    lockReferenceBackground();
    try {
        if (!referenceDialog.open) referenceDialog.showModal();
    } catch (error) {
        unlockReferenceBackground();
        throw error;
    }
    referenceOpenButton.setAttribute('aria-expanded', 'true');
    focusWithoutScroll(referenceCloseButton);
    if (referenceIndex) {
        renderReferenceCurrentPage();
    } else {
        void loadReferenceIndex();
    }
}

function setupReferenceReader(reference) {
    configureReference(reference);
    if (!referenceConfiguration) return;
    referenceOpenButton.addEventListener('click', openReferenceDialog);
    referenceCloseButton.addEventListener('click', () => referenceDialog.close());
    referenceDialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        referenceDialog.close();
    });
    referenceDialog.addEventListener('close', () => {
        referenceOpenButton.setAttribute('aria-expanded', 'false');
        unlockReferenceBackground();
        persistReaderState();
        focusWithoutScroll(referenceOpenButton);
    });
    referenceDialog.addEventListener('click', (event) => {
        if (event.target === referenceDialog) referenceDialog.close();
    });
    referenceRetryIndexButton.addEventListener('click', () => {
        referenceIndex = null;
        void loadReferenceIndex();
    });
    referencePageSelect.addEventListener('change', () => {
        const index = referenceIndex?.pages.findIndex((page) => page.id === referencePageSelect.value) ?? -1;
        setReferencePage(index);
    });
    referencePagePrevious.addEventListener('click', () => setReferencePage(referencePageIndex - 1));
    referencePageNext.addEventListener('click', () => setReferencePage(referencePageIndex + 1));
    referenceRetryImageButton.addEventListener('click', () => {
        const page = getReferencePage();
        if (page) {
            clearReferenceImage();
            void loadReferenceImage(page);
        }
    });
    referencePageImage.addEventListener('error', () => {
        if (!referenceImagePath) return;
        referenceImageErrorMessage.textContent = `The scan for PDF page ${getReferencePage()?.pdfPage ?? ''} could not be displayed.`;
        referenceImageError.hidden = false;
        referencePageImage.hidden = true;
        referenceImageLoading.hidden = true;
    });
    referenceSearchInput.addEventListener('input', () => updateReferenceSearch(true));
    referenceSearchPrevious.addEventListener('click', () => moveReferenceMatch(-1));
    referenceSearchNext.addEventListener('click', () => moveReferenceMatch(1));
    referenceZoomOut.addEventListener('click', () => {
        referenceZoom = Math.max(60, referenceZoom - 20);
        referenceZoomOut.disabled = referenceZoom <= 60;
        referenceZoomIn.disabled = referenceZoom >= 240;
        renderReferenceCurrentPage();
    });
    referenceZoomIn.addEventListener('click', () => {
        referenceZoom = Math.min(240, referenceZoom + 20);
        referenceZoomOut.disabled = referenceZoom <= 60;
        referenceZoomIn.disabled = referenceZoom >= 240;
        renderReferenceCurrentPage();
    });
    referencePdfDownload.addEventListener('click', () => void downloadReferencePdf());
}
function navigateToDocument(doc, destination) {
    persistReaderState();
    const position = storedPositionFor(doc.id);
    const currentState = history.state && typeof history.state === 'object' && !Array.isArray(history.state) ? history.state : {};
    history.pushState({ ...currentState, documentId: doc.id, readerScrollY: position }, '', destination);
    void showDocument(doc, { position, scrollToDocument: position === null });
}

async function start() {
    try {
        readerState = readStoredReaderState();
        try {
            history.scrollRestoration = 'manual';
        } catch {
            // Browser history can still restore reader selection without scroll restoration support.
        }
        setupAnnotationListeners();
        setupManuscriptSearch();
        const response = await fetch(manifestUrl, requestOptions);
        if (!response.ok) throw new Error(`Unable to load publication index (HTTP ${response.status}).`);
        const manifest = await response.json();
        if (!Array.isArray(manifest.docs) || !manifest.docs.length) throw new Error('This publication contains no documents.');
        publishedDocs = manifest.docs;
        setupGeneration(manifest.generation);
        figureAssets = manifest.assets ?? {};
        const approvedFigureFiles = new Set(Object.values(figureAssets));
        for (const [fileName, dimensions] of Object.entries(manifest.figureDimensions ?? {})) {
            if (approvedFigureFiles.has(fileName) && /^[A-Za-z0-9_-]+\.png$/.test(fileName)
                && Number.isSafeInteger(dimensions?.width) && dimensions.width > 0
                && Number.isSafeInteger(dimensions?.height) && dimensions.height > 0) {
                figureImageDimensions[fileName] = { width: dimensions.width, height: dimensions.height };
            }
        }
        const validDocumentIds = new Set(manifest.docs.map((doc) => doc.id));
        readerState.documentPositions = Object.fromEntries(
            Object.entries(readerState.documentPositions).filter(([id]) => validDocumentIds.has(id)),
        );
        if (!validDocumentIds.has(readerState.documentId)) readerState.documentId = null;
        publishedAt.textContent = new Date(manifest.publishedAt).toLocaleString();
        appendNavigation(manifest.docs);
        appendDownloadLinks(manifest.docs);
        setupReferenceReader(manifest.reference);
        selector.addEventListener('change', () => {
            const doc = manifest.docs.find((item) => item.id === selector.value);
            if (doc) {
                navigateToDocument(doc, `?document=${encodeURIComponent(doc.id)}#document-content`);
            }
        });
        window.addEventListener('popstate', (event) => {
            persistReaderState(false);
            const queryId = new URLSearchParams(window.location.search).get('document');
            const queryDocument = manifest.docs.find((doc) => doc.id === queryId);
            const stateDocument = manifest.docs.find((doc) => doc.id === event.state?.documentId);
            const storedDocument = manifest.docs.find((doc) => doc.id === readerState.documentId);
            const chosen = queryDocument ?? stateDocument ?? storedDocument ?? manifest.docs[0];
            const historyPosition = event.state?.documentId === chosen.id
                && Number.isFinite(event.state?.readerScrollY)
                && event.state.readerScrollY >= 0
                ? event.state.readerScrollY
                : null;
            const position = historyPosition ?? storedPositionFor(chosen.id);
            void showDocument(chosen, { position });
        });
        const queryId = new URLSearchParams(window.location.search).get('document');
        const explicitDocument = manifest.docs.find((doc) => doc.id === queryId);
        const storedDocument = manifest.docs.find((doc) => doc.id === readerState.documentId);
        const chosen = explicitDocument ?? storedDocument ?? manifest.docs[0];
        const currentState = history.state && typeof history.state === 'object' && !Array.isArray(history.state) ? history.state : {};
        const historyPosition = currentState.documentId === chosen.id
            && Number.isFinite(currentState.readerScrollY)
            && currentState.readerScrollY >= 0
            ? currentState.readerScrollY
            : null;
        const initialPosition = historyPosition ?? storedPositionFor(chosen.id);
        readerState.documentId = chosen.id;
        history.replaceState({ ...currentState, documentId: chosen.id, readerScrollY: initialPosition }, '', window.location.href);
        await showDocument(chosen, { position: initialPosition });
    } catch (error) {
        title.textContent = 'Publication unavailable';
        content.textContent = error.message;
    }
}

if (document.querySelector('#document-navigation')) {
    void start();
}
