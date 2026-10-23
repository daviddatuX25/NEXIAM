import type {
    ConsultationHubContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ConsultationHubContext;

const done: TourDestination = { kind: 'complete' };

export function buildConsultationHubTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 6: Release Day',
            text: 'Welcome to the Release Day Workspace! In NEXIAM’s 7-step pipeline, you are operating in Step 6: Release Day. Here, guidance counselors conduct in-person consultations with examinees, review aptitude profiles, and guide degree choices.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'triage-concept' } },
        },
        {
            id: 'triage-concept',
            phase: 'concept',
            title: 'The Release Day Operational Queues',
            text: 'The metrics above segment candidates into operational queues: Scheduled appointments, Walk-in queries, and Follow-ups requiring departmental review. You can quickly filter candidates by session time or priority.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'consultation-metrics-grid' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'advising-workflow' } },
        },
        {
            id: 'advising-workflow',
            phase: 'workflow',
            title: 'Conducting Encounters & Advising Verdicts',
            text: 'Open a candidate file to review their aptitude breakdown, Senior High background, and degree wishlist. Counselors record one of three outcomes: Approve choice, Modify recommendation, or Refer to the Program Head for departmental evaluation.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'release-day-applicants' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Handoff to Step 7: Admission Clearance',
            text: 'Approved advising outcomes commit the official degree placement and advance the applicant to Step 7: Admission Clearance at the Registrar. If an applicant is referred, they transition smoothly to the Program Head’s review queue!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'consultation-hub',
        revision: 3,
        title: 'Release Day Guide',
        domain: 'consultation',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const consultationHubTour = buildConsultationHubTour;
