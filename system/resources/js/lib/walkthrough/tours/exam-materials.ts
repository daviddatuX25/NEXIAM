import type {
    ExamMaterialsContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ExamMaterialsContext;

const done: TourDestination = { kind: 'complete' };

export function buildExamMaterialsTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Test Kits',
            text: 'Use Test Kits as the single entry point for exam setup. Find a kit in the list and open it to review its setup context.',
            emotion: 'hi',
            speechMode: 'typewriter',
            target: { dataTour: 'test-kits-list' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'open-kit' } },
        },
        {
            id: 'open-kit',
            phase: 'workflow',
            title: 'Open a Test Kit',
            text: 'Choose Open Kit on a row to enter that kit’s context. Profiles, norms, and related setup belong within Test Kit setup, not a separate materials hub.',
            emotion: 'searching-paper',
            speechMode: 'typewriter',
            target: { dataTour: 'test-kit-row' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Kit-first setup',
            text: 'Use the Test Kits list to locate and open kits. Answer Sheets and Booklets remain available as standalone resource libraries; do not use a separate Exam Materials hub.',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'exam-materials',
        revision: 3,
        title: 'Test Kits Guide',
        domain: 'examinations',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const examMaterialsTour = buildExamMaterialsTour;
