import { createHash } from 'node:crypto';
import { mkdir, readFile, realpath, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const sourcePaths = {
    appendix_n: 'manuscript/sections/04_appendices/04_appendix_n_facilitator_protocol_and_orchestration_script.md',
    appendix_j: 'manuscript/sections/04_appendices/04_appendix_j_tam_evaluation_instrument_sheet.md',
};

const roleBindings = [
    { key: 'student', scenario: 'A', label: 'Student / applicant', section: 'C', prefix: 'STU', count: 6, matrix: 'FIRST-YEAR COLLEGE STUDENT EVALUATION MATRIX' },
    { key: 'registrar', scenario: 'B', label: 'Registrar', section: 'A', prefix: 'REG', count: 6, matrix: 'REGISTRAR OFFICE PERSONNEL EVALUATION MATRIX' },
    { key: 'guidance', scenario: 'C', label: 'Guidance', section: 'B', prefix: 'GUD', count: 8, matrix: 'GUIDANCE OFFICE PERSONNEL EVALUATION MATRIX' },
    { key: 'academic-leader', scenario: 'D', label: 'Academic leader', section: 'D', prefix: 'LEAD', count: 4, matrix: 'ACADEMIC PROGRAM HEAD OR DEAN EVALUATION MATRIX' },
];

function requiredMatch(source, expression, description) {
    const match = source.match(expression);
    if (!match) {
        throw new Error(`Research guide publication failed: cannot resolve ${description}.`);
    }
    return match;
}

function section(source, start, end, description) {
    const startIndex = source.indexOf(start);
    if (startIndex < 0) {
        throw new Error(`Research guide publication failed: cannot resolve ${description}.`);
    }
    const bodyStart = startIndex + start.length;
    const endIndex = end ? source.indexOf(end, bodyStart) : source.length;
    if (end && endIndex < 0) {
        throw new Error(`Research guide publication failed: cannot resolve end of ${description}.`);
    }
    const body = source.slice(bodyStart, endIndex).trim();
    if (!body) {
        throw new Error(`Research guide publication failed: empty ${description}.`);
    }
    return body;
}

function plainMarkdown(value) {
    return value.trim().replace(/^\*\*(.*?)\*\*$/s, '$1');
}

function scenarioSource(source, role) {
    const heading = `### Scenario ${role.scenario}:`;
    const nextHeading = role.scenario === 'D' ? '\n---\n\n## 5.' : '\n### Scenario ';
    return section(source, heading, nextHeading, `${role.key} scenario`);
}

function parseScenario(source, role) {
    const scenarioBlock = scenarioSource(source, role);
    const title = scenarioBlock.split('\n', 1)[0].trim();
    const context = plainMarkdown(requiredMatch(scenarioBlock, /^\*\*Starting context:\*\* (.+)$/m, `${role.key} starting context`)[1]);
    const briefing = plainMarkdown(requiredMatch(scenarioBlock, /^> "([\s\S]*?)"\s*$/m, `${role.key} standardized scenario briefing`)[1].replace(/\n> ?/g, '\n'));
    const taskRows = [...scenarioBlock.matchAll(/^\|\s*([ABCD]\d+)\s*\|\s*(.*?)\s*\|\s*([A-Z]+(?:-(?:PU|PEOU)-\d{2})(?:,\s*[A-Z]+-(?:PU|PEOU)-\d{2})*)\s*\|\s*$/gm)];
    const tasks = taskRows.map((match) => ({
        id: match[1],
        text: plainMarkdown(match[2]),
        item_ids: match[3].split(/,\s*/),
    }));
    if (tasks.length !== role.count || tasks.some((task, index) => task.id !== `${role.scenario}${index + 1}` || !task.text)) {
        throw new Error(`Research guide publication failed: ${role.scenario} must contain exactly ${role.count} complete ordered tasks.`);
    }
    const taskTableEnd = scenarioBlock.indexOf('\n\n', scenarioBlock.indexOf('|---|'));
    const afterTable = taskTableEnd < 0 ? '' : scenarioBlock.slice(taskTableEnd).trim();
    const restrictions = afterTable.replace(/^\|[^\n]*\n(?:\|[^\n]*\n)*/m, '').trim();
    if (!restrictions) {
        throw new Error(`Research guide publication failed: missing ${role.scenario} task restrictions.`);
    }
    return { ...role, title, context, briefing, restrictions, tasks: tasks.map(({ id, text }) => ({ id, text })) };
}

function extractMatrix(source, role) {
    const heading = `### SECTION ${role.section}: ${role.matrix}`;
    const matrixBlock = section(source, heading, '\n---', `${role.key} Section ${role.section} matrix`);
    const itemRows = [...matrixBlock.matchAll(/^\|\s*\*\*([A-Z]+-(?:PU|PEOU)-\d{2})\*\*\s*\|\s*(.*?)\s*\|/gm)];
    const allowed = new Set(itemRows.map((match) => match[1]));
    if (itemRows.length !== (role.scenario === 'A' ? 9 : role.scenario === 'C' ? 13 : 8)
        || itemRows.some((match) => !match[1].startsWith(`${role.prefix}-`))) {
        throw new Error(`Research guide publication failed: ${role.key} matrix has ${itemRows.length} items or contains a cross-role item.`);
    }
    return { markdown: `${heading}\n\n${matrixBlock}`, allowed };
}

export async function compileResearchSessionGuide(repoRoot) {
    const sources = {};
    const sourceHashes = {};
    for (const [key, relativePath] of Object.entries(sourcePaths)) {
        const sourcePath = await realpath(resolve(repoRoot, relativePath));
        const bytes = await readFile(sourcePath);
        sources[key] = bytes.toString('utf8');
        sourceHashes[key] = createHash('sha256').update(bytes).digest('hex');
    }
    const { appendix_n: appendixN, appendix_j: appendixJ } = sources;
    const roles = roleBindings.map((role) => parseScenario(appendixN, role));
    const totalTasks = roles.reduce((sum, role) => sum + role.tasks.length, 0);
    if (totalTasks !== 24) {
        throw new Error('Research guide publication failed: expected exactly 24 canonical tasks.');
    }

    for (const role of roles) {
        const matrix = extractMatrix(appendixJ, role);
        if (role.tasks.some((task) => {
            const sourceTask = [...scenarioSource(appendixN, role).matchAll(/^\|\s*([ABCD]\d+)\s*\|\s*(.*?)\s*\|\s*([A-Z]+(?:-(?:PU|PEOU)-\d{2})(?:,\s*[A-Z]+-(?:PU|PEOU)-\d{2})*)\s*\|\s*$/gm)].find((row) => row[1] === task.id);
            return !sourceTask || sourceTask[3].split(/,\s*/).some((item) => !matrix.allowed.has(item));
        })) {
            throw new Error(`Research guide publication failed: ${role.key} task items do not map to its assigned questionnaire.`);
        }
    }

    const consent = section(appendixJ, '#### PART I: EVALUATOR PROFILE AND INFORMED CONSENT', '\n---\n\n#### PART II:', 'Appendix J consent');
    const instructions = section(appendixJ, '#### PART II: EVALUATION INSTRUCTIONS AND RATING BENCHMARK', '\n---\n\n#### PART III:', 'Appendix J common instructions');
    const feedback = section(appendixJ, '#### PART IV: QUALITATIVE FEEDBACK AND OPERATIONAL OBSERVATIONS', '\n---', 'Appendix J feedback questions');
    const commonBriefingSection = section(appendixN, '## 3. Standardized Verbal Briefing', '\n---\n\n## 4.', 'standardized common briefing section');
    const standardBriefing = requiredMatch(commonBriefingSection, /^> "([\s\S]*?)"\s*$/m, 'standardized common briefing')[1].replace(/\n> ?/g, '\n');
    const preparation = section(appendixN, '## 2. Preparation and Safety Checks', '\n---\n\n## 3.', 'facilitator preparation checks');
    const observationSection = section(appendixN, '## 5. Observation and Task-Status Procedure', '\n---\n\n## 6.', 'observation status and blank-sheet guidance');
    const rules = section(appendixN, '## 6. Facilitator Rules and Non-Interference', null, 'facilitator rules');
    const statuses = [...observationSection.matchAll(/^- \*\*(Completed|Attempted, not completed|Blocked: technical|Unavailable: fixture|Not permitted|Not attempted):?\*\* (.+)$/gm)];
    if (statuses.length !== 6 || statuses.some((match) => !match[2])) {
        throw new Error('Research guide publication failed: expected all six canonical observation statuses.');
    }
    const blankSheet = requiredMatch(observationSection, /(\| Scenario\/task \| Item IDs \| Status \| Observable outcome or failure \| Assistance \/ interruption record \|\n\|---[^\n]*\|\n(?:\|[^\n]*\|\n?)+)/, 'blank observation sheet')[1];
    const facilitatorRoles = roles.map((role) => ({ ...role, tasks: [...scenarioSource(appendixN, role).matchAll(/^\|\s*([ABCD]\d+)\s*\|\s*(.*?)\s*\|\s*([A-Z]+(?:-(?:PU|PEOU)-\d{2})(?:,\s*[A-Z]+-(?:PU|PEOU)-\d{2})*)\s*\|\s*$/gm)].map((row) => ({ id: row[1], text: plainMarkdown(row[2]), item_ids: row[3].split(/,\s*/) })) }));

    const data = {
        schema: 1,
        roles: roles.map(({ item_ids, ...role }) => role),
        facilitator: {
            preparation_markdown: `## 2. Preparation and Safety Checks\n\n${preparation}`,
            standard_briefing: standardBriefing,
            observation_markdown: `## 5. Observation and Task-Status Procedure\n\n${observationSection.slice(0, observationSection.indexOf('| Scenario/task |')).trim()}\n\n${blankSheet}`,
            rules_markdown: `## 6. Facilitator Rules and Non-Interference\n\n${rules}`,
            roles: facilitatorRoles,
            paired_exchange: { tasks: ['C5', 'D4'], note: 'C5 and D4 are a same-case exchange between Guidance and an explicitly authorized Program Head or Dean. This does not establish a three-party thread or Registrar access.' },
        },
        after_instructions: instructions,
        questionnaires: Object.fromEntries(roleBindings.map((role) => {
            const matrix = extractMatrix(appendixJ, role);
            return [role.key, { id: `section-${role.section.toLowerCase()}`, title: `Appendix J, Section ${role.section}`, markdown: `# Appendix J: Role-Adapted Technology Acceptance Model (TAM) Evaluation Questionnaire\n\n${consent}\n\n${instructions}\n\n${matrix.markdown}\n\n${feedback}` }];
        })),
        source_version: sourceHashes,
    };

    const outputRoot = resolve(repoRoot, 'system/resources/views/capstone/research-session-guide');
    await mkdir(resolve(outputRoot, 'instruments'), { recursive: true });
    await writeFile(resolve(outputRoot, 'research-data.json'), `${JSON.stringify(data, null, 2)}\n`);
    for (const role of roleBindings) {
        await writeFile(resolve(outputRoot, 'instruments', `${role.key}.md`), data.questionnaires[role.key].markdown + '\n');
    }
    return totalTasks;
}
