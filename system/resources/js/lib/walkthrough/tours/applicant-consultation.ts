import type {
    ApplicantConsultationContext,
    TourDefinition,
    TourDestination,
    TourStep,
} from '../types';

type Ctx = ApplicantConsultationContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantConsultationTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 6: Release Day!',
            text: "Mabuhay! You've made it to Step 6: Release Day! This is one of the most exciting milestones in your journey—it's when you meet with a guidance counselor to discuss your entrance test results, explore your strengths, and talk through your degree plans.",
            emotion: 'hi',
            speechMode: 'typewriter',
            target: { dataTour: 'release-schedule-header' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'schedule-concept' } },
        },
        {
            id: 'schedule-concept',
            phase: 'concept',
            title: 'Your Consultation Appointment',
            text: "The guidance office arranges appointments by batch so every student gets dedicated, one-on-one time with a counselor. Your scheduled date, time window, and campus venue will appear right below as soon as your session is published.",
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'release-schedule-content' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'preparation-concept' } },
        },
        {
            id: 'preparation-concept',
            phase: 'workflow',
            title: 'What to Expect in Your Session',
            text: "Please don't feel nervous! Your counseling encounter is a friendly, supportive conversation. You and your counselor will review your aptitude highlights, discuss which college majors fit your strengths best, and prepare you for Step 7: Admission Clearance. You're almost there!",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            target: { dataTour: 'release-schedule-content' },
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-consultation',
        revision: 1,
        title: 'Your Release Day Appointment',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        steps,
    };
}

export const applicantConsultationTour = buildApplicantConsultationTour;
