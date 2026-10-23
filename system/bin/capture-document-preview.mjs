#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { lstat, open, readFile, readdir, realpath, rename, stat, writeFile } from 'node:fs/promises';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const SYSTEM = path.join(ROOT, 'system');
const EXPECTED_CASES = [
    'result-word',
    'result-builder',
    'passing-report-word',
    'course-quota-builder',
];
const OPERATIONAL_REPORT_CASES = [
    'passing-report-operational',
    'course-quota-operational',
];
const REPORT_SOURCE_KINDS = ['docx', 'builder'];
const CAPTURE_TIMEOUT_MS = 20 * 60 * 1000;
const CHILD_SHUTDOWN_GRACE_MS = 5_000;
const SOURCE_TREE_EXCLUSIONS = new Set([
    '.git',
    'bootstrap/cache',
    'docker/jitsi/config',
    'node_modules',
    'public/build',
    'storage',
    'vendor/pestphp/pest/.temp',
]);
const PRECAPTURE_FILES = new Set([
    'compose-reviewed.yaml',
    'isolated-build-manifest.sha256',
    'prebuild-critical-source.sha256',
    'proxy.log',
]);
const CASE_CRITERIA = [
    'index_png',
    'automatic_stage3_preview',
    'preview_transport',
    'pdf_bytes_hash_marker',
    'iframe_http',
    'blob_display_bytes',
    'painted_iframe',
    'inactive_save_reopen_repeat',
    'word_converter',
];
const SOURCE_PATHS = [
    'system/routes/web.php',
    'system/app/Http/Controllers/Setup/ResultSheetTemplateController.php',
    'system/app/Http/Controllers/Setup/ReportTemplateController.php',
    'system/app/Http/Controllers/PrintingController.php',
    'system/app/Http/Requests/ResultSheet/PreviewResultSheetTemplateRequest.php',
    'system/app/ResultSheet/Rendering/ResultSheetPreviewRenderer.php',
    'system/app/DocumentTemplate/DocumentSurfaceRegistry.php',
    'system/app/DocumentTemplate/Rendering/DocumentBlueprintCompiler.php',
    'system/app/DocumentTemplate/Rendering/DocxTemplateFiller.php',
    'system/app/DocumentTemplate/Rendering/LibreOfficeConverter.php',
    'system/resources/js/Components/ResultSheet/ResultSheetTemplateEditor.svelte',
    'system/tests/Support/DocumentPreviewTransportFixture.php',
    'system/tests/browser/document-preview-transport.spec.ts',
    'system/bin/capture-document-preview.mjs',
    'system/tests/browser/document-operational-report.spec.ts',
];
let activeComposeProject = process.env.COMPOSE_PROJECT_NAME || 'nexiam-document-recovery';

function composeCommandArgs(projectName = activeComposeProject) {
    return [
        'compose',
        '-p',
        projectName,
        '-f',
        'compose.yaml',
        '-f',
        'compose.document-recovery.yaml',
        '--profile',
        'document-recovery',
    ];
}

const COMPOSE = composeCommandArgs(activeComposeProject);

function parseArguments(argv) {
    const options = new Map();
    for (let index = 0; index < argv.length; index += 1) {
        const name = argv[index];
        if (name === '--help' || name === '-h') {
            return { help: true };
        }
        if (!['--run-id', '--port', '--artifacts', '--report-source', '--compose-project'].includes(name)) {
            throw new Error(`Unsupported option: ${name}`);
        }
        const value = argv[index + 1];
        if (!value || value.startsWith('--')) {
            throw new Error(`Missing value for ${name}`);
        }
        if (options.has(name)) {
            throw new Error(`Duplicate option: ${name}`);
        }
        options.set(name, value);
        index += 1;
    }

    const runId = options.get('--run-id');
    const port = Number(options.get('--port'));
    const artifacts = options.get('--artifacts');
    if (!runId || !/^[a-z0-9][a-z0-9._-]{0,63}$/i.test(runId) || runId.includes('..')) {
        throw new Error('--run-id must be a short safe identifier.');
    }
    if (!Number.isInteger(port) || port < 1024 || port > 65535) {
        throw new Error('--port must be an explicit TCP port between 1024 and 65535.');
    }
    if (port === 18000) {
        throw new Error('Port 18000 is the shared runtime and is forbidden for this capture.');
    }
    if (!artifacts) {
        throw new Error('--artifacts must name a dedicated test-owned output directory.');
    }
    const reportSource = options.get('--report-source');
    if (reportSource !== undefined && !REPORT_SOURCE_KINDS.includes(reportSource)) {
        throw new Error('--report-source must be exactly docx or builder.');
    }
    const composeProject = options.get('--compose-project') || process.env.COMPOSE_PROJECT_NAME || 'nexiam-document-recovery';

    return {
        runId,
        port,
        artifacts: path.resolve(artifacts),
        reportSource: reportSource ?? null,
        composeProject,
    };
}

function printHelp() {
    console.log(
        'Usage: node system/bin/capture-document-preview.mjs --run-id <id> --port <loopback-port> --artifacts <dedicated-output-dir> [--report-source docx|builder] [--compose-project <project-name>]',
    );
    console.log('The isolated recovery service and raw HTTP relay must already be owned by the parent. Report source requires a matching initialized operational report fixture block.');
}


async function safeOutputPath(directory, runId) {
    const requestedPath = path.resolve(directory);
    const requestedRelative = path.relative(ROOT, requestedPath);
    if (requestedRelative === '' || (!requestedRelative.startsWith(`..${path.sep}`) && requestedRelative !== '..' && !path.isAbsolute(requestedRelative))) {
        throw new Error('Capture artifacts must live outside the repository.');
    }

    const physical = await realpath(requestedPath);
    const physicalRelative = path.relative(ROOT, physical);
    if (physicalRelative === '' || (!physicalRelative.startsWith(`..${path.sep}`) && physicalRelative !== '..' && !path.isAbsolute(physicalRelative))) {
        throw new Error('Capture artifact root resolved inside the repository.');
    }
    if (!(await stat(physical)).isDirectory()) {
        throw new Error('Capture artifact root must be an existing directory created by the parent.');
    }
    await assertUnclaimedOutputDirectory(physical, runId);
    return physical;
}

async function readOwnedArtifact(root, relativePath, encoding = null) {
    const normalized = path.normalize(relativePath);
    if (
        path.isAbsolute(normalized)
        || normalized === '..'
        || normalized.startsWith(`..${path.sep}`)
        || normalized.split(path.sep).some((part) => part === '' || part === '.' || part === '..')
    ) {
        throw new Error('Capture evidence artifact path escapes its owned run directory.');
    }
    const parts = normalized.split(path.sep);
    let currentPath = root;
    for (const [index, part] of parts.entries()) {
        currentPath = path.join(currentPath, part);
        const metadata = await lstat(currentPath);
        if (metadata.isSymbolicLink() || (index < parts.length - 1 && !metadata.isDirectory())) {
            throw new Error('Capture evidence artifact is not a regular file within the owned run directory.');
        }
        if (index === parts.length - 1 && !metadata.isFile()) {
            throw new Error('Capture evidence artifact is not a regular file.');
        }
    }
    return readFile(currentPath, encoding ?? undefined);
}
async function assertUnclaimedOutputDirectory(directory, runId) {
    const entries = await readdir(directory, { withFileTypes: true });
    for (const entry of entries) {
        if (
            !entry.isFile()
            || (
                entry.name !== 'fixture.json'
                && entry.name !== 'drain.json'
                && !PRECAPTURE_FILES.has(entry.name)
            )
        ) {
            throw new Error('Capture output already contains artifacts or an ownership claim; refusing to touch this run.');
        }
    }
    const fixtureEntry = entries.find((entry) => entry.name === 'fixture.json');
    if (fixtureEntry) {
        const manifest = JSON.parse(await readFile(path.join(directory, 'fixture.json'), 'utf8'));
        if (manifest?.run_id !== runId || manifest?.service !== 'document-recovery') {
            throw new Error('The existing fixture manifest belongs to a different or unidentified recovery run.');
        }
    }
    const drainEntry = entries.find((entry) => entry.name === 'drain.json');
    if (drainEntry) {
        const drain = JSON.parse(await readFile(path.join(directory, 'drain.json'), 'utf8'));
        if (
            drain?.run_id !== runId
            || drain.completed !== true
            || drain.pending_count !== 0
            || !Array.isArray(drain.pending_job_ids)
            || drain.pending_job_ids.length !== 0
            || !Array.isArray(drain.failed)
            || drain.failed.length !== 0
            || !Array.isArray(drain.failed_jobs)
            || drain.failed_jobs.length !== 0
        ) {
            throw new Error('The existing bounded drain record is not completed and bound to this exact recovery run.');
        }
    }
}

async function claimOwnership(directory, runId, startedAt) {
    await assertUnclaimedOutputDirectory(directory, runId);
    const lock = await open(path.join(directory, 'run-owner.lock'), 'wx', 0o600);
    try {
        await lock.writeFile(`${JSON.stringify({ run_id: runId, pid: process.pid, started_at: startedAt })}\n`);
        await lock.sync();
    } finally {
        await lock.close();
    }
}

function execCompose(args, { composeProject = activeComposeProject, timeout = 30_000, encoding = 'utf8' } = {}) {
    const result = spawnSync('docker', [...composeCommandArgs(composeProject), ...args], {
        cwd: SYSTEM,
        encoding,
        timeout,
        maxBuffer: 64 * 1024 * 1024,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    if (result.error) {
        throw result.error;
    }
    if (result.status !== 0) {
        const stderr = String(result.stderr ?? '').trim();
        throw new Error(`Isolated recovery command failed (${result.status}): ${stderr || String(result.stdout ?? '').trim()}`);
    }
    return result.stdout;
}

function validFixtureManifest(manifest, runId, port, sourceDigest, buildDigest) {
    if (
        !manifest
        || manifest.schema_version !== 2
        || manifest.run_id !== runId
        || manifest.service !== 'document-recovery'
        || Number(manifest.public_port) !== port
        || manifest.source_digest !== sourceDigest
        || manifest.initial_source_digest !== sourceDigest
        || manifest.build_digest !== buildDigest
        || manifest.initial_build_digest !== buildDigest
        || !Array.isArray(manifest.cases)
    ) {
        throw new Error('The already-served fixture manifest has an unsupported schema, run identity, source digest, build digest, or port.');
    }
    const casesById = new Map();
    for (const item of manifest.cases) {
        if (!item || typeof item !== 'object' || typeof item.id !== 'string' || casesById.has(item.id)) {
            throw new Error('The fixture manifest contains an invalid or duplicate case id.');
        }
        casesById.set(item.id, item);
    }
    const missing = EXPECTED_CASES.filter((id) => !casesById.has(id));
    const unexpected = [...casesById.keys()].filter((id) => !EXPECTED_CASES.includes(id));
    if (missing.length > 0 || unexpected.length > 0 || manifest.cases.length !== EXPECTED_CASES.length) {
        throw new Error(`The fixture manifest must contain only the four agreed cases; missing=${missing.join(',') || 'none'} unexpected=${unexpected.join(',') || 'none'}.`);
    }
    const expectedSurface = {
        'result-word': ['result', 'docx'],
        'result-builder': ['result', 'builder'],
        'passing-report-word': ['passing_report', 'docx'],
        'course-quota-builder': ['course_quota_qualifiers', 'builder'],
    };
    for (const id of EXPECTED_CASES) {
        const item = casesById.get(id);
        const [surface, sourceKind] = expectedSurface[id];
        const template = item.template ?? item;
        const actor = item.actor ?? {};
        if (item.surface !== surface || item.source_kind !== sourceKind) {
            throw new Error(`Fixture case [${id}] has the wrong surface/source kind.`);
        }
        if (!item.marker || !actor.email || !actor.password || !Number.isInteger(actor.id)) {
            throw new Error(`Fixture case [${id}] is missing its source identity, marker, or synthetic staff login.`);
        }
        if (typeof item.libreoffice_available !== 'boolean') {
            throw new Error(`Fixture case [${id}] is missing its LibreOffice availability probe.`);
        }
        if (!Number.isInteger(Number(template.id)) || !template.name || template.is_active !== false) {
            throw new Error(`Fixture case [${id}] must identify an existing inactive template.`);
        }
        if (typeof item.source_sha256 !== 'string' || !/^[a-f0-9]{64}$/i.test(item.source_sha256)) {
            throw new Error(`Fixture case [${id}] must include the current source checksum.`);
        }
        if (sourceKind === 'builder' && !/^[a-f0-9]{64}$/i.test(item.builder_sha256 ?? '')) {
            throw new Error(`Fixture case [${id}] must include its canonical builder checksum.`);
        }
        const expectedListPath = surface === 'result' ? '/setup/result-templates' : `/setup/report-templates/${surface}`;
        if (template.list_url !== expectedListPath) {
            throw new Error(`Fixture case [${id}] must use its real template index route [${expectedListPath}].`);
        }
    }

    return EXPECTED_CASES.map((id) => casesById.get(id));
}

function validOperationalReports(block, runId, sourceKind, previewCases) {
    if (block?.run_id !== runId || block?.source_kind !== sourceKind || !Array.isArray(block.cases)) {
        throw new Error('The initialized operational_reports block must match this run and exact --report-source.');
    }
    if (
        block.cases.length !== OPERATIONAL_REPORT_CASES.length
        || OPERATIONAL_REPORT_CASES.some((id, index) => block.cases[index]?.id !== id)
    ) {
        throw new Error('The operational report block must contain exactly the passing-report and course-quota cases in the agreed order.');
    }
    const previewActorsBySurface = {
        passing_report: previewCases.find((item) => item.surface === 'passing_report')?.actor,
        course_quota_qualifiers: previewCases.find((item) => item.surface === 'course_quota_qualifiers')?.actor,
    };
    for (const item of block.cases) {
        const expectedSurface = item.id === 'passing-report-operational' ? 'passing_report' : 'course_quota_qualifiers';
        const actor = item.actor ?? {};
        const template = item.template ?? {};
        if (
            item.surface !== expectedSurface
            || item.source_kind !== sourceKind
            || !item.marker
            || !Number.isInteger(actor.id)
            || actor.id !== previewActorsBySurface[expectedSurface]?.id
            || actor.email !== previewActorsBySurface[expectedSurface]?.email
            || actor.password !== previewActorsBySurface[expectedSurface]?.password
            || !Number.isInteger(Number(template.id))
            || !template.name
            || template.is_active !== true
            || template.list_url !== `/setup/report-templates/${expectedSurface}`
            || !/^[a-f0-9]{64}$/i.test(item.source_sha256 ?? '')
            || !Number.isInteger(Number(item.academic_cycle_id))
            || !Number.isInteger(Number(item.institution_id))
            || item.program_code !== 'BSIT'
            || !item.expected
        ) {
            throw new Error(`Operational report fixture [${item.id}] is not a complete, selected, authenticated, same-run source identity.`);
        }
    }
    return block.cases;
}

function phpCompatibleJsonString(value) {
    return JSON.stringify(value).replace(/[\u007f-\uffff]/g, (character) => {
        return `\\u${character.charCodeAt(0).toString(16).padStart(4, '0')}`;
    });
}

function sourceOmissionReason(relativePath) {
    if ([...SOURCE_TREE_EXCLUSIONS].some((value) => relativePath === value || relativePath.startsWith(`${value}/`))) {
        return 'excluded directory';
    }

    const basename = path.posix.basename(relativePath);
    if (
        (basename === '.env' || basename.startsWith('.env.'))
        && !['.env.example', '.env.example.write'].includes(basename)
        && relativePath !== '.env'
    ) {
        return 'private environment file';
    }
    if (basename === 'auth.json') {
        return 'private credential file';
    }
    if (relativePath === 'docker/jitsi/.env-provision.lock') {
        return 'private provisioning lock';
    }
    if (relativePath.startsWith('database/') && !relativePath.slice('database/'.length).includes('/') && basename.includes('.sqlite')) {
        return 'private database file';
    }
    return null;
}


async function hashTree(directory, excludedPaths = new Set(), overlays = new Map(), filterSource = false) {
    const files = [];
    const omissions = {};
    const exclusions = [...excludedPaths];
    async function visit(currentDirectory, relativeDirectory = '') {
        const entries = await readdir(currentDirectory, { withFileTypes: true });
        for (const entry of entries) {
            const relativePath = relativeDirectory ? `${relativeDirectory}/${entry.name}` : entry.name;
            const absolutePath = path.join(currentDirectory, entry.name);
            if (overlays.has(relativePath)) {
                const bytes = await readFile(overlays.get(relativePath));
                files.push([relativePath, createHash('sha256').update(bytes).digest('hex')]);
                continue;
            }
            if (exclusions.some((value) => relativePath === value || relativePath.startsWith(`${value}/`))) {
                if (filterSource) {
                    omissions[relativePath] = 'excluded directory';
                }
                continue;
            }
            const omissionReason = filterSource ? sourceOmissionReason(relativePath) : null;
            if (omissionReason) {
                omissions[relativePath] = omissionReason;
                continue;
            }
            if (entry.isSymbolicLink()) {
                continue;
            }
            if (entry.isDirectory()) {
                await visit(absolutePath, relativePath);
            } else if (entry.isFile()) {
                const bytes = await readFile(absolutePath);
                files.push([relativePath, createHash('sha256').update(bytes).digest('hex')]);
            }
        }
    }

    await visit(directory);
    for (const [relativePath, overlayPath] of overlays) {
        if (!files.some(([existingPath]) => existingPath === relativePath)) {
            const bytes = await readFile(overlayPath);
            files.push([relativePath, createHash('sha256').update(bytes).digest('hex')]);
        }
    }
    files.sort((left, right) => Buffer.compare(Buffer.from(left[0]), Buffer.from(right[0])));
    const fileMap = Object.fromEntries(files);
    const canonicalJson = `{${files.map(([relativePath, digest]) => `${phpCompatibleJsonString(relativePath)}:${phpCompatibleJsonString(digest)}`).join(',')}}`;
    return {
        sha256: createHash('sha256').update(canonicalJson).digest('hex'),
        files: fileMap,
        omissions: Object.fromEntries(Object.entries(omissions).sort(([left], [right]) => Buffer.compare(Buffer.from(left), Buffer.from(right)))),
    };
}

async function effectiveBuildDirectory() {
    const configuredDirectory = process.env.DOCUMENT_RECOVERY_BUILD_DIR;
    const expectedDigest = process.env.DOCUMENT_RECOVERY_BUILD_DIGEST;
    if (!configuredDirectory || !/^[a-f0-9]{64}$/i.test(expectedDigest ?? '')) {
        throw new Error('DOCUMENT_RECOVERY_BUILD_DIR and a 64-character DOCUMENT_RECOVERY_BUILD_DIGEST are required.');
    }
    const directory = await realpath(path.resolve(configuredDirectory));
    const relativeToRoot = path.relative(ROOT, directory);
    if (
        relativeToRoot === ''
        || (!relativeToRoot.startsWith(`..${path.sep}`) && relativeToRoot !== '..' && !path.isAbsolute(relativeToRoot))
        || directory === path.join(SYSTEM, 'public', 'build')
    ) {
        throw new Error('The effective recovery build must be an isolated directory outside the repository, not shared public/build.');
    }
    if (!(await stat(directory)).isDirectory()) {
        throw new Error('DOCUMENT_RECOVERY_BUILD_DIR must name an existing build directory.');
    }
    return { directory, expectedDigest: expectedDigest.toLowerCase() };
}

async function hashFile(relativePath) {
    const absolute = path.join(ROOT, relativePath);
    try {
        const bytes = await readFile(absolute);
        return { path: relativePath, sha256: createHash('sha256').update(bytes).digest('hex'), bytes: bytes.length };
    } catch (error) {
        return { path: relativePath, unavailable: error.code ?? error.message };
    }
}

async function sourceManifest(buildDirectory) {
    const syntheticEnvironmentPath = path.join(SYSTEM, 'docker', 'recovery-synthetic.env');
    const syntheticEnvironment = await readFile(syntheticEnvironmentPath, 'utf8');
    if (syntheticEnvironment.trim() !== 'DOCUMENT_RECOVERY_SYNTHETIC_ENV=1') {
        throw new Error('The isolated recovery .env overlay does not contain the exact synthetic marker.');
    }
    const [files, sourceTree, buildTree] = await Promise.all([
        Promise.all(SOURCE_PATHS.map(hashFile)),
        hashTree(SYSTEM, SOURCE_TREE_EXCLUSIONS, new Map([['.env', syntheticEnvironmentPath]]), true),
        hashTree(buildDirectory),
    ]);
    if (!Object.hasOwn(buildTree.files, 'manifest.json')) {
        throw new Error('The effective isolated build is missing public/build/manifest.json.');
    }

    return {
        observed_at: new Date().toISOString(),
        files,
        source_digest: sourceTree.sha256,
        source_file_count: Object.keys(sourceTree.files).length,
        build_digest: buildTree.sha256,
        build_file_count: Object.keys(buildTree.files).length,
        effective_build_directory: buildDirectory,
        effective_source_files: sourceTree.files,
        effective_source_omissions: sourceTree.omissions,
        effective_build_files: buildTree.files,
        sha256: sourceTree.sha256,
    };
}

async function writeJson(filePath, value) {
    const temporary = `${filePath}.${process.pid}.tmp`;
    await writeFile(temporary, `${JSON.stringify(value, null, 2)}\n`, { mode: 0o600 });
    await rename(temporary, filePath);
}

async function readFixtureManifest(runId, port, sourceDigest, buildDigest, artifactRoot, reportSource) {
    const remoteContents = execCompose(['exec', '-T', 'document-recovery', 'cat', '/evidence/fixture.json']);
    const localContents = await readFile(path.join(artifactRoot, 'fixture.json'), 'utf8');
    if (localContents !== remoteContents) {
        throw new Error('The host fixture is not the exact manifest already served from /evidence/fixture.json.');
    }
    const manifest = JSON.parse(remoteContents);
    const cases = validFixtureManifest(manifest, runId, port, sourceDigest, buildDigest);
    const operationalReportCases = manifest.operational_reports ?? null;
    if (reportSource !== null && operationalReportCases === null) {
        throw new Error('The exact --report-source was not initialized in the already-served operational_reports block.');
    }
    const selectedOperationalReports = reportSource === null
        ? null
        : validOperationalReports(operationalReportCases, runId, reportSource, cases);
    return { raw: remoteContents, manifest, cases, operationalReportCases, selectedOperationalReports };
}

async function assertObservationalPreflight(expectedManifest) {
    execCompose(['exec', '-T', 'document-recovery', 'php', 'bin/document-recovery.php', 'preflight']);
    const after = execCompose(['exec', '-T', 'document-recovery', 'cat', '/evidence/fixture.json']);
    if (after !== expectedManifest) {
        throw new Error('Observational recovery preflight changed the already-served fixture manifest.');
    }
}

async function assertLoopbackListener(port) {
    await new Promise((resolve, reject) => {
        const socket = net.createConnection({ host: '127.0.0.1', port });
        const timeout = setTimeout(() => {
            socket.destroy();
            reject(new Error(`No owned loopback relay answered on 127.0.0.1:${port}.`));
        }, 1500);
        socket.once('connect', () => {
            clearTimeout(timeout);
            socket.end();
            resolve();
        });
        socket.once('error', (error) => {
            clearTimeout(timeout);
            reject(error);
        });
    });
}

async function runPlaywright({
    testFile,
    runId,
    port,
    artifactRoot,
    manifestPath,
    reportSource = null,
    composeProject = activeComposeProject,
}) {
    const cli = path.join(SYSTEM, 'node_modules', '.bin', 'playwright');
    const output = path.join(artifactRoot, reportSource ? 'operational-report-playwright-output' : 'playwright-output');
    const args = [
        'test',
        testFile,
        '--workers=1',
        '--retries=0',
        '--reporter=line',
        `--output=${output}`,
    ];
    const environment = {
        ...process.env,
        COMPOSE_PROJECT_NAME: composeProject,
        PLAYWRIGHT_BASE_URL: `http://127.0.0.1:${port}`,
        PLAYWRIGHT_START_SERVER: '',
        DOCUMENT_PREVIEW_RUN_ID: runId,
        DOCUMENT_PREVIEW_BASE_URL: `http://127.0.0.1:${port}`,
        DOCUMENT_PREVIEW_ARTIFACTS: artifactRoot,
        DOCUMENT_PREVIEW_MANIFEST: manifestPath,
        DOCUMENT_PREVIEW_PORT: String(port),
        DOCUMENT_PREVIEW_EVIDENCE_CONTAINER_ROOT: '/evidence',
        ...(reportSource ? { DOCUMENT_OPERATIONAL_REPORT_SOURCE: reportSource } : {}),
    };
    const logPrefix = reportSource ? 'operational-report-playwright' : 'playwright';
    const stdoutLog = await open(path.join(artifactRoot, `${logPrefix}.stdout.log`), 'wx', 0o600);
    let stderrLog;
    try {
        stderrLog = await open(path.join(artifactRoot, `${logPrefix}.stderr.log`), 'wx', 0o600);
    } catch (error) {
        await stdoutLog.close();
        throw error;
    }

    return await new Promise((resolve) => {
        let child;
        try {
            child = spawn(cli, args, {
                cwd: SYSTEM,
                env: environment,
                stdio: ['ignore', stdoutLog.fd, stderrLog.fd],
            });
        } catch (error) {
            void Promise.all([stdoutLog.close(), stderrLog.close()]);
            resolve({ status: 127, signal: null, timed_out: false, error: String(error) });
            return;
        }

        let timedOut = false;
        const timeout = setTimeout(() => {
            timedOut = true;
            child.kill('SIGTERM');
            setTimeout(() => child.kill('SIGKILL'), CHILD_SHUTDOWN_GRACE_MS).unref();
        }, CAPTURE_TIMEOUT_MS);

        child.once('error', (error) => {
            clearTimeout(timeout);
            void Promise.all([stdoutLog.close(), stderrLog.close()]);
            resolve({ status: 127, signal: null, timed_out: timedOut, error: String(error) });
        });
        child.once('close', (status, signal) => {
            clearTimeout(timeout);
            void Promise.all([stdoutLog.close(), stderrLog.close()]);
            resolve({ status: status ?? 128, signal, timed_out: timedOut, error: null });
        });
    });
}

async function writeOperationalReportSummary({
    artifactRoot,
    runId,
    reportSource,
    cases,
    playwrightResult,
    sourceIntegrity = null,
    sourceChanged = false,
}) {
    let browserLedger = null;
    let ledgerError = null;
    try {
        browserLedger = JSON.parse(await readFile(path.join(artifactRoot, 'operational-report-ledger.json'), 'utf8'));
        if (
            browserLedger?.run_id !== runId
            || browserLedger?.source_kind !== reportSource
            || !Array.isArray(browserLedger?.cases)
            || browserLedger.cases.length !== OPERATIONAL_REPORT_CASES.length
            || OPERATIONAL_REPORT_CASES.some((id, index) => browserLedger.cases[index]?.id !== id)
        ) {
            throw new Error('The browser operational report ledger does not match the exact run, source kind, or two-case contract.');
        }
    } catch (error) {
        ledgerError = error.message;
    }

    let inspection = null;
    try {
        inspection = JSON.parse(await readFile(path.join(artifactRoot, 'inspection.json'), 'utf8'));
    } catch {
        inspection = null;
    }

    const outcomes = [];
    for (const id of OPERATIONAL_REPORT_CASES) {
        const fixture = cases.find((item) => item.id === id);
        const evidence = browserLedger?.cases?.find((item) => item.id === id);
        const caseDir = path.join(artifactRoot, id);

        let pdfStatus = 'BLOCKED';
        let pdfSha = null;
        let pdfText = '';
        let markerFound = false;
        let rasterBytesLength = 0;
        let pdfFailureReason = null;

        try {
            const pdfBytes = await readFile(path.join(caseDir, 'program-filtered.pdf'));
            if (pdfBytes.length > 0 && pdfBytes.subarray(0, 5).equals(Buffer.from('%PDF-'))) {
                pdfSha = createHash('sha256').update(pdfBytes).digest('hex');
                const textFile = path.join(caseDir, 'program-filtered.txt');
                const rasterPrefix = path.join(caseDir, 'first-page');
                try {
                    spawnSync('pdftotext', ['-layout', path.join(caseDir, 'program-filtered.pdf'), textFile]);
                    pdfText = await readFile(textFile, 'utf8');
                    markerFound = fixture?.marker ? pdfText.toLowerCase().includes(fixture.marker.toLowerCase()) : false;
                } catch {
                    markerFound = false;
                }
                try {
                    spawnSync('pdftoppm', ['-f', '1', '-l', '1', '-singlefile', '-png', '-r', '120', path.join(caseDir, 'program-filtered.pdf'), rasterPrefix]);
                    const raster = await readFile(`${rasterPrefix}.png`);
                    rasterBytesLength = raster.length;
                } catch {
                    rasterBytesLength = 0;
                }
                if (markerFound && rasterBytesLength > 0) {
                    pdfStatus = 'PASS';
                } else {
                    pdfStatus = 'FAIL';
                    pdfFailureReason = !markerFound ? 'Template marker was not found in extracted PDF text.' : 'Raster rendering failed.';
                }
            } else {
                pdfStatus = 'FAIL';
                pdfFailureReason = pdfBytes.length === 0 ? 'Exported PDF is empty.' : 'Exported file lacks PDF signature.';
            }
        } catch (error) {
            pdfStatus = 'BLOCKED';
            pdfFailureReason = error.code === 'ENOENT' ? 'PDF artifact was not produced.' : error.message;
        }

        let docxStatus = 'BLOCKED';
        let docxFailureReason = null;
        try {
            const docxBytes = await readFile(path.join(caseDir, 'program-filtered.docx'));
            if (docxBytes.length > 0 && docxBytes.subarray(0, 4).equals(Buffer.from([0x50, 0x4b, 0x03, 0x04]))) {
                const unzipped = spawnSync('unzip', ['-p', path.join(caseDir, 'program-filtered.docx'), 'word/document.xml'], { encoding: 'utf8' });
                if (unzipped.status === 0 && unzipped.stdout.includes('<w:p') && unzipped.stdout.includes('<w:tbl')) {
                    docxStatus = 'PASS';
                } else {
                    docxStatus = 'FAIL';
                    docxFailureReason = 'Docx missing paragraph or table elements in word/document.xml.';
                }
            } else {
                docxStatus = 'FAIL';
                docxFailureReason = 'Docx artifact is empty or lacks zip magic.';
            }
        } catch (error) {
            docxStatus = 'BLOCKED';
            docxFailureReason = error.code === 'ENOENT' ? 'Docx artifact was not produced.' : error.message;
        }

        let csvStatus = 'BLOCKED';
        let csvFailureReason = null;
        try {
            const csvText = await readFile(path.join(caseDir, 'program-filtered.csv'), 'utf8');
            if (csvText.includes('Reference Code') && csvText.includes('Mara CurrentOne')) {
                csvStatus = 'PASS';
            } else {
                csvStatus = 'FAIL';
                csvFailureReason = 'CSV output missing expected Reference Code or student row.';
            }
        } catch (error) {
            csvStatus = 'BLOCKED';
            csvFailureReason = error.code === 'ENOENT' ? 'CSV artifact was not produced.' : error.message;
        }

        let auditStatus = 'BLOCKED';
        let matchedAudit = null;
        if (inspection !== null && Array.isArray(inspection?.audit_events)) {
            const reportType = id === 'passing-report-operational' ? 'passing-report' : 'course-quota-qualifiers';
            matchedAudit = inspection.audit_events.find((evt) => {
                const p = typeof evt?.payload === 'string' ? JSON.parse(evt.payload) : (evt?.payload ?? {});
                return evt?.event_type === 'reports.export_downloaded'
                    && p.type === reportType
                    && p.format === 'pdf'
                    && Number(p.actor_id) === Number(fixture?.actor?.id)
                    && Number(p.template_id) === Number(fixture?.template?.id)
                    && p.template_source_kind === reportSource
                    && p.render_status === 'success'
                    && p.rendered_pdf_sha256 === pdfSha;
            });
            if (matchedAudit) {
                auditStatus = 'PASS';
            } else {
                auditStatus = 'FAIL';
            }
        } else {
            auditStatus = 'BLOCKED';
        }

        let wrongOfficeStatus = 'BLOCKED';
        if (evidence?.wrong_office_denial !== undefined && evidence?.wrong_office_denial !== null) {
            if (evidence.wrong_office_denial.status === 403 && evidence.wrong_office_denial.rendered_pdf_blocked === true) {
                wrongOfficeStatus = 'PASS';
            } else {
                wrongOfficeStatus = 'FAIL';
            }
        } else {
            wrongOfficeStatus = 'BLOCKED';
        }

        const casePassed = evidence?.outcome === 'PASS'
            && pdfStatus === 'PASS'
            && docxStatus === 'PASS'
            && csvStatus === 'PASS'
            && auditStatus === 'PASS'
            && wrongOfficeStatus === 'PASS';

        const caseFailed = evidence?.outcome === 'FAIL'
            || pdfStatus === 'FAIL'
            || docxStatus === 'FAIL'
            || csvStatus === 'FAIL'
            || auditStatus === 'FAIL'
            || wrongOfficeStatus === 'FAIL';

        outcomes.push({
            id,
            surface: fixture?.surface ?? null,
            source_kind: fixture?.source_kind ?? reportSource,
            template_id: fixture?.template?.id ?? null,
            source_sha256: fixture?.source_sha256 ?? null,
            marker: fixture?.marker ?? null,
            marker_status: markerFound ? 'PASS' : pdfStatus === 'BLOCKED' ? 'BLOCKED' : 'FAIL',
            outcome: casePassed ? 'PASS' : caseFailed ? 'FAIL' : 'BLOCKED',
            pdf: {
                status: pdfStatus,
                sha256: pdfSha,
                marker_found: markerFound,
                raster_bytes: rasterBytesLength,
                ...(pdfFailureReason ? { reason: pdfFailureReason } : {}),
            },
            docx: {
                status: docxStatus,
                ...(docxFailureReason ? { reason: docxFailureReason } : {}),
            },
            csv: {
                status: csvStatus,
                ...(csvFailureReason ? { reason: csvFailureReason } : {}),
            },
            audit: {
                status: auditStatus,
                audit_id: matchedAudit?.id ?? null,
            },
            wrong_office: { status: wrongOfficeStatus },
            evidence,
        });
    }

    const integrityClean = !sourceChanged && sourceIntegrity === 'STABLE_DURING_CAPTURE';

    const allPassed = !ledgerError
        && playwrightResult.status === 0
        && !playwrightResult.timed_out
        && !playwrightResult.error
        && outcomes.length === 2
        && outcomes.every((item) => item.outcome === 'PASS')
        && integrityClean;

    const hasAnyFailure = outcomes.some((item) => item.outcome === 'FAIL')
        || (playwrightResult.status !== 0 && !playwrightResult.timed_out && !ledgerError && outcomes.length === 2);

    const summary = {
        schema_version: 1,
        run_id: runId,
        diagnostic_only: true,
        feature_acceptance: false,
        source_kind: reportSource,
        outcome: allPassed ? 'PASS' : hasAnyFailure ? 'FAIL' : 'BLOCKED',
        source_integrity: sourceIntegrity,
        cases: outcomes,
        side_effects: inspection?.operational_reports?.side_effects ?? null,
        playwright: {
            status: playwrightResult.status,
            signal: playwrightResult.signal ?? null,
            timed_out: playwrightResult.timed_out === true,
            error: playwrightResult.error ?? null,
        },
        ledger_error: ledgerError,
    };
    await writeJson(path.join(artifactRoot, 'operational-report-summary.json'), summary);
    console.log(`Operational report smoke: ${summary.outcome} (${reportSource}); evidence: ${artifactRoot} -> operational-report-summary.json`);
}

async function caseAttemptSource(caseId, attempt) {
    return {
        relativePath: path.join(caseId, `attempt-${attempt}`, 'current.pdf'),
        containerPath: path.posix.join('/evidence', caseId, `attempt-${attempt}`, 'current.pdf'),
    };
}

async function rasterizePdfArtifacts(artifactRoot, fixtureCases, runId, startedAt) {
    let childLedger = null;
    try {
        const candidate = JSON.parse(await readFile(path.join(artifactRoot, 'ledger.json'), 'utf8'));
        if (candidate.run_id === runId && candidate.started_at === startedAt && candidate.driver_initialized !== true) {
            childLedger = candidate;
        }
    } catch {
        childLedger = null;
    }
    const childCases = new Map((Array.isArray(childLedger?.cases) ? childLedger.cases : [])
        .filter((item) => item.run_id === runId && item.started_at === startedAt)
        .map((item) => [item.id, item]));
    const casesById = new Map(fixtureCases.map((item) => [item.id, item]));
    const results = [];
    for (const caseId of EXPECTED_CASES) {
        const fixtureCase = casesById.get(caseId);
        const caseRow = childCases.get(caseId);
        const caseState = recordFrom(caseRow?.state);
        const attempts = Array.isArray(caseRow?.attempt_results) ? caseRow.attempt_results : [];
        for (const attempt of [1, 2]) {
            const attemptResult = attempts.find((item) => Number(item?.attempt) === attempt);
            const source = await caseAttemptSource(caseId, attempt, caseState, attemptResult);
            const relativePath = source?.relativePath ?? path.join(caseId, `attempt-${attempt}`, 'current.pdf');
            const containerPdf = source?.containerPath ?? '';
            const targetBase = path.posix.join('/evidence', caseId, `attempt-${attempt}`, 'iframe-current');
            const hostText = path.join(artifactRoot, caseId, `attempt-${attempt}`, 'iframe-current.txt');
            const hostRaster = path.join(artifactRoot, caseId, `attempt-${attempt}`, 'iframe-current-first-page.png');
            let pdfBytes;
            try {
                if (!caseRow || caseRow.run_id !== runId || caseRow.started_at !== startedAt || !source) {
                    throw new Error('This exact run has no independently captured iframe PDF artifact.');
                }
                pdfBytes = await readOwnedArtifact(artifactRoot, relativePath);
            } catch (error) {
                results.push({
                    case_id: caseId,
                    attempt,
                    pdf: relativePath,
                    status: 'BLOCKED',
                    reason: `Exact-run iframe PDF bytes are missing: ${error.code ?? error.message}`,
                });
                continue;
            }
            if (pdfBytes.length === 0 || !pdfBytes.subarray(0, 5).equals(Buffer.from('%PDF-'))) {
                results.push({
                    case_id: caseId,
                    attempt,
                    pdf: relativePath,
                    bytes: pdfBytes.length,
                    sha256: createHash('sha256').update(pdfBytes).digest('hex'),
                    status: 'FAIL',
                    reason: 'The captured iframe artifact is empty or lacks a PDF signature.',
                });
                continue;
            }
            const sha256 = createHash('sha256').update(pdfBytes).digest('hex');
            const textPath = `${targetBase}.txt`;
            const rasterPrefix = `${targetBase}-first-page`;
            try {
                execCompose(
                    ['exec', '-T', 'document-recovery', 'pdftotext', '-layout', containerPdf, textPath],
                    { timeout: 120_000 },
                );
                execCompose(
                    ['exec', '-T', 'document-recovery', 'pdftoppm', '-f', '1', '-l', '1', '-singlefile', '-png', '-r', '120', containerPdf, rasterPrefix],
                    { timeout: 120_000 },
                );
                const extractedText = await readOwnedArtifact(artifactRoot, path.join(caseId, `attempt-${attempt}`, 'iframe-current.txt'), 'utf8');
                const rasterBytes = await readOwnedArtifact(artifactRoot, path.join(caseId, `attempt-${attempt}`, 'iframe-current-first-page.png'));
                const marker = String(fixtureCase?.marker ?? '');
                const markerFound = marker !== '' && extractedText.toLowerCase().includes(marker.toLowerCase());
                results.push({
                    case_id: caseId,
                    attempt,
                    pdf: relativePath,
                    bytes: pdfBytes.length,
                    sha256,
                    text: path.relative(artifactRoot, hostText),
                    raster: path.relative(artifactRoot, hostRaster),
                    marker,
                    marker_status: markerFound ? 'PASS' : 'FAIL',
                    raster_bytes: rasterBytes.length,
                    status: markerFound && rasterBytes.length > 0 ? 'PASS' : 'FAIL',
                });
            } catch (error) {
                results.push({
                    case_id: caseId,
                    attempt,
                    pdf: relativePath,
                    bytes: pdfBytes.length,
                    sha256,
                    status: 'BLOCKED',
                    reason: error.message,
                });
            }
        }
    }
    return results;
}

function defaultCaseCriteria(id) {
    return {
        index_png: 'BLOCKED',
        automatic_stage3_preview: 'BLOCKED',
        preview_transport: 'BLOCKED',
        pdf_bytes_hash_marker: 'BLOCKED',
        iframe_http: id === 'result-word' ? 'BLOCKED' : 'NOT_APPLICABLE',
        blob_display_bytes: id === 'result-word' ? 'NOT_APPLICABLE' : 'BLOCKED',
        painted_iframe: 'BLOCKED',
        inactive_save_reopen_repeat: 'BLOCKED',
        word_converter: id === 'result-word' || id === 'passing-report-word' ? 'BLOCKED' : 'NOT_APPLICABLE',
    };
}

function initialAttemptResult(id, attempt, reason, runId, startedAt) {
    const criteria = defaultCaseCriteria(id);
    return {
        run_id: runId,
        started_at: startedAt,
        attempt,
        outcome: 'BLOCKED',
        criteria: {
            automatic_stage3_preview: criteria.automatic_stage3_preview,
            preview_transport: criteria.preview_transport,
            pdf_bytes_hash_marker: criteria.pdf_bytes_hash_marker,
            iframe_http: criteria.iframe_http,
            blob_display_bytes: criteria.blob_display_bytes,
            painted_iframe: criteria.painted_iframe,
            word_converter: criteria.word_converter,
        },
        state: {},
        errors: [reason],
    };
}

function initialCase(id, reason, runId, startedAt) {
    return {
        id,
        run_id: runId,
        started_at: startedAt,
        outcome: 'BLOCKED',
        criteria: defaultCaseCriteria(id),
        attempt_results: [
            initialAttemptResult(id, 1, reason, runId, startedAt),
            initialAttemptResult(id, 2, reason, runId, startedAt),
        ],
        state: {},
        errors: [reason],
    };
}

function initialBlockedLedger(runId, startedAt, reason) {
    return {
        schema_version: 1,
        run_id: runId,
        started_at: startedAt,
        diagnostic_only: true,
        feature_acceptance: false,
        driver_initialized: true,
        outcome: 'BLOCKED',
        reason,
        cases: EXPECTED_CASES.map((id) => initialCase(id, reason, runId, startedAt)),
    };
}

function recordFrom(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function criterionStatus(value, fallback = 'BLOCKED') {
    return ['PASS', 'FAIL', 'BLOCKED', 'PENDING', 'NOT_APPLICABLE'].includes(value) ? value : fallback;
}

function attemptStage(attemptResult, attempt) {
    const state = recordFrom(attemptResult.state);
    return state.iframe_pdf ? state : recordFrom(state[`attempt_${attempt}`] ?? state);
}

async function verifyAttemptPaint(artifactRoot, caseId, attempt, attemptResult) {
    if (attemptResult.criteria.painted_iframe !== 'PASS') {
        return attemptResult.criteria.painted_iframe === 'FAIL' ? 'FAIL' : 'BLOCKED';
    }
    const stage = attemptStage(attemptResult, attempt);
    const expectedScreenshot = path.join(`attempt-${attempt}`, 'stage3-painted-iframe.png');
    if (
        stage.screenshot !== expectedScreenshot
        || stage.paint?.status !== 'PASS'
        || stage.paint?.visible_content !== true
        || Number(stage.paint?.dark_pixels) < 48
        || Number(stage.paint?.contrast_edges) < 40
    ) {
        return 'BLOCKED';
    }
    try {
        const screenshot = await readOwnedArtifact(artifactRoot, path.join(caseId, expectedScreenshot));
        return screenshot.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))
            ? 'PASS'
            : 'FAIL';
    } catch {
        return 'BLOCKED';
    }
}

async function verifyAttemptPdf(artifactRoot, caseId, attempt, caseState, attemptResult) {
    const rawStatus = attemptResult.criteria.pdf_bytes_hash_marker;
    if (rawStatus === 'FAIL') {
        return { status: 'FAIL', sha256: null };
    }
    if (!['PENDING', 'PASS'].includes(rawStatus)) {
        return { status: 'BLOCKED', sha256: null };
    }
    const stage = attemptStage(attemptResult, attempt);
    const iframePdf = recordFrom(stage.iframe_pdf);
    const source = await caseAttemptSource(caseId, attempt, caseState, attemptResult);
    if (!source) {
        return { status: 'BLOCKED', sha256: null };
    }
    if (
        caseId !== 'result-word'
        && String(iframePdf.artifact ?? '').split(path.sep).join('/') !== path.posix.join(`attempt-${attempt}`, 'current.pdf')
    ) {
        return { status: 'BLOCKED', sha256: null };
    }
    let pdfBytes;
    try {
        pdfBytes = await readOwnedArtifact(artifactRoot, source.relativePath);
    } catch {
        return { status: 'BLOCKED', sha256: null };
    }
    if (pdfBytes.length === 0 || !pdfBytes.subarray(0, 5).equals(Buffer.from('%PDF-'))) {
        return { status: 'FAIL', sha256: createHash('sha256').update(pdfBytes).digest('hex') };
    }
    const sha256 = createHash('sha256').update(pdfBytes).digest('hex');
    if (caseId === 'result-word') {
        const httpState = recordFrom(
            caseState[`attempt_${attempt}_result_word_http`]
            ?? attemptResult.state?.result_word_http
            ?? attemptResult.state?.[`attempt_${attempt}_result_word_http`]
            ?? attemptResult.state,
        );
        const response = recordFrom(httpState.iframe_http_response);
        const jobId = Number(httpState.job_id);
        const requesterDownloadPass = httpState.requester_status_download_checksum_pass === true
            && httpState.downloaded_pdf_signature === true
            && httpState.downloaded_sha256 === sha256
            && httpState.current_pdf_artifact === path.posix.join(`attempt-${attempt}`, 'current.pdf')
            && httpState.expected_file_checksum === sha256;
        if (
            Number(response.status) !== 200
            || !String(response.content_type ?? '').toLowerCase().includes('application/pdf')
            || !Number.isInteger(jobId)
            || response.pathname !== `/printing/jobs/${jobId}/download`
            || !requesterDownloadPass
            || httpState.iframe_http_checksum_pass !== true
        ) {
            return { status: 'BLOCKED', sha256 };
        }
    } else if (
        Number(iframePdf.status) !== 200
        || !String(iframePdf.content_type ?? '').toLowerCase().includes('application/pdf')
        || Number(iframePdf.bytes) !== pdfBytes.length
        || iframePdf.sha256 !== sha256
        || stage.iframe_matches_latest_preview !== true
        || stage.preview_response_pdf_sha256 !== sha256
        || attemptResult.criteria.blob_display_bytes !== 'PASS'
    ) {
        return { status: 'BLOCKED', sha256 };
    }
    return { status: 'PASS', sha256 };
}

function aggregateAttemptCriterion(caseId, criterion, attemptResults, caseStatus) {
    const expected = criterion === 'iframe_http'
        ? (caseId === 'result-word' ? 'PASS' : 'NOT_APPLICABLE')
        : criterion === 'blob_display_bytes'
            ? (caseId === 'result-word' ? 'NOT_APPLICABLE' : 'PASS')
            : criterion === 'word_converter'
                ? (caseId === 'result-word' || caseId === 'passing-report-word' ? 'PASS' : 'NOT_APPLICABLE')
                : 'PASS';
    const statuses = attemptResults.map((item) => item.criteria[criterion]);
    if (statuses.includes('FAIL') || caseStatus === 'FAIL') {
        return 'FAIL';
    }
    if (statuses.every((status) => status === expected) && caseStatus === expected) {
        return expected;
    }
    if (statuses.includes('PENDING') || caseStatus === 'PENDING') {
        return 'PENDING';
    }
    return 'BLOCKED';
}

function changedMapPaths(before, after) {
    const names = new Set([...Object.keys(before ?? {}), ...Object.keys(after ?? {})]);
    return [...names].filter((name) => before?.[name] !== after?.[name]).sort();
}

async function servedBuildAssetDrift(browserLedger, effectiveBuildFiles, port) {
    const problems = [];
    const caseAssets = (Array.isArray(browserLedger?.cases) ? browserLedger.cases : [])
        .filter((item) => item?.run_id === browserLedger.run_id && EXPECTED_CASES.includes(item.id))
        .flatMap((item) => Array.isArray(item.served_assets) ? item.served_assets : []);
    const rootAssets = Array.isArray(browserLedger?.served_asset_manifest) ? browserLedger.served_asset_manifest : [];
    const servedAssets = caseAssets.length > 0 ? caseAssets : rootAssets;
    const buildAssets = servedAssets.filter((asset) => {
        try {
            return new URL(String(asset.url)).pathname.startsWith('/build/');
        } catch {
            return false;
        }
    });
    if (buildAssets.length === 0) {
        problems.push('No current /build/ JavaScript or CSS response hashes were captured.');
    }
    for (const asset of buildAssets) {
        let assetUrl;
        let relativePath;
        try {
            assetUrl = new URL(String(asset.url));
            relativePath = decodeURIComponent(assetUrl.pathname.slice('/build/'.length));
        } catch {
            problems.push('A served build asset has an invalid URL/path.');
            continue;
        }
        if (
            assetUrl.origin !== `http://127.0.0.1:${port}`
            || Number(asset.status) !== 200
            || Number(asset.bytes) <= 0
            || !/^[a-f0-9]{64}$/i.test(String(asset.sha256 ?? ''))
            || effectiveBuildFiles[relativePath] !== asset.sha256
        ) {
            problems.push(`Served build asset does not match the effective isolated build: ${assetUrl.pathname}`);
        }
    }
    return problems;
}

async function evaluateSourceIntegrity({
    buildConfiguration,
    beforeSourceManifest,
    afterSourceManifest,
    childLedger,
    port,
}) {
    const changedSourceFiles = beforeSourceManifest && afterSourceManifest
        ? changedMapPaths(beforeSourceManifest.effective_source_files, afterSourceManifest.effective_source_files)
        : [];
    const changedBuildFiles = beforeSourceManifest && afterSourceManifest
        ? changedMapPaths(beforeSourceManifest.effective_build_files, afterSourceManifest.effective_build_files)
        : [];
    const selectedSourceChanges = beforeSourceManifest && afterSourceManifest
        ? beforeSourceManifest.files
            .filter((before, index) => JSON.stringify(before) !== JSON.stringify(afterSourceManifest.files[index]))
            .map((file) => file.path)
        : [];
    const browserAssetChanges = (Array.isArray(childLedger?.asset_changes) ? childLedger.asset_changes : [])
        .filter((change) => {
            try {
                return new URL(String(change.url)).pathname.startsWith('/build/');
            } catch {
                return false;
            }
        });
    const servedAssetProblems = await servedBuildAssetDrift(
        childLedger,
        beforeSourceManifest?.effective_build_files ?? {},
        port,
    );
    const expectedDigestMismatch = !buildConfiguration
        || !beforeSourceManifest
        || !afterSourceManifest
        || beforeSourceManifest.source_digest !== afterSourceManifest.source_digest
        || beforeSourceManifest.build_digest !== afterSourceManifest.build_digest
        || beforeSourceManifest.build_digest !== buildConfiguration?.expectedDigest
        || afterSourceManifest.build_digest !== buildConfiguration?.expectedDigest;
    const sourceChanged = expectedDigestMismatch
        || changedSourceFiles.length > 0
        || changedBuildFiles.length > 0
        || browserAssetChanges.length > 0
        || servedAssetProblems.length > 0;
    const sourceIntegrity = expectedDigestMismatch ? 'SOURCE_CHANGED' : sourceChanged ? 'SOURCE_CHANGED' : 'STABLE_DURING_CAPTURE';

    return {
        sourceIntegrity,
        sourceChanged,
        expectedDigestMismatch,
        changedSourceFiles,
        changedBuildFiles,
        selectedSourceChanges,
        browserAssetChanges,
        servedAssetProblems,
    };
}


async function summarize({
    artifactRoot,
    runId,
    startedAt,
    buildConfiguration,
    beforeSourceManifest,
    playwrightResult,
    inspectOutput,
    rasterResults,
    driverErrors,
    port,
    preflightError,
    afterSourceManifest = null,
    childLedger = null,
    integrity = null,
}) {
    if (!afterSourceManifest) {
        try {
            if (!buildConfiguration) {
                throw new Error('The effective isolated build configuration is unavailable.');
            }
            afterSourceManifest = await sourceManifest(buildConfiguration.directory);
        } catch (error) {
            driverErrors.push(`Could not hash effective source/build after capture: ${error.message}`);
        }
    }
    let ledgerError = null;
    if (!childLedger) {
        try {
            const candidate = JSON.parse(await readFile(path.join(artifactRoot, 'ledger.json'), 'utf8'));
            if (
                candidate.run_id === runId
                && candidate.started_at === startedAt
                && candidate.schema_version === 1
                && candidate.driver_initialized !== true
            ) {
                childLedger = candidate;
            } else if (candidate.driver_initialized === true) {
                ledgerError = 'The browser did not replace the driver-initialized ledger with an exact-run checkpoint.';
            } else {
                ledgerError = 'Browser ledger run identity, timestamp, or schema does not match this owned capture.';
            }
        } catch {
            ledgerError = 'Browser ledger was not produced by this owned capture.';
        }
        if (ledgerError) {
            driverErrors.push(ledgerError);
        }
    }

    const browserCases = new Map();
    const childCases = Array.isArray(childLedger?.cases) ? childLedger.cases : [];
    const caseCounts = new Map();
    for (const item of childCases) {
        if (item && typeof item.id === 'string') {
            caseCounts.set(item.id, (caseCounts.get(item.id) ?? 0) + 1);
        }
    }
    const caseRowsValid = childCases.length === EXPECTED_CASES.length
        && EXPECTED_CASES.every((id) => caseCounts.get(id) === 1)
        && childCases.every((item) => item && item.run_id === runId && item.started_at === startedAt);
    if (childLedger && !caseRowsValid) {
        driverErrors.push('Browser ledger did not contain exactly one correctly identified row for each of the four current-run cases.');
    }
    for (const item of childCases) {
        if (
            caseRowsValid
            && EXPECTED_CASES.includes(item.id)
            && item.run_id === runId
        ) {
            browserCases.set(item.id, item);
        }
    }
    const rasterByAttempt = new Map(rasterResults.map((item) => [`${item.case_id}:${item.attempt}`, item]));
    const cases = [];
    for (const id of EXPECTED_CASES) {
        const rawCase = browserCases.get(id);
        const caseErrors = Array.isArray(rawCase?.errors) ? rawCase.errors.map(String) : [];
        const rawCriteria = recordFrom(rawCase?.criteria);
        const criteria = defaultCaseCriteria(id);
        for (const key of CASE_CRITERIA) {
            criteria[key] = criterionStatus(rawCriteria[key], criteria[key]);
        }
        const caseState = recordFrom(rawCase?.state);
        const rawAttempts = Array.isArray(rawCase?.attempt_results) ? rawCase.attempt_results : [];
        const attemptResults = [];
        const attemptProofs = [];
        for (const attempt of [1, 2]) {
            const matchingAttempts = rawAttempts.filter((item) => Number(item?.attempt) === attempt);
            const rawAttempt = matchingAttempts.length === 1 ? matchingAttempts[0] : null;
            const reason = !rawCase
                ? 'The browser did not produce this exact-run case row.'
                : matchingAttempts.length !== 1
                    ? `Attempt ${attempt} is missing or duplicated.`
                    : 'Attempt evidence is incomplete.';
            const baseAttempt = initialAttemptResult(id, attempt, reason, runId, startedAt);
            const attemptCriteria = { ...baseAttempt.criteria };
            const rawAttemptCriteria = recordFrom(rawAttempt?.criteria);
            for (const key of Object.keys(attemptCriteria)) {
                attemptCriteria[key] = criterionStatus(rawAttemptCriteria[key], attemptCriteria[key]);
            }
            const attemptErrors = Array.isArray(rawAttempt?.errors) ? rawAttempt.errors.map(String) : (rawAttempt ? [] : [reason]);
            const attemptResult = {
                run_id: runId,
                started_at: startedAt,
                attempt,
                outcome: criterionStatus(rawAttempt?.outcome, 'BLOCKED'),
                browser_outcome: rawAttempt?.outcome ?? null,
                browser_criteria: { ...rawAttemptCriteria },
                criteria: attemptCriteria,
                state: recordFrom(rawAttempt?.state),
                errors: attemptErrors,
            };
            const paintStatus = await verifyAttemptPaint(artifactRoot, id, attempt, attemptResult);
            if (paintStatus !== 'PASS') {
                attemptResult.criteria.painted_iframe = paintStatus;
            }
            const pdfProof = await verifyAttemptPdf(artifactRoot, id, attempt, caseState, attemptResult);
            const raster = rasterByAttempt.get(`${id}:${attempt}`);
            const matchingPdfHash = pdfProof.status === 'PASS' && raster?.status === 'PASS' && raster.sha256 === pdfProof.sha256;
            if (matchingPdfHash) {
                attemptResult.criteria.pdf_bytes_hash_marker = 'PASS';
            } else if (pdfProof.status === 'FAIL' || raster?.status === 'FAIL') {
                attemptResult.criteria.pdf_bytes_hash_marker = 'FAIL';
            } else {
                attemptResult.criteria.pdf_bytes_hash_marker = 'BLOCKED';
            }
            attemptProofs.push({ pdf: matchingPdfHash ? 'PASS' : attemptResult.criteria.pdf_bytes_hash_marker });
            attemptResult.outcome = attemptResult.errors.length > 0
                ? 'BLOCKED'
                : Object.values(attemptResult.criteria).some((status) => status === 'FAIL')
                    ? 'FAIL'
                    : Object.values(attemptResult.criteria).every((status) => status === 'PASS' || status === 'NOT_APPLICABLE')
                        ? 'PASS'
                        : 'BLOCKED';
            attemptResults.push(attemptResult);
            caseErrors.push(...attemptErrors);
        }

        for (const key of ['automatic_stage3_preview', 'preview_transport', 'iframe_http', 'blob_display_bytes', 'painted_iframe', 'word_converter']) {
            criteria[key] = aggregateAttemptCriterion(id, key, attemptResults, criterionStatus(rawCriteria[key], 'BLOCKED'));
        }
        if (attemptProofs.every((proof) => proof.pdf === 'PASS') && criteria.pdf_bytes_hash_marker !== 'FAIL') {
            criteria.pdf_bytes_hash_marker = 'PASS';
        } else if (attemptProofs.some((proof) => proof.pdf === 'FAIL') || criteria.pdf_bytes_hash_marker === 'FAIL') {
            criteria.pdf_bytes_hash_marker = 'FAIL';
        } else {
            criteria.pdf_bytes_hash_marker = 'BLOCKED';
        }
        if (rawCase?.run_id !== runId || rawCase?.started_at !== childLedger?.started_at) {
            caseErrors.push('Case row identity or fixed start time does not match the owned run.');
            for (const key of CASE_CRITERIA) {
                if (criteria[key] === 'PASS') criteria[key] = 'BLOCKED';
            }
        }
        const browserCriteriaComplete = CASE_CRITERIA.every((key) => {
            const status = rawCriteria[key];
            return key === 'pdf_bytes_hash_marker'
                ? status === 'PENDING' || status === 'PASS'
                : status === 'PASS' || status === 'NOT_APPLICABLE';
        });
        const browserAttemptsComplete = attemptResults.every((item) => {
            return item.errors.length === 0
                && ['PASS', 'PENDING'].includes(item.browser_outcome)
                && Object.entries(item.browser_criteria).every(([key, status]) => {
                    return key === 'pdf_bytes_hash_marker'
                        ? status === 'PENDING' || status === 'PASS'
                        : status === 'PASS' || status === 'NOT_APPLICABLE';
                });
        });
        const deferredPdfOnly = rawCase?.outcome === 'PENDING'
            && rawCriteria.pdf_bytes_hash_marker === 'PENDING'
            && browserCriteriaComplete
            && browserAttemptsComplete;
        const browserOutcomeAllowsPass = rawCase?.outcome === 'PASS' || deferredPdfOnly;
        const caseOutcome = caseErrors.length > 0
            ? 'BLOCKED'
            : Object.values(criteria).some((status) => status === 'FAIL')
                ? 'FAIL'
                : browserOutcomeAllowsPass
                    && Object.values(criteria).every((status) => status === 'PASS' || status === 'NOT_APPLICABLE')
                    ? 'PASS'
                    : 'BLOCKED';
        cases.push({
            ...rawCase,
            id,
            run_id: runId,
            started_at: startedAt,
            browser_outcome: rawCase?.outcome ?? null,
            browser_criteria: { ...rawCriteria },
            outcome: caseOutcome,
            criteria,
            attempt_results: attemptResults,
            errors: [...new Set(caseErrors)],
        });
    }

    integrity ??= await evaluateSourceIntegrity({
        buildConfiguration,
        beforeSourceManifest,
        afterSourceManifest,
        childLedger,
        port,
    });
    const {
        sourceIntegrity,
        sourceChanged,
        expectedDigestMismatch,
        changedSourceFiles,
        changedBuildFiles,
        selectedSourceChanges,
        browserAssetChanges,
        servedAssetProblems,
    } = integrity;

    const casesComplete = cases.length === EXPECTED_CASES.length
        && cases.every((item) => item.outcome === 'PASS');
    const deferredOnlyRun = childLedger?.outcome === 'PENDING'
        && cases.length === EXPECTED_CASES.length
        && cases.every((item) => item.outcome === 'PASS'
            && item.browser_outcome === 'PENDING'
            && item.attempt_results.every((attempt) => attempt.browser_outcome === 'PENDING'))
        && (!Array.isArray(childLedger.errors) || childLedger.errors.length === 0)
        && browserAssetChanges.length === 0;
    const childRunPassed = (childLedger?.outcome === 'PASS' || deferredOnlyRun)
        && playwrightResult.status === 0
        && !playwrightResult.timed_out;
    const outcome = !sourceChanged
        && !preflightError
        && driverErrors.length === 0
        && casesComplete
        && childRunPassed
        && inspectOutput.status === 'PASS'
        ? 'PASS'
        : 'BLOCKED';
    const summary = {
        schema_version: 1,
        run_id: runId,
        started_at: startedAt,
        diagnostic_only: true,
        feature_acceptance: false,
        outcome,
        source_integrity: sourceIntegrity,
        source_manifest_before: beforeSourceManifest,
        source_manifest_after: afterSourceManifest,
        source_changed_files: [...new Set([...changedSourceFiles, ...selectedSourceChanges])],
        build_changed_files: changedBuildFiles,
        served_asset_drift: servedAssetProblems,
        served_asset_changes: browserAssetChanges,
        cases,
        rasterization: rasterResults,
        playwright: {
            status: playwrightResult.status,
            signal: playwrightResult.signal ?? null,
            timed_out: playwrightResult.timed_out === true,
            error: playwrightResult.error ?? null,
        },
        inspect_status: inspectOutput.status,
        inspect_output: inspectOutput.text,
        blocked_reason: preflightError,
        errors: [...driverErrors],
        artifact_root: artifactRoot,
    };
    await writeJson(path.join(artifactRoot, 'summary.json'), summary);
    await writeJson(path.join(artifactRoot, 'ledger.json'), summary);
    return summary;
}

function printSummary(summary) {
    console.log(`Document preview transport diagnostic: ${summary.outcome} (feature acceptance: NOT CLAIMED)`);
    for (const item of summary.cases ?? []) {
        const criteria = item.criteria ?? {};
        console.log(`${item.id}: transport=${criteria.preview_transport ?? 'BLOCKED'} paint=${criteria.painted_iframe ?? 'BLOCKED'} save/reopen=${criteria.inactive_save_reopen_repeat ?? 'BLOCKED'} PNG=${criteria.index_png ?? 'BLOCKED'} PDF=${criteria.pdf_bytes_hash_marker ?? 'BLOCKED'}`);
    }
    if (summary.source_integrity === 'SOURCE_CHANGED') {
        console.log(`SOURCE_CHANGED: ${(summary.source_changed_files ?? []).join(', ') || 'served asset manifest differed across cases'}`);
    }
    if (summary.blocked_reason) console.log(`BLOCKED: ${summary.blocked_reason}`);
    console.log(`Evidence: ${summary.artifact_root} -> summary.json`);
}

function runtimeEnvironmentError(options) {
    const runId = process.env.DOCUMENT_RECOVERY_RUN_ID;
    const port = Number(process.env.DOCUMENT_RECOVERY_PUBLIC_PORT);
    const evidenceDirectory = process.env.DOCUMENT_RECOVERY_EVIDENCE_DIR;
    if (runId !== options.runId || port !== options.port || !evidenceDirectory || path.resolve(evidenceDirectory) !== options.artifacts) {
        return 'The inherited DOCUMENT_RECOVERY_RUN_ID, DOCUMENT_RECOVERY_PUBLIC_PORT, and DOCUMENT_RECOVERY_EVIDENCE_DIR must exactly match --run-id, --port, and --artifacts.';
    }

    return null;
}

async function main() {
    const options = parseArguments(process.argv.slice(2));
    if (options.help) {
        printHelp();
        return 0;
    }
    activeComposeProject = options.composeProject;

    const artifactRoot = await safeOutputPath(options.artifacts, options.runId);
    const startedAt = new Date().toISOString();
    await claimOwnership(artifactRoot, options.runId, startedAt);
    const initialLedger = initialBlockedLedger(options.runId, startedAt, 'Capture has not produced browser evidence.');
    await writeJson(path.join(artifactRoot, 'ledger.json'), initialLedger);

    let beforeSourceManifest = null;
    let buildConfiguration = null;
    let playwrightResult = { status: 2, signal: null, timed_out: false, error: null };
    let operationalPlaywright = null;
    let inspectOutput = { status: 'BLOCKED', text: 'Recovery inspection has not run.' };
    let rasterResults = [];
    let manifest = null;
    let fixtureCases = [];
    let operationalReportCases = null;
    let preflightError = runtimeEnvironmentError(options);
    const driverErrors = [];
    try {
        if (preflightError) {
            throw new Error(preflightError);
        }
        buildConfiguration = await effectiveBuildDirectory();
        beforeSourceManifest = await sourceManifest(buildConfiguration.directory);
        await writeJson(path.join(artifactRoot, 'before-source-manifest.json'), beforeSourceManifest);
        if (beforeSourceManifest.build_digest !== buildConfiguration.expectedDigest) {
            throw new Error('The effective isolated build digest does not match DOCUMENT_RECOVERY_BUILD_DIGEST.');
        }
        const fixture = await readFixtureManifest(
            options.runId,
            options.port,
            beforeSourceManifest.source_digest,
            beforeSourceManifest.build_digest,
            artifactRoot,
            options.reportSource,
        );
        manifest = fixture.manifest;
        fixtureCases = fixture.cases;
        operationalReportCases = fixture.selectedOperationalReports;
        await assertObservationalPreflight(fixture.raw);
        await writeJson(path.join(artifactRoot, 'run.json'), {
            schema_version: 1,
            run_id: options.runId,
            started_at: startedAt,
            base_url: `http://127.0.0.1:${options.port}`,
            manifest_schema_version: manifest.schema_version,
            source_digest: manifest.source_digest,
            build_digest: manifest.build_digest,
            effective_build_directory: buildConfiguration.directory,
            case_ids: fixtureCases.map((item) => item.id),
            report_source: options.reportSource,
            compose_project: options.composeProject,
            operational_report_case_ids: operationalReportCases?.map((item) => item.id) ?? [],
            recovery_public_port: process.env.DOCUMENT_RECOVERY_PUBLIC_PORT,
            recovery_evidence_directory: process.env.DOCUMENT_RECOVERY_EVIDENCE_DIR,
        });
        await assertLoopbackListener(options.port);
        playwrightResult = await runPlaywright({
            testFile: 'tests/browser/document-preview-transport.spec.ts',
            runId: options.runId,
            port: options.port,
            artifactRoot,
            manifestPath: path.join(artifactRoot, 'fixture.json'),
            composeProject: options.composeProject,
        });
        if (playwrightResult.timed_out) {
            driverErrors.push(`Playwright exceeded the ${CAPTURE_TIMEOUT_MS / 60_000}-minute capture ceiling.`);
        } else if (playwrightResult.status !== 0) {
            driverErrors.push(`Playwright exited with status ${playwrightResult.status}${playwrightResult.signal ? ` (${playwrightResult.signal})` : ''}.`);
        }
        if (playwrightResult.error) {
            driverErrors.push(`Playwright child error: ${playwrightResult.error}`);
        }
        if (options.reportSource !== null && operationalReportCases !== null) {
            operationalPlaywright = await runPlaywright({
                testFile: 'tests/browser/document-operational-report.spec.ts',
                runId: options.runId,
                port: options.port,
                artifactRoot,
                manifestPath: path.join(artifactRoot, 'fixture.json'),
                reportSource: options.reportSource,
                composeProject: options.composeProject,
            });
            if (operationalPlaywright.status !== 0 || operationalPlaywright.timed_out || operationalPlaywright.error) {
                driverErrors.push(`Operational report Playwright exited with status ${operationalPlaywright.status}.`);
                process.exitCode = 2;
            }
        }
    } catch (error) {
        preflightError ??= error.message;
        driverErrors.push(error?.stack ?? String(error));
        try {
            await writeFile(path.join(artifactRoot, 'driver-error.txt'), `${error?.stack ?? error}
`, { mode: 0o600 });
        } catch (writeError) {
            driverErrors.push(`Could not save driver error log: ${writeError.message}`);
        }
    } finally {
        if (!preflightError) {
            try {
                inspectOutput.text = execCompose(
                    ['exec', '-T', 'document-recovery', 'php', 'bin/document-recovery.php', 'inspect', '--evidence=/evidence'],
                    { composeProject: options.composeProject, timeout: 30_000 },
                ).toString();
                inspectOutput.status = 'PASS';
            } catch (error) {
                inspectOutput.status = 'BLOCKED';
                inspectOutput.text = error.message;
            }
        } else {
            inspectOutput.text = 'Skipped because the inherited run, port, or evidence directory did not match this invocation.';
        }

        let afterSourceManifest = null;
        try {
            if (buildConfiguration) {
                afterSourceManifest = await sourceManifest(buildConfiguration.directory);
            } else {
                throw new Error('The effective isolated build configuration is unavailable.');
            }
        } catch (error) {
            driverErrors.push(`Could not hash effective source/build after capture: ${error.message}`);
        }

        let childLedger = null;
        let ledgerError = null;
        try {
            const candidate = JSON.parse(await readFile(path.join(artifactRoot, 'ledger.json'), 'utf8'));
            if (
                candidate.run_id === options.runId
                && candidate.started_at === startedAt
                && candidate.schema_version === 1
                && candidate.driver_initialized !== true
            ) {
                childLedger = candidate;
            } else if (candidate.driver_initialized === true) {
                ledgerError = 'The browser did not replace the driver-initialized ledger with an exact-run checkpoint.';
            } else {
                ledgerError = 'Browser ledger run identity, timestamp, or schema does not match this owned capture.';
            }
        } catch {
            ledgerError = 'Browser ledger was not produced by this owned capture.';
        }
        if (ledgerError && !options.reportSource) {
            driverErrors.push(ledgerError);
        }

        let integrity = null;
        try {
            integrity = await evaluateSourceIntegrity({
                buildConfiguration,
                beforeSourceManifest,
                afterSourceManifest,
                childLedger,
                port: options.port,
            });
        } catch (error) {
            driverErrors.push(`Could not evaluate source integrity: ${error.message}`);
        }

        if (options.reportSource !== null && operationalReportCases !== null) {
            try {
                await writeOperationalReportSummary({
                    artifactRoot,
                    runId: options.runId,
                    reportSource: options.reportSource,
                    cases: operationalReportCases,
                    playwrightResult: operationalPlaywright ?? { status: 2, signal: null, timed_out: false, error: 'Operational Playwright did not run.' },
                    sourceIntegrity: integrity?.sourceIntegrity ?? 'BLOCKED',
                    sourceChanged: integrity?.sourceChanged ?? true,
                });
            } catch (error) {
                driverErrors.push(`Operational report summary failed: ${error.message}`);
            }
        }

        if (fixtureCases.length === EXPECTED_CASES.length) {
            try {
                rasterResults = await rasterizePdfArtifacts(artifactRoot, fixtureCases, options.runId, startedAt);
            } catch (error) {
                driverErrors.push(`PDF byte/raster inspection failed: ${error.message}`);
            }
        } else {
            rasterResults = EXPECTED_CASES.flatMap((caseId) => [1, 2].map((attempt) => ({
                case_id: caseId,
                attempt,
                pdf: path.join(caseId, `attempt-${attempt}`, 'current.pdf'),
                status: 'BLOCKED',
                reason: 'No validated exact-run fixture manifest was available to bind this PDF artifact.',
            })));
        }
        try {
            await writeFile(path.join(artifactRoot, 'harness-inspect.txt'), `${inspectOutput.text}
`, { mode: 0o600 });
        } catch (error) {
            driverErrors.push(`Could not save recovery inspection output: ${error.message}`);
        }

        let summary;
        try {
            summary = await summarize({
                artifactRoot,
                runId: options.runId,
                startedAt,
                buildConfiguration,
                beforeSourceManifest,
                afterSourceManifest,
                childLedger,
                integrity,
                playwrightResult,
                inspectOutput,
                rasterResults,
                driverErrors,
                port: options.port,
                preflightError,
            });
        } catch (error) {
            const reason = `Capture finalization failed: ${error?.stack ?? String(error)}`;
            driverErrors.push(reason);
            summary = {
                ...initialBlockedLedger(options.runId, startedAt, reason),
                driver_initialized: false,
                source_integrity: integrity?.sourceIntegrity ?? 'BLOCKED',
                source_manifest_before: beforeSourceManifest,
                source_manifest_after: afterSourceManifest,
                rasterization: rasterResults,
                playwright: {
                    status: playwrightResult.status,
                    signal: playwrightResult.signal,
                    timed_out: playwrightResult.timed_out,
                    error: playwrightResult.error,
                },
                inspect_status: inspectOutput.status,
                inspect_output: inspectOutput.text,
                errors: driverErrors,
                artifact_root: artifactRoot,
            };
            await writeJson(path.join(artifactRoot, 'summary.json'), summary);
            await writeJson(path.join(artifactRoot, 'ledger.json'), summary);
        }
        printSummary(summary);
        if (summary.outcome !== 'PASS') process.exitCode = 2;
    }

    return process.exitCode ?? 0;
}

main().catch(async (error) => {
    const message = error?.stack ?? String(error);
    console.error(message);
    process.exitCode = 2;
});
