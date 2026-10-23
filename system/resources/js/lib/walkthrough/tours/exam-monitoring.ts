import type {
    ExamMonitoringContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ExamMonitoringContext;

const done: TourDestination = { kind: 'complete' };

export function buildExamMonitoringTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 3: Exam Monitoring',
            text: 'Welcome to the Live Exam Monitoring Console! In NEXIAM’s 7-step pipeline, you are operating in Step 3: Exam Monitoring. Examinees scheduled in Step 2: Exam Scheduling arrive on campus for active test administration, check-in, and incident tracking.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'attendance-concept' } },
        },
        {
            id: 'attendance-concept',
            phase: 'concept',
            title: 'Real-Time Telemetry & Attendance Tracking',
            text: 'The telemetry cards display live campus attendance metrics: scheduled examinees, verified present arrivals, and unrecorded candidates. You can track session progress across testing shifts in real time.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'monitoring-telemetry' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'shifts-workflow' } },
        },
        {
            id: 'shifts-workflow',
            phase: 'workflow',
            title: 'Session Supervision & Check-in Roster',
            text: 'Monitor each room’s active status: Scheduled, In Progress, or Closed. You can scan applicant QR codes or use the check-in list to record candidate attendance and accommodate verified walk-ins.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'monitoring-checkin' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Handoff to Step 4: Scoring & OMR',
            text: 'When test administration concludes, closing the exam session reconciles eligible submissions and locks the attendance ledger. The closed session flows immediately to Step 4: Scoring & OMR for answer sheet scanning and manual score entry!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'exam-monitoring',
        revision: 2,
        title: 'Live Exam Monitoring & Operations Guide',
        domain: 'examinations',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const examMonitoringTour = buildExamMonitoringTour;
