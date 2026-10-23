import type {
    ApplicantMyApplicationContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ApplicantMyApplicationContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantMyApplicationTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Reviewing Your Application Record',
            text: "Welcome to your application overview! Think of this page as your official admissions profile. Everything you submitted in Step 1: Applications—your personal details, Senior High strand, and degree choices—is neatly organized right here.",
            emotion: 'hi',
            speechMode: 'typewriter',
            target: { dataTour: 'my-application-status' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'review-concept' } },
        },
        {
            id: 'review-concept',
            phase: 'concept',
            title: 'Document Checks & Registrar Review',
            text: "The Document Checklist shows how the Registrar is reviewing your school credentials. A green check means that requirement is satisfied. If an item says 'Not yet checked,' don't stress—the admissions staff reviews files in batches, so you're in good hands!",
            emotion: 'searching-paper',
            speechMode: 'typewriter',
            target: { dataTour: 'my-application-documents' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'corrections-concept' } },
        },
        {
            id: 'corrections-concept',
            phase: 'workflow',
            title: 'What If a Correction Is Requested?',
            text: "If you ever see a yellow 'Correction requested' card, please don't panic! It is definitely not a rejection. It just means the Registrar spotted a small typo or needs a clearer detail. Simply click the link, update that specific field, and resubmit easily.",
            emotion: 'thinking',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'preferences-workflow' } },
        },
        {
            id: 'preferences-workflow',
            phase: 'workflow',
            title: 'Your Degree Program Wishlist',
            text: "Here are your 1st, 2nd, and 3rd choice college majors! Later on, during Step 6: Release Day, a guidance counselor will discuss these choices with you alongside your exam results to help guide you toward the best degree track.",
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'my-application-preferences' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'What Happens Next?',
            text: "Once the Registrar approves your application, your account unlocks Step 2: Exam Scheduling! Check your portal dashboard to see when session dates open for reservation. You're doing wonderful work—keep moving forward!",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-my-application',
        revision: 4,
        title: 'Application Record & Verification Guide',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        steps,
    };
}

export const applicantMyApplicationTour = buildApplicantMyApplicationTour;
