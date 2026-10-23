import type {
    ApplicantProgramChoiceContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ApplicantProgramChoiceContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantProgramChoiceTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Your College Degree Program Plans',
            text: "Kumusta! On this page, you can review the degree program recommended for you following your entrance exam and advising encounter in Step 6: Release Day. Let's walk through your options together!",
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'qualification-concept' } },
        },
        {
            id: 'qualification-concept',
            phase: 'concept',
            title: 'Your Advised Degree Program',
            text: "Right here is the degree program currently listed on your admissions file. This reflects your guidance counselor's review and your entrance test results. Take a moment to check the college department and major.",
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'program-choice-status' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'advising-workflow' } },
        },
        {
            id: 'advising-workflow',
            phase: 'workflow',
            title: 'Confirming or Exploring Alternatives',
            text: "If you're excited about this program, simply tap 'Still my plan' to let us know. But if your plans or interests have shifted, tap 'Something changed' to see other Guidance-recommended tracks. Don't worry—sharing your thoughts is optional and never delays your official clearance!",
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'program-choice-plans' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Next Stop: Step 7 Admission Clearance!',
            text: "Once your program plan is acknowledged, you're set for Step 7: Admission Clearance with the Registrar! You've worked so hard through exams and advising—college life is just around the corner, and you're ready for it!",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-program-choice',
        revision: 4,
        title: 'Program Choice Guide',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        steps,
    };
}

export const applicantProgramChoiceTour = buildApplicantProgramChoiceTour;
