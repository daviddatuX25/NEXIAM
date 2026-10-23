import type {
    ApplicantApplyContext,
    TourDefinition,
    TourDestination,
    TourStep,
} from '../types';

type Ctx = ApplicantApplyContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantApplyGuideTour(
    c?: Readonly<Ctx>,
): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 1: Applications!',
            text: "Mabuhay! I'm Kuya Xiam, and I'll be right beside you throughout your college admissions journey. This is Step 1: Applications. Taking this first step is exciting—don't stress, we'll go through the form together step by step!",
            emotion: 'hi',
            speechMode: 'typewriter',
            target: { dataTour: 'apply-header' },
            advance: {
                kind: 'next',
                destination: { kind: 'step', stepId: 'personal-identity' },
            },
        },
        {
            id: 'personal-identity',
            phase: 'concept',
            title: 'Your Details & Passwordless Access Key',
            text: "Enter your legal name, birthdate, Senior High strand, and contact info just like they appear on your school records. You won't need to invent a complicated password—as soon as you submit, NEXIAM gives you a personal access key that lets you sign into your portal anytime with your birthdate.",
            emotion: 'searching-phone',
            speechMode: 'typewriter',
            target: { dataTour: 'apply-personal-info' },
            advance: {
                kind: 'next',
                destination: { kind: 'step', stepId: 'degree-preferences' },
            },
        },
        {
            id: 'degree-preferences',
            phase: 'guidance',
            title: 'Choose Your Dream Degree Programs',
            text: "Pick your 1st, 2nd, and 3rd preferred degree programs—put what you genuinely love most as your first choice! Review the Privacy Policy and submit when you're ready. Hard copy Form 138 / TOR documents will still be verified in person at the Registrar. Your journey begins right here—you've got this, future scholar!",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            target: { dataTour: 'apply-program-preferences' },
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-apply-guide',
        revision: 1,
        title: 'Admission Application Guide',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        allowAskAi: false,
        steps,
    };
}

export const applicantApplyGuideTour = buildApplicantApplyGuideTour;
