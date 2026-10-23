import { createHash } from 'node:crypto';
import { mkdir, readFile, realpath, writeFile } from 'node:fs/promises';
import { dirname, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

import { compileResearchSessionGuide } from './compile-research-session-guide.mjs';
const appRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const repoRoot = resolve(appRoot, '..');
const guideRoot = resolve(appRoot, 'resources/views/capstone/demo-guide');
const manifest = JSON.parse(await readFile(resolve(guideRoot, 'asset-sources.json'), 'utf8'));
const allowedRoots = await Promise.all([
    realpath(resolve(repoRoot, 'openspec/changes/action-core-authority-realignment/use-case-audit/diagrams')),
    realpath(resolve(repoRoot, 'manuscript/sections/04_appendices')),
]);

if (manifest.schema !== 1 || !Array.isArray(manifest.assets)) {
    throw new Error('Unsupported demo-guide asset manifest.');
}

const ids = new Set();
const names = new Set();
const published = [];
await mkdir(resolve(guideRoot, 'assets'), { recursive: true });

for (const asset of manifest.assets) {
    if (!['diagram', 'material'].includes(asset.kind)
        || !/^[A-Za-z0-9-]+$/.test(asset.id)
        || !/^[A-Za-z0-9][A-Za-z0-9._-]*$/.test(asset.name)
        || asset.name.includes('..')
        || ids.has(asset.id)
        || names.has(asset.name)) {
        throw new Error(`Invalid or duplicate demo-guide asset: ${asset.id}`);
    }
    ids.add(asset.id);
    names.add(asset.name);

    const source = await realpath(resolve(repoRoot, asset.source));
    if (!allowedRoots.some((root) => source.startsWith(root + sep))) {
        throw new Error(`Asset source is outside the approved reference directories: ${asset.id}`);
    }
    const bytes = await readFile(source);
    if (asset.kind === 'diagram' && (!asset.name.endsWith('.svg') || !bytes.subarray(0, 512).toString().includes('<svg'))) {
        throw new Error(`Expected an exported SVG diagram: ${asset.id}`);
    }
    if (asset.kind === 'material' && !asset.name.endsWith('.md')) {
        throw new Error(`Expected a Markdown research source: ${asset.id}`);
    }
    await writeFile(resolve(guideRoot, 'assets', asset.name), bytes);
    published.push({
        id: asset.id,
        kind: asset.kind,
        name: asset.name,
        source: asset.source,
        sha256: createHash('sha256').update(bytes).digest('hex'),
        bytes: bytes.length,
    });
}

await writeFile(resolve(guideRoot, 'published-manifest.json'), JSON.stringify({ schema: 1, assets: published }, null, 2) + '\n');
console.log(`Published ${published.length} fixed demo-guide reference snapshots. Original sources were not changed.`);
const researchTasks = await compileResearchSessionGuide(repoRoot);
console.log(`Published canonical research-session guide content (${researchTasks} tasks and four role-scoped instruments).`);
