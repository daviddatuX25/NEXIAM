import type {
    ConsultationSchedulingContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ConsultationSchedulingContext;

const done: TourDestination = { kind: 'complete' };

export function buildConsultationSchedulingTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 5: Release Scheduling',
            text: 'Welcome to the Release Scheduling Workspace! In NEXIAM’s 7-step pipeline, you are operating in Step 5: Release Scheduling. Examinees with finalized score profiles from Step 4 arrive here to be assigned dedicated Release Day sessions.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'rostering-concept' } },
        },
        {
            id: 'rostering-concept',
            phase: 'concept',
            title: 'Release Day Sessions & Encounter Capacities',
            text: 'A Release Day session coordinates date, time, on-campus venue, and counselor capacity. Each applicant receives an individual appointment slot within a published session to guarantee dedicated face-to-face advising time.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'consultation-session-list' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'booking-workflow' } },
        },
        {
            id: 'booking-workflow',
            phase: 'workflow',
            title: 'Creating & Publishing Sessions',
            text: 'Click "Schedule Release Day" to configure a new session draft. You can also use "Cohort Auto-Distribute" to balance unassigned examinees across open slots before publishing schedules to applicants.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'consultation-create-session' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Handoff to Step 6: Release Day',
            text: 'Once published, appointments appear in the applicant portal and populate the active Step 6: Release Day counseling queues where academic advising and degree confirmation take place!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'consultation-scheduling',
        revision: 2,
        title: 'Release Day Scheduling Guide',
        domain: 'consultation',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const consultationSchedulingTour = buildConsultationSchedulingTour;
