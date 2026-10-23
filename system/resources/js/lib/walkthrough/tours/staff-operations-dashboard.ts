import type {
    StaffOperationsContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = StaffOperationsContext;

const done: TourDestination = { kind: 'complete' };

export function buildStaffOperationsTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to the Staff Operations Console',
            text: 'Welcome to your admissions command center! This console gives you unified visibility across NEXIAM’s 7-step admission lifecycle—from Step 1: Applications, through testing and scoring, to Step 6: Release Day advising and Step 7: Admission Clearance.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'funnel-concept' } },
        },
        {
            id: 'funnel-concept',
            phase: 'concept',
            title: 'Admission Cycle Road Meter & Pipeline',
            text: 'The operations console tracks applicant flow and cycle velocity across all 7 operational steps—from application intake through examination, scoring, release day, and final clearance.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'operations-welcome-compass' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'metrics-workflow' } },
        },
        {
            id: 'metrics-workflow',
            phase: 'workflow',
            title: 'Department Telemetry & Bottleneck Triage',
            text: 'Track real-time conversion metrics and active queues for your authorized office: monitor examinee attendance, identify unscored exam rosters, and review quota meters to ensure no department falls behind schedule.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'operations-dashboard-summary' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Inter-Office Coordination & Real-Time Sync',
            text: 'All metrics update automatically in real time without manual spreadsheet reconciliation. When your office completes actions in one step, applicants transition smoothly to the next downstream team without delays!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'staff-operations-dashboard',
        revision: 2,
        title: 'Staff Operations & Intake Funnel Guide',
        domain: 'setup',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const staffOperationsTour = buildStaffOperationsTour;
export const staffOperationsDashboardTour = buildStaffOperationsTour;
