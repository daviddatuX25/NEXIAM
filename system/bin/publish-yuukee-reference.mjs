import { createHash } from 'node:crypto';
import { createReadStream } from 'node:fs';
import { execFile as execFileCallback } from 'node:child_process';
import { chmod, copyFile, mkdir, readFile, realpath, rename, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { promisify } from 'node:util';

const execFile = promisify(execFileCallback);
const SOURCE_RELATIVE_PATH = 'inspiration/yuuke_researche_pages.pdf';
const PAGE_COUNT = 92;
const REFERENCE_PDF_NAME = 'yuukee-original.pdf';
const CACHE_ROOT_NAME = 'manuscript-reference-cache';
const CACHE_SCHEMA_VERSION = 1;
const VERIFIED_SOURCE_SHA256 = '80b03a5acdc7dd183865f35b35d51472d1728bde8921bd310e87523dae835fed';

// Only labels confirmed by visual inspection of the source scans belong here.
// Keys are original PDF positions, not printed pagination.
const VERIFIED_PAGE_LABELS = new Map([
    [1, { label: '17', printedPage: 17, group: 'chapter-2' }],
    [2, { label: '16', printedPage: 16, group: 'chapter-2' }],
    [3, { label: '15', printedPage: 15, group: 'chapter-2' }],
    [4, { label: 'Biographical Sketch (printed pagination unknown)', printedPage: null, group: 'unclassified' }],
    [5, { label: 'Appendix O · Documentation · 82', printedPage: 82, group: 'appendices' }],
    [6, { label: 'Appendix N · Certificate of Statistician · 81', printedPage: 81, group: 'appendices' }],
    [7, { label: '14', printedPage: 14, group: 'chapter-2' }],
    [8, { label: 'Appendix M · Plagiarism Certification · 80', printedPage: 80, group: 'appendices' }],
    [9, { label: 'Appendix L · Plagiarism Result · 79', printedPage: 79, group: 'appendices' }],
    [10, { label: 'Appendix K · Certificate of English Critic · 78', printedPage: 78, group: 'appendices' }],
    [11, { label: 'Appendix J · SUS Tabulation · 77', printedPage: 77, group: 'appendices' }],
    [12, { label: 'Appendix I · Certificate of Implementation · 76', printedPage: 76, group: 'appendices' }],
    [13, { label: 'Appendix H · SUS questionnaire · 75', printedPage: 75, group: 'appendices' }],
    [14, { label: 'Appendix H · Questionnaire (SUS) · 74', printedPage: 74, group: 'appendices' }],
    [15, { label: 'Appendix G · Deployment Letter · 73', printedPage: 73, group: 'appendices' }],
    [16, { label: 'Appendix P · Entity Relationship Diagram · 72', printedPage: 72, group: 'appendices' }],
    [17, { label: 'Appendix E · Data Flow Diagram Level 1 · 71', printedPage: 71, group: 'appendices' }],
    [18, { label: 'Appendix D · Data Flow Diagram Level 0 · 70', printedPage: 70, group: 'appendices' }],
    [19, { label: 'Appendix C · Use Case Diagram · 69', printedPage: 69, group: 'appendices' }],
    [20, { label: 'Appendix B · ICAT Process Flow Chart · 68', printedPage: 68, group: 'appendices' }],
    [21, { label: 'Appendix A · Communication Letter · 67', printedPage: 67, group: 'appendices' }],
    [22, { label: '65', printedPage: 65, group: 'unclassified' }],
    [23, { label: '64', printedPage: 64, group: 'unclassified' }],
    [24, { label: 'Glossary · 63', printedPage: 63, group: 'unclassified' }],
    [25, { label: 'Glossary · 62', printedPage: 62, group: 'unclassified' }],
    [26, { label: 'Glossary · 61', printedPage: 61, group: 'unclassified' }],
    [27, { label: '60', printedPage: 60, group: 'chapter-3' }],
    [28, { label: '59', printedPage: 59, group: 'chapter-3' }],
    [29, { label: '58', printedPage: 58, group: 'chapter-3' }],
    [30, { label: '57', printedPage: 57, group: 'chapter-3' }],
    [31, { label: '56', printedPage: 56, group: 'chapter-3' }],
    [32, { label: '55', printedPage: 55, group: 'chapter-3' }],
    [33, { label: '54', printedPage: 54, group: 'chapter-3' }],
    [34, { label: '53', printedPage: 53, group: 'chapter-3' }],
    [35, { label: '52', printedPage: 52, group: 'chapter-3' }],
    [36, { label: '51', printedPage: 51, group: 'chapter-3' }],
    [37, { label: '50', printedPage: 50, group: 'chapter-3' }],
    [38, { label: '49', printedPage: 49, group: 'chapter-3' }],
    [39, { label: '48', printedPage: 48, group: 'chapter-3' }],
    [40, { label: '47', printedPage: 47, group: 'chapter-3' }],
    [41, { label: '46', printedPage: 46, group: 'chapter-3' }],
    [42, { label: '45', printedPage: 45, group: 'chapter-3' }],
    [43, { label: '44', printedPage: 44, group: 'chapter-3' }],
    [44, { label: '43', printedPage: 43, group: 'chapter-3' }],
    [45, { label: '42', printedPage: 42, group: 'chapter-3' }],
    [46, { label: '41', printedPage: 41, group: 'chapter-3' }],
    [47, { label: '40', printedPage: 40, group: 'chapter-3' }],
    [48, { label: '39', printedPage: 39, group: 'chapter-3' }],
    [49, { label: '38', printedPage: 38, group: 'chapter-3' }],
    [50, { label: '37', printedPage: 37, group: 'chapter-3' }],
    [51, { label: '36', printedPage: 36, group: 'chapter-3' }],
    [52, { label: '35', printedPage: 35, group: 'chapter-3' }],
    [53, { label: '34', printedPage: 34, group: 'chapter-3' }],
    [54, { label: '33', printedPage: 33, group: 'chapter-3' }],
    [55, { label: '32', printedPage: 32, group: 'chapter-3' }],
    [56, { label: '31', printedPage: 31, group: 'chapter-3' }],
    [57, { label: '30', printedPage: 30, group: 'chapter-3' }],
    [58, { label: '29', printedPage: 29, group: 'chapter-3' }],
    [59, { label: '28', printedPage: 28, group: 'chapter-3' }],
    [60, { label: 'Chapter 3 · Results and Discussion (opening page; number not printed)', printedPage: null, group: 'chapter-3' }],
    [61, { label: '26', printedPage: 26, group: 'chapter-2' }],
    [62, { label: '25', printedPage: 25, group: 'chapter-2' }],
    [63, { label: '24', printedPage: 24, group: 'chapter-2' }],
    [64, { label: '23', printedPage: 23, group: 'chapter-2' }],
    [65, { label: '22', printedPage: 22, group: 'chapter-2' }],
    [66, { label: '21', printedPage: 21, group: 'chapter-2' }],
    [67, { label: '20', printedPage: 20, group: 'chapter-2' }],
    [68, { label: '19', printedPage: 19, group: 'chapter-2' }],
    [69, { label: 'Chapter 2 · Methodology (opening page; number not printed)', printedPage: null, group: 'chapter-2' }],
    [70, { label: '12', printedPage: 12, group: 'chapter-1' }],
    [71, { label: '11', printedPage: 11, group: 'chapter-1' }],
    [72, { label: '10', printedPage: 10, group: 'chapter-1' }],
    [73, { label: '9', printedPage: 9, group: 'chapter-1' }],
    [74, { label: '8', printedPage: 8, group: 'chapter-1' }],
    [75, { label: '7', printedPage: 7, group: 'chapter-1' }],
    [76, { label: '6', printedPage: 6, group: 'chapter-1' }],
    [77, { label: '5', printedPage: 5, group: 'chapter-1' }],
    [78, { label: '4', printedPage: 4, group: 'chapter-1' }],
    [79, { label: '3', printedPage: 3, group: 'chapter-1' }],
    [80, { label: '2', printedPage: 2, group: 'chapter-1' }],
    [81, { label: 'Chapter 1 · Introduction (opening page; number not printed)', printedPage: null, group: 'chapter-1' }],
    [82, { label: 'List of Plates (front matter)', printedPage: null, group: 'frontmatter' }],
    [83, { label: 'List of Figures (front matter)', printedPage: null, group: 'frontmatter' }],
    [84, { label: 'List of Tables (front matter)', printedPage: null, group: 'frontmatter' }],
    [85, { label: 'List of Appendices (front matter)', printedPage: null, group: 'frontmatter' }],
    [86, { label: 'Table of Contents (front matter)', printedPage: null, group: 'frontmatter' }],
    [87, { label: 'Table of Contents (front matter)', printedPage: null, group: 'frontmatter' }],
    [88, { label: 'Dedication (front matter)', printedPage: null, group: 'frontmatter' }],
    [89, { label: 'Acknowledgement (front matter)', printedPage: null, group: 'frontmatter' }],
    [90, { label: 'Abstract (front matter)', printedPage: null, group: 'frontmatter' }],
    [91, { label: 'Approval Sheet (front matter)', printedPage: null, group: 'frontmatter' }],
    [92, { label: 'Deployment approval letter (pagination unknown)', printedPage: null, group: 'unclassified' }],
]);
const VERIFIED_MISSING_PAGES = [18, 66];

async function run(program, args) {
    const { stdout } = await execFile(program, args, { maxBuffer: 32 * 1024 * 1024 });
    return stdout;
}

async function ensureInside(rootPath, relativePath) {
    if (path.isAbsolute(relativePath) || relativePath.split(/[\\/]/).some((part) => part === '..' || part === '')) {
        throw new Error(`Invalid fixed reference path: ${relativePath}`);
    }
    const root = await realpath(rootPath);
    const candidate = await realpath(path.join(root, relativePath));
    if (candidate !== root && !candidate.startsWith(`${root}${path.sep}`)) {
        throw new Error(`Reference source resolves outside manuscript root: ${relativePath}`);
    }
    const info = await stat(candidate);
    if (!info.isFile()) {
        throw new Error(`Reference source is not a file: ${relativePath}`);
    }
    return candidate;
}

function orderedPages(pages) {
    const groupRank = new Map([
        ['frontmatter', 0],
        ['chapter-1', 1],
        ['chapter-2', 2],
        ['chapter-3', 3],
        ['appendices', 5],
        ['unclassified', 6],
    ]);
    const getRank = (page) => {
        const group = page.group || 'unclassified';
        if (group === 'unclassified' && page.printedPage !== null) return 4;
        return groupRank.get(group) ?? 6;
    };
    return [...pages].sort((left, right) => {
        const leftRank = getRank(left);
        const rightRank = getRank(right);
        if (leftRank !== rightRank) return leftRank - rightRank;
        if (leftRank === 0) return right.pdfPage - left.pdfPage;
        if (leftRank >= 1 && leftRank <= 3) {
            const leftOpening = left.printedPage === null;
            const rightOpening = right.printedPage === null;
            if (leftOpening !== rightOpening) return leftOpening ? -1 : 1;
            if (!leftOpening && left.printedPage !== right.printedPage) return left.printedPage - right.printedPage;
            return left.pdfPage - right.pdfPage;
        }
        if (leftRank === 4 && left.printedPage !== right.printedPage) return left.printedPage - right.printedPage;
        if (leftRank === 5) {
            if (left.printedPage !== right.printedPage) {
                return (left.printedPage ?? Number.MAX_SAFE_INTEGER) - (right.printedPage ?? Number.MAX_SAFE_INTEGER);
            }
            const leftAppendix = left.label.match(/^Appendix ([A-Z])/i)?.[1]?.toUpperCase() ?? '';
            const rightAppendix = right.label.match(/^Appendix ([A-Z])/i)?.[1]?.toUpperCase() ?? '';
            if (leftAppendix !== rightAppendix) return leftAppendix.localeCompare(rightAppendix);
        }
        return left.pdfPage - right.pdfPage;
    });
}

async function completeCacheExists(cacheDirectory, sourceHash, requireSchemaVersion) {
    try {
        const record = JSON.parse(await readFile(path.join(cacheDirectory, 'complete.json'), 'utf8'));
        if (record.sourceHash !== sourceHash || record.pageCount !== PAGE_COUNT) return false;
        if (requireSchemaVersion && record.schemaVersion !== CACHE_SCHEMA_VERSION) return false;
        const indexExists = await stat(path.join(cacheDirectory, 'index.json')).then((result) => result.isFile()).catch(() => false);
        if (!indexExists) return false;
        const imagesExist = await Promise.all(Array.from({ length: PAGE_COUNT }, (_, offset) => {
            const page = String(offset + 1).padStart(3, '0');
            return stat(path.join(cacheDirectory, 'yuukee', `page-${page}.jpg`)).then((result) => result.isFile()).catch(() => false);
        }));
        return imagesExist.every(Boolean);
    } catch (error) {
        if (error.code === 'ENOENT' || error instanceof SyntaxError) return false;
        throw error;
    }
}

async function generateCache(sourcePath, cacheRoot, sourceHash) {
    const cacheDirectory = path.join(cacheRoot, `${sourceHash}-v${CACHE_SCHEMA_VERSION}`);
    const legacyCacheDirectory = path.join(cacheRoot, sourceHash);
    if (!await stat(cacheDirectory).then(() => true).catch(() => false)
        && await completeCacheExists(legacyCacheDirectory, sourceHash, false)) {
        await rename(legacyCacheDirectory, cacheDirectory);
        await writeFile(
            path.join(cacheDirectory, 'complete.json'),
            JSON.stringify({ sourceHash, pageCount: PAGE_COUNT, schemaVersion: CACHE_SCHEMA_VERSION }),
            { mode: 0o600 },
        );
    }
    if (await completeCacheExists(cacheDirectory, sourceHash, true)) return cacheDirectory;

    const staging = path.join(cacheRoot, `.${sourceHash}-v${CACHE_SCHEMA_VERSION}.staging-${process.pid}`);
    await rm(staging, { recursive: true, force: true });
    await mkdir(staging, { recursive: true, mode: 0o700 });
    const pagesDirectory = path.join(staging, 'yuukee');
    await mkdir(pagesDirectory, { recursive: true, mode: 0o700 });
    try {
        const info = await run('pdfinfo', [sourcePath]);
        const pageCountMatch = info.match(/^Pages:\s+(\d+)\s*$/m);
        if (Number(pageCountMatch?.[1]) !== PAGE_COUNT) {
            throw new Error(`Expected ${PAGE_COUNT} source pages; found ${pageCountMatch?.[1] ?? 'unknown'}.`);
        }
        await run('pdftoppm', ['-jpeg', '-r', '120', '-jpegopt', 'quality=82,optimize=y', sourcePath, path.join(pagesDirectory, 'page')]);
        for (let page = 1; page <= PAGE_COUNT; page += 1) {
            const shortName = `page-${String(page).padStart(2, '0')}.jpg`;
            const fullName = `page-${String(page).padStart(3, '0')}.jpg`;
            await rename(path.join(pagesDirectory, shortName), path.join(pagesDirectory, fullName));
        }
        const extracted = await run('pdftotext', ['-layout', '-enc', 'UTF-8', sourcePath, '-']);
        const extractedPages = extracted.split('\f');
        if (extractedPages.at(-1)?.trim() === '') extractedPages.pop();
        const pages = [];
        for (let pageNumber = 1; pageNumber <= PAGE_COUNT; pageNumber += 1) {
            const imageFile = path.join(pagesDirectory, `page-${String(pageNumber).padStart(3, '0')}.jpg`);
            const imageStat = await stat(imageFile).catch(() => null);
            if (!imageStat?.isFile()) throw new Error(`Rendered image is missing for PDF page ${pageNumber}.`);
            let text = (extractedPages[pageNumber - 1] ?? '').replace(/\u0000/g, '').trim();
            let status = text.length >= 24 ? 'pdf-text' : 'ocr';
            if (status === 'ocr') {
                text = (await run('tesseract', [imageFile, 'stdout', '--psm', '3'])).trim();
            }
            await writeFile(path.join(staging, `page-${String(pageNumber).padStart(3, '0')}.txt`), `${text}\n`, { mode: 0o600 });
            const verified = VERIFIED_PAGE_LABELS.get(pageNumber);
            pages.push({
                id: `pdf-${pageNumber}`,
                pdfPage: pageNumber,
                label: verified?.label ?? `PDF page ${pageNumber} (printed page unknown)`,
                printedPage: verified?.printedPage ?? null,
                group: verified?.group ?? 'unclassified',
                image: `yuukee/page-${String(pageNumber).padStart(3, '0')}.jpg`,
                text,
                status: text.length >= 24 ? status : 'unusable-text',
            });
        }
        const index = {
            pages: orderedPages(pages),
            missingPages: VERIFIED_MISSING_PAGES,
            notes: [
                'The original PDF and page images are the evidence authority; extracted/OCR text can be inaccurate.',
                `Verified gaps in the supplied scan excerpt: ${VERIFIED_MISSING_PAGES.join(', ')}. These do not establish absence from the complete manuscript.`,
                'Entries with printedPage=null have unnumbered, unreadable, or non-Arabic folios; descriptive labels do not assert a printed numeric page.',
                'Appendix lettering is transcribed from the scans: original PDF page 16 is visibly Appendix P; lettering is not normalized to the surrounding sequence.',
            ],
        };
        await writeFile(path.join(staging, 'index.json'), `${JSON.stringify(index)}\n`, { mode: 0o600 });
        await writeFile(
            path.join(staging, 'complete.json'),
            JSON.stringify({ sourceHash, pageCount: PAGE_COUNT, schemaVersion: CACHE_SCHEMA_VERSION }),
            { mode: 0o600 },
        );
        await rm(cacheDirectory, { recursive: true, force: true });
        await rename(staging, cacheDirectory);
    } catch (error) {
        await rm(staging, { recursive: true, force: true });
        throw error;
    }
    return cacheDirectory;
}
async function refreshIndex(cacheDirectory) {
    const indexPath = path.join(cacheDirectory, 'index.json');
    const index = JSON.parse(await readFile(indexPath, 'utf8'));
    index.pages = orderedPages(index.pages.map((page) => {
        const verified = VERIFIED_PAGE_LABELS.get(page.pdfPage);
        return {
            ...page,
            label: verified?.label ?? `PDF page ${page.pdfPage} (printed page unknown)`,
            printedPage: verified?.printedPage ?? null,
            group: verified?.group ?? 'unclassified',
        };
    }));
    index.missingPages = VERIFIED_MISSING_PAGES;
    index.notes = [
        'The original PDF and page images are the evidence authority; extracted/OCR text can be inaccurate.',
        `Verified gaps in the supplied scan excerpt: ${VERIFIED_MISSING_PAGES.join(', ')}. These do not establish absence from the complete manuscript.`,
        'Entries with printedPage=null have unnumbered, unreadable, or non-Arabic folios; descriptive labels do not assert a printed numeric page.',
        'Appendix lettering is transcribed from the scans: original PDF page 16 is visibly Appendix P; lettering is not normalized to the surrounding sequence.',
    ];
    await writeFile(indexPath, `${JSON.stringify(index)}\n`, { mode: 0o600 });
}

export async function publishYuukeeReference(manuscriptRoot, privateRoot, publicationRoot) {
    const sourcePath = await ensureInside(manuscriptRoot, SOURCE_RELATIVE_PATH);
    const digest = createHash('sha256');
    for await (const chunk of createReadStream(sourcePath)) digest.update(chunk);
    const sourceHash = digest.digest('hex');
    if (sourceHash !== VERIFIED_SOURCE_SHA256) {
        throw new Error(`YuKee source SHA-256 ${sourceHash} is not visually verified; update the fixed inventory map before publishing.`);
    }
    const cacheRoot = path.join(privateRoot, CACHE_ROOT_NAME);
    await mkdir(cacheRoot, { recursive: true, mode: 0o700 });
    await chmod(cacheRoot, 0o700);
    const cache = await generateCache(sourcePath, cacheRoot, sourceHash);
    await refreshIndex(cache);
    const referenceRoot = path.join(publicationRoot, 'yuukee');
    await mkdir(referenceRoot, { recursive: true });
    await copyFile(sourcePath, path.join(publicationRoot, REFERENCE_PDF_NAME));
    await copyFile(path.join(cache, 'index.json'), path.join(referenceRoot, 'index.json'));
    for (let page = 1; page <= PAGE_COUNT; page += 1) {
        const padded = String(page).padStart(3, '0');
        await copyFile(path.join(cache, 'yuukee', `page-${padded}.jpg`), path.join(referenceRoot, `page-${padded}.jpg`));
    }
    return { title: 'YuKee · Original research', pdfFile: REFERENCE_PDF_NAME, indexFile: 'yuukee/index.json' };
}
