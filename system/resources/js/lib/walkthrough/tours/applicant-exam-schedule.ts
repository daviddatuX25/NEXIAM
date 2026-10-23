import type {
    ApplicantScheduleContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ApplicantScheduleContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantScheduleTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const hasAppointment = (c?.pageState === 'assigned_tentative' || c?.pageState === 'assigned_published') && c.appointment !== null;
    const canReserve = c?.pageState === 'calendar_open' && c.availableSlotCount > 0;
    const schedulingText = hasAppointment
        ? c?.appointment?.source === 'applicant_reserved'
            ? "Awesome! You've reserved your official exam slot. Take a look below to review your date, time, and room details so you're completely prepared."
            : 'Great news! The testing office has assigned your official exam schedule. Review your session date, time, and campus room assignment below.'
        : canReserve
            ? "Good news! Choose an available exam slot from the calendar, pick the date and morning or afternoon session that fits you best, and confirm your reservation!"
            : c?.message || "The testing center is organizing upcoming exam batches. As soon as reservation opens or your session is scheduled, you'll see it right here.";
    const appointmentText = hasAppointment
        ? c?.appointment?.published
            ? "Here is your official exam appointment card! Note down your testing date, building, room number, and reporting time so you're well-prepared on test day."
            : "Your assignment is not yet published. The testing office is finalizing the session—check back shortly for the official schedule before treating these details as final."
        : "An appointment card with your room and schedule details appears after an exam slot is assigned or reserved. Follow the current status message on this page.";
    const seatingInstruction = (c?.seatingEnabled === true || c?.hasAssignedSeatInstruction === true) && hasAppointment
        ? ' Room proctors will assist you to your assigned seat upon arrival.'
        : '';
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 2: Exam Scheduling!',
            text: "Kumusta! Kuya Xiam here. You've reached Step 2: Exam Scheduling! Whether you're reserving a slot yourself or waiting for testing staff to assign your session, this page keeps your test day plans clear and organized.",
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'scheduling-concept' } },
        },
        {
            id: 'scheduling-concept',
            phase: 'concept',
            title: 'How Exam Booking Works',
            text: schedulingText,
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'exam-schedule-slots' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'appointment-workflow' } },
        },
        {
            id: 'appointment-workflow',
            phase: 'workflow',
            title: 'Your Date, Time & Testing Room',
            text: appointmentText,
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'exam-schedule-appointment' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Kuya Xiam’s Exam Day Checklist',
            text: `Follow the official exam notice and Guidance instructions for your arrival time and what to bring. Check this page for any schedule updates before travelling. Get a good night's sleep—you are going to do great!${seatingInstruction}`,
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-exam-schedule',
        revision: 4,
        title: 'Exam Scheduling & Test Day Guide',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        steps,
    };
}

export const applicantScheduleTour = buildApplicantScheduleTour;
export const applicantExamScheduleTour = buildApplicantScheduleTour;
