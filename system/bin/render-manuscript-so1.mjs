#!/usr/bin/env node
import { spawnSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const systemRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const repositoryRoot = path.resolve(systemRoot, '..');
const figureDirectory = path.join(repositoryRoot, 'manuscript', 'V2_RESOURCES', 'figures');
const d2 = process.env.D2_BIN || path.join(process.env.HOME || '', '.local', 'bin', 'd2');
const figures = [
  'figure_3_1_evidence_to_process_mapping',
  'figure_3_2_current_state_flowchart',
  'figure_activity_diagram_lifecycle',
];
const registryPath = path.join(repositoryRoot, 'system', 'resources', 'manuscript-diagrams.json');
const registry = JSON.parse(await readFile(registryPath, 'utf8'));
for (const asset of registry.assets) {
  figures.push(path.basename(asset.fileName, path.extname(asset.fileName)));
}

for (const figure of figures) {
  const source = path.join(figureDirectory, `${figure}.d2`);
  for (const extension of ['svg', 'png']) {
    const output = path.join(figureDirectory, `${figure}.${extension}`);
    const result = spawnSync(d2, [
      '--layout=dagre',
      '--pad=24',
      '--scale=1',
      source,
      output,
    ], { cwd: systemRoot, encoding: 'utf8' });

    if (result.error) {
      console.error(`D2 failed for ${source} -> ${output}: ${result.error.message}`);
      process.exit(result.status ?? 1);
    }
    if (result.status !== 0) {
      console.error(`D2 exited with status ${result.status} while rendering ${source} -> ${output}.`);
      if (result.stdout) console.error(`stdout:\n${result.stdout}`);
      if (result.stderr) console.error(`stderr:\n${result.stderr}`);
      process.exit(result.status ?? 1);
    }
  }
}
