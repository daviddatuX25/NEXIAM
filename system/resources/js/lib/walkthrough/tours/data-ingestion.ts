import type {
    DataIngestionContext,
    TourDefinition,
    TourDestination,
    TourStep,
} from '../types';

type Ctx = DataIngestionContext;

const done: TourDestination = { kind: 'complete' };

export function buildDataIngestionTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const builder = Boolean(c?.builderOpen);
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: builder
                ? 'Build your import template'
                : 'Prepare, upload, then review',
            text: builder
                ? 'Choose the columns you need before collecting spreadsheet rows. This builder only prepares headers: it does not import applicants or scores.'
                : 'Prepare a template, upload your workbook, then review its saved rows before confirming any records. Applicant intake, exam scores and combined imports keep their own permission and review rules.',
            emotion: 'hi',
            speechMode: 'instant',
            advance: { kind: 'next', destination: done },
        },
    ];

    if (builder) {
        steps.push({
            id: 'template-context',
            phase: 'concept',
            title: 'Choose the right import context',
            text:
                c?.activePreset === 'applicants_only'
                    ? 'Applicant templates follow the selected academic year and semester’s intake policy. Required identity and admission fields stay included. No Test Kit is needed for applicant-only intake.'
                    : 'Score-bearing templates need an exact locked Test Kit version. Use the compatible completed session when importing existing applicants’ scores. The selected setup determines accepted inputs; a numeric value alone does not tell NEXIAM whether it is raw or final.',
            emotion: 'thinking',
            target: { dataTour: 'import-template-context' },
            advance: { kind: 'next', destination: done },
        });
        if (c?.hasTemplateSchema) {
            steps.push({
                id: 'template-fields',
                phase: 'workflow',
                title: 'Pick supported headers',
                text: 'Search by label, exact code or a supported alternate spelling. Required columns are locked; optional columns can be removed. Searching does not remove selected columns. Score columns come from the chosen setup, not a fixed six-area list.',
                emotion: 'searching-paper',
                target: { dataTour: 'import-template-fields' },
                advance: { kind: 'next', destination: done },
            });
            if (c?.hasFieldDetails) {
                steps.push({
                    id: 'template-details',
                    phase: 'concept',
                    title: 'Check meaning, source and format',
                    text: 'Read the field’s source, format and example before filling it. Applicant No. matches an existing NEXIAM applicant reference, such as AY26-00001; it is not automatically a school LRN. Similar-looking score codes are not interchangeable.',
                    emotion: 'thinking',
                    target: { dataTour: 'import-template-details' },
                    advance: { kind: 'next', destination: done },
                });
            }
            steps.push({
                id: 'template-preview',
                phase: 'workflow',
                title: 'Preview, copy or download',
                text: 'The preview is the exact ordered header row. Copy all headers pastes columns into a spreadsheet; Download CSV creates a headers-only file. Neither action creates records. If the policy or scoring setup changes, refresh and review before downloading again.',
                emotion: 'thumbs-up',
                target: { dataTour: 'import-template-preview' },
                advance: { kind: 'next', destination: done },
            });
        } else {
            steps.push({
                id: 'template-prerequisites',
                phase: 'guidance',
                title: 'Resolve the context before choosing columns',
                text: 'The header list is not ready yet. Select the required context, or read the displayed setup or connection error and retry. NEXIAM will not substitute an unrelated kit or a guessed header list.',
                emotion: 'thinking',
                target: { dataTour: 'import-template-context' },
                advance: { kind: 'next', destination: done },
            });
        }
    } else {
        if (c?.hasTemplateEntry) {
            steps.push({
                id: 'template-entry',
                phase: 'workflow',
                title: 'Start with a template',
                text: 'Build import template lets you discover exact headers, inspect their sources and formats, and choose optional columns before downloading. Applicant-only, score-only and combined files use different contracts.',
                emotion: 'searching-paper',
                target: { dataTour: 'import-template-entry' },
                advance: { kind: 'next', destination: done },
            });
        }
        if (c?.hasUploader) {
            steps.push({
                id: 'upload',
                phase: 'workflow',
                title: 'Upload for analysis',
                text: 'Upload CSV, TXT, XLSX or XLS. Analysis checks the workbook and suggests a mode; it is not an import or permission grant. Score imports need the correct completed session and exact Test Kit context.',
                emotion: 'searching-paper',
                target: { dataTour: 'data-ingestion-uploader' },
                advance: { kind: 'next', destination: done },
            });
        }
        if (c?.hasAnalysisResult) {
            steps.push({
                id: 'analysis',
                phase: 'workflow',
                title: 'Check mappings before staging',
                text: 'Check the detected columns, missing fields and worksheet targets. Correct invalid or ambiguous mappings before saving rows for review. Analysis does not confirm that the rows have been committed.',
                emotion: 'thinking',
                target: { dataTour: 'data-ingestion-preview' },
                advance: { kind: 'next', destination: done },
            });
        }
    }

    steps.push({
        id: 'review-boundary',
        phase: 'guidance',
        title: 'Review is separate from commitment',
        text: 'Review valid and invalid saved rows before confirming. A combined import may wait for Registrar consent; Guidance then performs its authorized scoring phase. Failed rows keep explicit outcomes, and retry must not duplicate already committed work. The guide never uploads or commits for you.',
        emotion: 'thumbs-up',
        speechMode: 'instant',
        advance: { kind: 'next', destination: done },
    });
    for (let index = 0; index < steps.length - 1; index += 1) {
        steps[index] = {
            ...steps[index],
            advance: {
                kind: 'next',
                destination: { kind: 'step', stepId: steps[index + 1].id },
            },
        };
    }
    return {
        id: 'data-ingestion',
        revision: 3,
        variant: builder ? 'template' : 'upload',
        title: 'Import template and review guide',
        domain: 'setup',
        persona: 'staff-copilot',
        start: 'welcome',
        allowAskAi: false,
        steps,
    };
}

export const dataIngestionTour = buildDataIngestionTour;
