import type {
    ApplicantPortalContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ApplicantPortalContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantPortalTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome Home to Your Admission Portal!',
            text: "Kumusta! I'm Kuya Xiam, and this is your personal college admissions roadmap. Everything you need to know about your application, entrance exams, and results will unfold right here on this dashboard.",
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'milestones' } },
        },
        {
            id: 'milestones',
            phase: 'concept',
            title: 'Your Step-by-Step Admissions Roadmap',
            text: "See this timeline? It tracks your progress from Step 1: Applications, through your entrance exam and Release Day advising, all the way to final clearance. A green badge means that step is done, while blue shows where you are right now!",
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'applicant-stages-list' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'action-cards' } },
        },
        {
            id: 'action-cards',
            phase: 'workflow',
            title: 'Action Cards & Live Instructions',
            text: "Whenever a step needs your attention—like reserving an exam slot or reviewing your program choices—an action button will light up here. You can click 'View details' on any milestone to see room assignments, schedules, and what to prepare.",
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: "Kuya Xiam's Friendly Tips",
            text: "Keep your Access Key private and safe. If you're on a shared PC at a computer shop or library, remember to tap 'Sign Out' when you're done so no one else can view your records. You've got this, and I'm right here whenever you need advice!",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-portal',
        revision: 4,
        title: 'Applicant Portal Journey Guide',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        steps,
    };
}

export const applicantPortalTour = buildApplicantPortalTour;
