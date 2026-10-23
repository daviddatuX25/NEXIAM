import type {
    ExamSessionsContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ExamSessionsContext;

const done: TourDestination = { kind: 'complete' };

export function buildExamSessionsTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 2: Exam Scheduling',
            text: 'Welcome to the Examination Sessions Hub! In NEXIAM’s 7-step pipeline, you are operating in Step 2: Exam Scheduling. Applicants accepted in Step 1: Applications arrive here to be scheduled into physical test sessions.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'capacity-concept' } },
        },
        {
            id: 'capacity-concept',
            phase: 'concept',
            title: 'Rooms, Shifts, & Capacity Invariants',
            text: 'Each examination session is bound to a physical room, building, and time window (Morning AM or Afternoon PM). NEXIAM strictly enforces room capacity limits to prevent overcrowding and maintain standardized testing conditions.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'sessions-list-container' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'distribution-workflow' } },
        },
        {
            id: 'distribution-workflow',
            phase: 'workflow',
            title: 'Publication & Cohort Auto-Distribution',
            text: "Sessions start in 'Draft' mode while rooms are being coordinated. Once finalized, clicking 'Publish' locks in the schedule and opens the seats for examinee booking. You can also use the 'Cohort Auto-Distribute' tool to automatically distribute unassigned applicants evenly across published sessions without manual data entry!",
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'auto-distribute-button' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Handoff to Step 3: Exam Monitoring',
            text: 'Always ensure room capacities match physical desk counts before publishing. Published sessions automatically integrate into live Exam Monitoring in Step 3 for applicant QR check-in on test day!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'exam-sessions',
        revision: 2,
        title: 'Exam Sessions & Capacity Planning Guide',
        domain: 'examinations',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const examSessionsTour = buildExamSessionsTour;
