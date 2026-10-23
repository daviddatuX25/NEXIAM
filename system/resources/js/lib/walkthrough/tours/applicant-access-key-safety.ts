import type {
    ApplicantAccessKeySafetyContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ApplicantAccessKeySafetyContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantAccessKeySafetyTour(
    c?: Readonly<Ctx>,
): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'You Did It! Application Submitted!',
            text: "Huge congratulations! Applying for college takes real courage, and you just finished Step 1: Applications! Before we head into your portal, let's make sure you keep your personal access key safe so you never lose access to your records.",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: {
                kind: 'next',
                destination: { kind: 'step', stepId: 'key-concept' },
            },
        },
        {
            id: 'key-concept',
            phase: 'concept',
            title: 'Your Private Access Key',
            text: "Notice your Access Key displayed right here. NEXIAM doesn't use passwords that get forgotten—instead, this unique key paired with your birthdate is your private keycard to sign in anytime. Tap 'Copy Key' or snap a clear photo so you always have it saved!",
            emotion: 'searching-phone',
            speechMode: 'typewriter',
            target: { dataTour: 'access-key-display' },
            advance: {
                kind: 'next',
                destination: { kind: 'step', stepId: 'safety-workflow' },
            },
        },
        {
            id: 'safety-workflow',
            phase: 'workflow',
            title: 'Your Phone vs. a Computer Cafe',
            text: "Are you on your personal phone or laptop? You can tap 'Save key on this device' for effortless sign-ins. But if you're at an internet cafe, school computer lab, or library, never save it on that browser—jot it down in your notebook or message it to yourself securely!",
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'access-key-save' },
            advance: {
                kind: 'next',
                destination: { kind: 'step', stepId: 'guidance' },
            },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Next Up: Your Applicant Portal',
            text: "We've also emailed a confirmation with your Notice of Admission (NOA) number for safekeeping. Whenever you're ready, tap 'Proceed to Applicant Portal' to see your official progress roadmap. I'll meet you right inside!",
            emotion: 'hi',
            speechMode: 'typewriter',
            target: { dataTour: 'access-key-sign-in' },
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-access-key-safety',
        revision: 2,
        title: 'Access Key Safety & Portal Onboarding',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        allowAskAi: false,
        steps,
    };
}

export const applicantAccessKeySafetyTour = buildApplicantAccessKeySafetyTour;
