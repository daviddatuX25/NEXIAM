import type {
    ProgramHeadOverviewContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ProgramHeadOverviewContext;

const done: TourDestination = { kind: 'complete' };

export function buildProgramHeadOverviewTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Department Workspace & Step 6: Release Day',
            text: 'Welcome to your Academic Program Workspace! In NEXIAM’s 7-step admissions pipeline, you operate in Step 6: Release Day. Guidance counselors have evaluated examinees and referred candidates who require departmental academic review directly to your workspace.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'quota-concept' } },
        },
        {
            id: 'quota-concept',
            phase: 'concept',
            title: 'Defending Program Quota Ceilings',
            text: 'Your primary governance duty is balancing academic excellence against approved quota limits. The metrics and quota meters track authorized capacity, 1st-choice demand, and committed placements in real time to prevent departmental over-enrollment.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'program-head-kpis' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'candidate-triage' } },
        },
        {
            id: 'candidate-triage',
            phase: 'workflow',
            title: 'Candidate Rosters & Referral Review',
            text: 'Candidates are segmented into four rosters: Potential (qualified by score), Upcoming (scheduled for encounters), Follow-up (awaiting your departmental decision), and Ready for Enrollment. A red badge alerts you whenever unresolved referrals are pending.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'program-head-candidate-tabs' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'referral-verdicts' } },
        },
        {
            id: 'referral-verdicts',
            phase: 'workflow',
            title: 'The Three Official Departmental Verdicts',
            text: 'For pending referrals, you record one of three official actions: (1) Cleared by Program Head (endorses the student for clearance), (2) Declined by Program Head (returns the applicant to Guidance for re-advising to an alternate degree), or (3) Appearance Requested (summons the applicant for an in-person audition or interview).',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Handoff to Step 7: Admission Clearance',
            text: 'Candidates you mark as "Cleared" advance immediately to Step 7: Admission Clearance at the Registrar’s office. You can also use the Benchmark Studio to adjust admission criteria or open an Internal Discussion thread with Guidance counselors to coordinate on complex cases!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'program-head-overview',
        revision: 2,
        title: 'Program Head & Quota Governance Guide',
        domain: 'consultation',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const programHeadOverviewTour = buildProgramHeadOverviewTour;
