import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { compileResearchSessionGuide } from '../../bin/compile-research-session-guide.mjs';

const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const appendixRoot = 'manuscript/sections/04_appendices';
const protocolName = '04_appendix_n_facilitator_protocol_and_orchestration_script.md';
const instrumentName = '04_appendix_j_tam_evaluation_instrument_sheet.md';

function emptySection(source: string, heading: string, nextHeading: string): string {
    const start = source.indexOf(heading) + heading.length;
    const end = source.indexOf(nextHeading, start);
    if (start < heading.length || end < start) throw new Error('Canonical fixture section is absent');
    return source.slice(0, start) + '\n\n---\n\n' + source.slice(end);
}

describe('research publication rejects incomplete participant instructions', () => {
    it.each(['common briefing', 'rating instructions', 'task action'])('rejects missing %s without publishing a substitute', async (missing) => {
        let protocol = await readFile(join(repoRoot, appendixRoot, protocolName), 'utf8');
        let instrument = await readFile(join(repoRoot, appendixRoot, instrumentName), 'utf8');
        if (missing === 'common briefing') {
            protocol = emptySection(protocol, '## 3. Standardized Verbal Briefing', '## 4. Standardized Scenario Task Cards');
        } else if (missing === 'rating instructions') {
            instrument = emptySection(instrument, '#### PART II: EVALUATION INSTRUCTIONS AND RATING BENCHMARK', '#### PART III: EVALUATION QUESTIONNAIRES ACCORDING TO OPERATIONAL ROLE');
        } else {
            protocol = protocol.replace(/^(\| A1 \|)[^|\n]*(\|[^\n]+\|)$/m, '$1 $2');
        }
        const root = await mkdtemp(join(tmpdir(), 'research-publication-'));
        try {
            await mkdir(join(root, appendixRoot), { recursive: true });
            await writeFile(join(root, appendixRoot, protocolName), protocol);
            await writeFile(join(root, appendixRoot, instrumentName), instrument);
            await expect(compileResearchSessionGuide(root)).rejects.toThrow();
        } finally {
            await rm(root, { recursive: true, force: true });
        }
    });
});
