import { createHash, randomBytes } from 'node:crypto';
import { chmod, mkdir, readFile, realpath, rename, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { publishYuukeeReference } from './publish-yuukee-reference.mjs';
const SYSTEM_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const REPOSITORY_ROOT = path.resolve(SYSTEM_ROOT, '..');
const MANUSCRIPT_ROOT = path.join(REPOSITORY_ROOT, 'manuscript');
const PUBLIC_ROOT = path.join(SYSTEM_ROOT, 'public', 'manuscript-review');
const TOKEN_PATH = path.join(SYSTEM_ROOT, 'storage', 'app', 'private', 'manuscript-review-token');
const ASSET_ROOT = path.join(SYSTEM_ROOT, 'resources');

const GENERATION_ID = 'slice-07-cumulative-bulk-manuscript';
const GENERATION_TITLE = 'Cumulative bulk content · Chapters 1–3 and appendices';
const GENERATION_NOTICE = 'Working cumulative manuscript — not final. Current-source content includes §§1.1–1.6, §§2.1–2.2 and 2.4–2.6, §§3.1–3.5, and available Appendices F/J/N/O. The existing §2.3 project-plan block and IPO remain preserved. Appendix C use-case and D/E DFD figures are omitted for your true copies; new captions and list fields remain deferred. Appendix O RACI table, cached TOC/page fields, footer-font substitution, and Word/Google Docs pagination need manual review.';
const GENERATION_INPUTS = Object.freeze([
    {
        key: 'docx',
        source: `exports/docx-engineering/NEXIAM-${GENERATION_ID}.docx`,
        fileName: `generation/NEXIAM-${GENERATION_ID}.docx`,
        signature: (bytes) => bytes.length >= 4 && bytes.readUInt32LE(0) === 0x04034b50,
    },
    {
        key: 'pdf',
        source: `exports/docx-engineering/preview/NEXIAM-${GENERATION_ID}.pdf`,
        fileName: `generation/NEXIAM-${GENERATION_ID}.pdf`,
        signature: (bytes) => bytes.length >= 5 && bytes.subarray(0, 5).toString('ascii') === '%PDF-',
    },
]);

async function readGenerationDriveReview() {
    let metadataPath;
    try {
        metadataPath = await resolveAllowedFile(MANUSCRIPT_ROOT, 'exports/docx-engineering/drive-review.json');
    } catch (error) {
        if (error.code === 'ENOENT') {
            return undefined;
        }
        throw new Error('Generation Drive review metadata is invalid.');
    }
    let value;
    try {
        value = JSON.parse(await readFile(metadataPath, 'utf8'));
    } catch {
        throw new Error('Generation Drive review metadata is invalid.');
    }
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || typeof value.folderName !== 'string' || !value.folderName.trim()
        || value.status !== 'uploaded' || typeof value.folderUrl !== 'string') {
        throw new Error('Generation Drive review metadata is invalid.');
    }
    let url;
    try {
        url = new URL(value.folderUrl);
    } catch {
        throw new Error('Generation Drive review URL is invalid.');
    }
    if (url.protocol !== 'https:' || url.hostname !== 'drive.google.com'
        || !/^\/drive\/folders\/[A-Za-z0-9_-]+$/.test(url.pathname)
        || url.username || url.password || url.search || url.hash || url.port) {
        throw new Error('Generation Drive review URL is invalid.');
    }
    return { folderName: value.folderName.trim(), folderUrl: url.href, status: 'uploaded' };
}

async function copyGenerationArtifacts(destination) {
    const files = {};
    for (const artifact of GENERATION_INPUTS) {
        const source = await resolveAllowedFile(MANUSCRIPT_ROOT, artifact.source);
        const info = await stat(source);
        const bytes = await readFile(source);
        if (!artifact.signature(bytes)) {
            throw new Error(`Generation artifact has an invalid ${artifact.key.toUpperCase()} signature.`);
        }
        const target = path.join(destination, artifact.fileName);
        await mkdir(path.dirname(target), { recursive: true });
        await writeFile(target, bytes);
        files[artifact.key] = {
            fileName: artifact.fileName,
            bytes: bytes.length,
            sha256: createHash('sha256').update(bytes).digest('hex'),
            mtime: info.mtime.toISOString(),
        };
    }
    const drive = await readGenerationDriveReview();
    return {
        id: GENERATION_ID,
        title: GENERATION_TITLE,
        status: 'Working manuscript slice — not final manuscript',
        notice: GENERATION_NOTICE,
        generatedAt: Object.values(files).map(({ mtime }) => mtime).sort().at(-1),
        files,
        ...(drive ? { drive } : {}),
    };
}


const SECTION_DOCUMENTS = [
    ['chapter-1-1', 'Chapter 1 · Background of the Study', 'sections/01_chapter_1_introduction/01_1_background_of_the_study.md'],
    ['chapter-1-2', 'Chapter 1 · Conceptual Framework', 'sections/01_chapter_1_introduction/01_2_conceptual_framework.md'],
    ['chapter-1-3', 'Chapter 1 · Objectives of the Study', 'sections/01_chapter_1_introduction/01_3_objectives_of_the_study.md'],
    ['chapter-1-3-alternative-1', '1.3 Alternative 1 · Six Research Objectives', 'sections/review_alternatives/01_3_alternative_1_objectives.md'],
    ['chapter-1-4', 'Chapter 1 · Scope and Limitations', 'sections/01_chapter_1_introduction/01_4_scope_and_limitations.md'],
    ['chapter-1-4-alternative-1', '1.4 Alternative 1 · Scope and Limitations', 'sections/review_alternatives/01_4_alternative_1_scope_and_limitations.md'],
    ['chapter-1-5', 'Chapter 1 · Importance of the Study', 'sections/01_chapter_1_introduction/01_5_importance_of_the_study.md'],
    ['chapter-1-6', 'Chapter 1 · Definition of Terms', 'sections/01_chapter_1_introduction/01_6_definition_of_terms.md'],
    ['chapter-2-1', 'Chapter 2 · Research Design', 'sections/02_chapter_2_methodology/02_1_research_design.md'],
    ['chapter-2-2', 'Chapter 2 · Software Development Model', 'sections/02_chapter_2_methodology/02_2_software_model.md'],
    ['chapter-2-2-alternative-1', '2.2 Alternative 1 · Software Development Model', 'sections/review_alternatives/02_2_alternative_1_development_tools.md'],
    ['chapter-2-3', 'Chapter 2 · Project Plan and Assignments', 'sections/02_chapter_2_methodology/02_3_project_plan_and_assignments.md'],
    ['chapter-2-4', 'Chapter 2 · Population and Locale', 'sections/02_chapter_2_methodology/02_4_population_and_locale.md'],
    ['chapter-2-5', 'Chapter 2 · Research Instruments', 'sections/02_chapter_2_methodology/02_5_research_instruments.md'],
    ['chapter-2-5-alternative-1', '2.5 Alternative 1 · Research Instruments', 'sections/review_alternatives/02_5_alternative_1_research_instruments.md'],
    ['chapter-2-6', 'Chapter 2 · Data Analysis', 'sections/02_chapter_2_methodology/02_6_data_analysis.md'],
    ['chapter-2-6-alternative-1', '2.6 Alternative 1 · Data Analysis', 'sections/review_alternatives/02_6_alternative_1_data_analysis.md'],
    ['chapter-3-1', 'Chapter 3 · SO1 Process Analysis', 'sections/03_chapter_3_results_and_discussion/03_1_so1_process_analysis.md'],
    ['chapter-3-2', 'Chapter 3 · SO2 System Requirements and RAD', 'sections/03_chapter_3_results_and_discussion/03_2_so2_system_requirements_rad.md'],
    ['chapter-3-2-alternative-1', '3.2 Alternative 1 · SO2 System Requirements and RAD', 'sections/review_alternatives/03_2_alternative_1_so2_system_requirements_rad.md'],
    ['chapter-3-3a-alternative-1', '3.3A Alternative 1 · SO3 OMR Answer-Sheet Scoring', 'sections/review_alternatives/03_3a_alternative_1_so3_omr_evaluation.md'],
    ['chapter-3-3b-alternative-1', '3.3B Alternative 1 · SO4 Advisory Admission Support', 'sections/review_alternatives/03_3b_alternative_1_so4_admission_support_evaluation.md'],
    ['chapter-3-3c-alternative-1', '3.3C Alternative 1 · SO5 Xiam Admission Inquiry', 'sections/review_alternatives/03_3c_alternative_1_so5_xiam_inquiry_evaluation.md'],
    ['chapter-3-3', 'Chapter 3 · SO3 TAM Evaluation', 'sections/03_chapter_3_results_and_discussion/03_3_so3_tam_evaluation.md'],
    ['chapter-3-3d-alternative-1', '3.3D Alternative 1 · SO6 TAM Evaluation', 'sections/review_alternatives/03_3d_alternative_1_so6_tam_evaluation.md'],
    ['chapter-3-4', 'Chapter 3 · Conclusions and Recommendations', 'sections/03_chapter_3_results_and_discussion/03_4_conclusions_and_recommendations.md'],
    ['chapter-3-4-alternative-1', '3.4 Alternative 1 · Conclusions and Recommendations', 'sections/review_alternatives/03_4_alternative_1_conclusions_and_recommendations.md'],
    ['appendix-c', 'Appendix C · Use-Case Diagram', 'sections/04_appendices/04_appendix_c_use_case_diagram.md'],
    ['appendix-d', 'Appendix D · DFD Level 0', 'sections/04_appendices/04_appendix_d_dfd_level_0.md'],
    ['appendix-e', 'Appendix E · DFD Level 1', 'sections/04_appendices/04_appendix_e_dfd_level_1.md'],
    ['appendix-f', 'Appendix F · Entity-Relationship Diagram', 'sections/04_appendices/04_appendix_f_entity_relationship_diagram.md'],
    ['appendix-j', 'Appendix J · TAM Evaluation Instrument', 'sections/04_appendices/04_appendix_j_tam_evaluation_instrument_sheet.md'],
    ['appendix-n', 'Appendix N · AI Architecture and Privacy Firewall Specification', 'sections/04_appendices/04_appendix_n_ai_architecture_and_privacy_specification.md'],
    ['appendix-o', 'Appendix O · Use-Case Documentation Register', 'sections/04_appendices/04_appendix_o_use_case_activity_validation.md'],
];

const FIGURE_ASSETS = Object.freeze([
    { fileName: 'figure_3_1_evidence_to_process_mapping.png', source: 'V2_RESOURCES/figures/figure_3_1_evidence_to_process_mapping.png', aliases: ['../../V2_RESOURCES/figures/figure_3_1_evidence_to_process_mapping.png'] },
    { fileName: 'figure_3_2_current_state_flowchart.png', source: 'V2_RESOURCES/figures/figure_3_2_current_state_flowchart.png', aliases: ['../../V2_RESOURCES/figures/figure_3_2_current_state_flowchart.png'] },
    { fileName: 'figure_activity_diagram_lifecycle.png', source: 'V2_RESOURCES/figures/figure_activity_diagram_lifecycle.png', aliases: ['../../V2_RESOURCES/figures/figure_activity_diagram_lifecycle.png'] },
]);

export const ALLOWLIST = Object.freeze([
    { id: 'chapter-1-full', title: 'Chapter 1 · Introduction (Full Chapter)', fileName: 'chapter-1-full.md', kind: 'chapter', chapter: 1 },
    { id: 'chapter-2-full', title: 'Chapter 2 · Methodology (Full Chapter)', fileName: 'NEXIAM_Chapter2_Methodology_REVIEW.md', kind: 'chapter', chapter: 2 },
    { id: 'chapter-3-full', title: 'Chapter 3 · Results and Discussion (Full Chapter)', fileName: 'NEXIAM_Chapter3_Results_Discussion_REVIEW.md', kind: 'chapter', chapter: 3 },
    { id: 'chapter-1-full-alternative-1', title: 'Alternative 1 · Chapter 1 · Introduction (Full Chapter)', fileName: 'chapter-1-full-alternative-1.md', kind: 'chapter', chapter: 1, alternative: true },
    { id: 'chapter-2-full-alternative-1', title: 'Alternative 1 · Chapter 2 · Methodology (Full Chapter)', fileName: 'chapter-2-full-alternative-1.md', kind: 'chapter', chapter: 2, alternative: true },
    { id: 'chapter-3-full-alternative-1', title: 'Alternative 1 · Chapter 3 · Results and Discussion (Full Chapter)', fileName: 'chapter-3-full-alternative-1.md', kind: 'chapter', chapter: 3, alternative: true },
    ...SECTION_DOCUMENTS.map(([id, title, source]) => ({ id, title, fileName: `${id}.md`, source, kind: id.startsWith('appendix') ? 'appendix' : 'section' })),
]);

export async function resolveAllowedFile(manuscriptRoot, relativePath) {
    if (path.isAbsolute(relativePath) || relativePath.split(/[\\/]/).some((part) => part === '..' || part === '')) {
        throw new Error(`Invalid allowlisted manuscript path: ${relativePath}`);
    }
    const root = await realpath(manuscriptRoot);
    const candidate = path.join(root, relativePath);
    const resolved = await realpath(candidate);
    if (resolved !== root && !resolved.startsWith(`${root}${path.sep}`)) {
        throw new Error(`Allowlisted file resolves outside manuscript root: ${relativePath}`);
    }
    const info = await stat(resolved);
    if (!info.isFile()) {
        throw new Error(`Allowlisted manuscript path is not a file: ${relativePath}`);
    }
    return resolved;
}

async function readSource(relativePath) {
    return readFile(await resolveAllowedFile(MANUSCRIPT_ROOT, relativePath), 'utf8');
}

function fullChapter(chapter, sections, publicationTime, alternative = false) {
    const content = sections.map((section, index) => {
        const lines = section.trim().split('\n');
        if (/^# \d+\.\d+[A-D]? Alternative 1: /.test(lines[0])) {
            if (chapter === 3 && lines[0].startsWith('# 3.4 Alternative 1: ')) {
                return lines.slice(1).join('\n').trim();
            }
            const heading = lines[0].replace(/^# (\d+\.\d+[A-D]?) Alternative 1: /, '## $1 ');
            let fenced = false;
            const body = lines.slice(1).map((line) => {
                if (line.startsWith('```')) {
                    fenced = !fenced;
                }
                return !fenced && /^#{2,5} /.test(line) ? `#${line}` : line;
            }).join('\n').trim();
            return `${heading}\n\n${body}`;
        }
        if (chapter !== 3 || index === 3) {
            return lines.slice(1).join('\n').trim();
        }
        let fenced = false;
        const body = lines.slice(1).map((line) => {
            if (line.startsWith('```')) {
                fenced = !fenced;
            }
            return !fenced && /^#{2,5} /.test(line) ? `#${line}` : line;
        }).join('\n').trim();
        const heading = `## 3.${index + 1} ${lines[0].replace(/^# /, '')}`;
        return `${heading}\n\n${body}`;
    }).join('\n\n---\n\n');
    const title = ['# Chapter 1: Introduction', '# Chapter 2: Methodology', '# Chapter 3: Results and Discussion'][chapter - 1];
    const note = alternative
        ? `> **Alternative 1 adviser-review draft, prepared ${publicationTime}.** This reading copy substitutes the matching Alternative 1 sections into the chapter and retains other original sections. Bracketed FIGURE, PLATE, TABLE, and EVIDENCE slots reserve insertion locations; pending values and images are not completed research results.\n\n`
        : `> **Review draft, prepared ${publicationTime}.** This reading copy is generated from the canonical section files. Bracketed FIGURE, PLATE, TABLE, and EVIDENCE instructions reserve insertion locations; pending values and images are not research results. Edit the source sections, then regenerate this copy before final integration.\n\n`;
    return `${title}\n\n${note}${content.trim()}\n`;
}
function normalizeChapterImagePaths(markdown, figureAssets) {
    return markdown.replace(/(!\[[^\]]*\]\()(\.\.\/\.\.\/V2_RESOURCES\/figures\/)([^)]+)(\))/g, (match, prefix, _source, fileName, suffix) => {
        const asset = figureAssets.find(({ fileName: approvedName }) => approvedName === fileName);
        return asset ? `${prefix}V2_RESOURCES/figures/${asset.fileName}${suffix}` : match;
    });
}
async function readFigureRegistry() {
    const registry = JSON.parse(await readFile(path.join(ASSET_ROOT, 'manuscript-diagrams.json'), 'utf8'));
    if (!registry || !Array.isArray(registry.assets)) throw new Error('Manuscript diagram registry is invalid.');
    const names = new Set(FIGURE_ASSETS.map(({ fileName }) => fileName));
    return registry.assets.map((asset) => {
        if (!asset || typeof asset.fileName !== 'string' || !/^[A-Za-z0-9_-]+\.png$/.test(asset.fileName)
            || names.has(asset.fileName) || asset.source !== `V2_RESOURCES/figures/${asset.fileName}`
            || !Array.isArray(asset.aliases)
            || !asset.aliases.every((alias) => [asset.source, `../../${asset.source}`].includes(alias))) {
            throw new Error('Manuscript diagram registry contains an invalid or duplicate figure.');
        }
        names.add(asset.fileName);
        return asset;
    });
}


async function copyFigureAssets(destination, figureAssets) {
    const assets = {};
    const figureDimensions = {};
    for (const asset of figureAssets) {
        const source = await resolveAllowedFile(MANUSCRIPT_ROOT, asset.source);
        const bytes = await readFile(source);
        if (bytes.length < 24 || bytes.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a') {
            throw new Error(`Manuscript figure is not a PNG: ${asset.fileName}`);
        }
        const width = bytes.readUInt32BE(16);
        const height = bytes.readUInt32BE(20);
        if (!width || !height || (asset.width !== undefined && asset.width !== width)
            || (asset.height !== undefined && asset.height !== height)) {
            throw new Error(`Manuscript figure dimensions do not match its registry: ${asset.fileName}`);
        }
        await writeFile(path.join(destination, asset.fileName), bytes);
        figureDimensions[asset.fileName] = { width, height };
        for (const alias of [...asset.aliases, asset.source]) {
            assets[alias] = asset.fileName;
        }
    }
    return { assets, figureDimensions };
}

async function readToken() {
    await mkdir(path.dirname(TOKEN_PATH), { recursive: true });
    try {
        const token = (await readFile(TOKEN_PATH, 'utf8')).trim();
        if (!/^[a-f0-9]{48}$/.test(token)) {
            throw new Error('Stored manuscript publication token is malformed.');
        }
        return token;
    } catch (error) {
        if (error.code !== 'ENOENT') {
            throw error;
        }
        const token = randomBytes(24).toString('hex');
        await writeFile(TOKEN_PATH, `${token}\n`, { flag: 'wx', mode: 0o600 });
        await chmod(TOKEN_PATH, 0o600);
        return token;
    }
}

async function publish() {
    const publicationTime = new Date().toISOString();
    const figureAssets = [...FIGURE_ASSETS, ...await readFigureRegistry()];
    const sections = new Map();
    for (const [id, , source] of SECTION_DOCUMENTS) {
        sections.set(id, await readSource(source));
    }

    const docs = [];
    for (const entry of ALLOWLIST) {
        const sourceMarkdown = entry.kind === 'chapter'
            ? fullChapter(
                entry.chapter,
                entry.alternative && entry.chapter === 3
                    ? [
                        sections.get('chapter-3-1'),
                        sections.get('chapter-3-2-alternative-1'),
                        sections.get('chapter-3-3a-alternative-1'),
                        sections.get('chapter-3-3b-alternative-1'),
                        sections.get('chapter-3-3c-alternative-1'),
                        sections.get('chapter-3-3d-alternative-1'),
                        sections.get('chapter-3-4-alternative-1'),
                    ]
                    : SECTION_DOCUMENTS
                        .filter(([, , source]) => source.includes(`/0${entry.chapter}_chapter_`))
                        .map(([id]) => sections.get(entry.alternative && sections.has(`${id}-alternative-1`) ? `${id}-alternative-1` : id)),
                publicationTime,
                entry.alternative,
            )
            : sections.get(entry.id);
        const markdown = entry.kind === 'chapter' && entry.chapter === 3
            ? normalizeChapterImagePaths(sourceMarkdown, figureAssets)
            : sourceMarkdown;
        docs.push({ id: entry.id, title: entry.title, fileName: entry.fileName, kind: entry.kind, alternative: Boolean(entry.alternative), markdown });
        if (entry.kind === 'chapter' && !entry.alternative && entry.chapter !== 1) {
            const readingCopy = await resolveAllowedFile(MANUSCRIPT_ROOT, entry.fileName);
            await writeFile(readingCopy, markdown, 'utf8');
        }
    }

    const token = await readToken();
    const outputDirectory = path.join(PUBLIC_ROOT, token);
    const stagingDirectory = path.join(PUBLIC_ROOT, `.${token}.staging-${process.pid}`);
    await rm(stagingDirectory, { recursive: true, force: true });
    await mkdir(stagingDirectory, { recursive: true });
    try {
        for (const doc of docs) {
            await writeFile(path.join(stagingDirectory, doc.fileName), doc.markdown, 'utf8');
        }
        const generation = await copyGenerationArtifacts(stagingDirectory);
        const { assets, figureDimensions } = await copyFigureAssets(stagingDirectory, figureAssets);
        const reference = await publishYuukeeReference(
            MANUSCRIPT_ROOT,
            path.join(SYSTEM_ROOT, 'storage', 'app', 'private'),
            stagingDirectory,
        );
        // esbuild is Node-only; eager initialization breaks jsdom consumers of the path helpers.
        const { build } = await import('esbuild');
        const readerBundle = await build({
            entryPoints: [path.join(ASSET_ROOT, 'js', 'manuscript-reader.js')],
            bundle: true,
            format: 'iife',
            platform: 'browser',
            target: ['es2022'],
            minify: true,
            write: false,
        });
        await writeFile(path.join(stagingDirectory, 'reader.js'), readerBundle.outputFiles[0].contents);
        await writeFile(path.join(stagingDirectory, 'reader.css'), await readFile(path.join(ASSET_ROOT, 'css', 'manuscript-reader.css')));
        const buildHash = Date.now().toString(36);
        let indexHtml = await readFile(path.join(ASSET_ROOT, 'manuscript-reader.html'), 'utf8');
        indexHtml = indexHtml.replace(/reader\.css(\?v=[^"']*)?/g, `reader.css?v=${buildHash}`);
        indexHtml = indexHtml.replace(/reader\.js(\?v=[^"']*)?/g, `reader.js?v=${buildHash}`);
        await writeFile(path.join(stagingDirectory, 'index.html'), indexHtml, 'utf8');
        const manifest = {
            publishedAt: publicationTime,
            docs: docs.map(({ id, title, fileName, kind, alternative }) => ({
                id,
                title,
                fileName,
                kind,
                alternative: Boolean(alternative || id.includes('-alternative-')),
                baseId: id === 'chapter-3-3d-alternative-1'
                    ? 'chapter-3-3'
                    : /^chapter-3-3[abc]-alternative-1$/.test(id)
                        ? null
                        : id.endsWith('-alternative-1')
                            ? id.replace(/-alternative-1$/, '')
                            : null,
            })),
            assets,
            figureDimensions,
            generation,
            reference,
        };
        await writeFile(path.join(stagingDirectory, 'manifest.json'), `${JSON.stringify(manifest)}\n`);
        await rm(outputDirectory, { recursive: true, force: true });
        await rename(stagingDirectory, outputDirectory);
    } catch (error) {
        await rm(stagingDirectory, { recursive: true, force: true });
        throw error;
    }

    console.log(`Published manuscript snapshot: public/manuscript-review/${token}/`);
    console.log(`Publication time: ${publicationTime}`);
    console.log(`Documents: ${docs.length}`);
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    publish().catch((error) => {
        console.error(`Manuscript publication failed: ${error.message}`);
        process.exitCode = 1;
    });
}
