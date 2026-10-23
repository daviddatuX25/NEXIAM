import type {
    GradingDashboardContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = GradingDashboardContext;

const done: TourDestination = { kind: 'complete' };

export function buildGradingDashboardTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 4: Scoring & OMR',
            text: 'Welcome to the Scoring Dashboard! In NEXIAM’s 7-step pipeline, you are operating in Step 4: Scoring & OMR. Exam sessions closed in Step 3: Exam Monitoring arrive here for score entry, spreadsheet ingestion, and official roster finalization.',
            emotion: 'hi',
            speechMode: 'typewriter',
            target: { dataTour: 'grading-sessions-list' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'session-list' } },
        },
        {
            id: 'session-list',
            phase: 'workflow',
            title: 'Finding & Opening Scoring Sessions',
            text: 'Search or filter the list to find active or pending exam sessions. Open "Scores" to enter raw items or verify converted score profiles. A finalized session is permanently locked to protect score integrity.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'grading-sessions-list' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'import-scores' } },
        },
        {
            id: 'import-scores',
            phase: 'workflow',
            title: 'Batch Score Imports',
            text: 'If your account has score-import authority, use "Import CSV scores" to upload spreadsheet files. The system validates all examinee identifiers against the active roster before staging rows for confirmation.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            when: (ctx) => Boolean(ctx?.canImportScores),
            otherwise: { kind: 'step', stepId: 'guidance' },
            target: { dataTour: 'grading-import-scores' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Finalizing Rosters & Handoff to Step 5',
            text: 'Always review examinee scores before finalization. Once finalized, results are locked and made ready for Step 5: Release Scheduling and Step 6: Release Day counseling encounters!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'assessment-grading-dashboard',
        revision: 3,
        title: 'Assessment Scoring & Quality Control Guide',
        domain: 'examinations',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const gradingDashboardTour = buildGradingDashboardTour;
export const assessmentGradingDashboardTour = buildGradingDashboardTour;
