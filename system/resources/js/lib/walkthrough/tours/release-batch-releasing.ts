import type {
    ReleaseIndexContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ReleaseIndexContext;

const done: TourDestination = { kind: 'complete' };

export function buildReleaseBatchReleasingTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Result Document Preparation',
            text: 'Welcome to the Result Printing Workspace! As part of Step 6: Release Day, official physical score certificates and admission slips are prepared here for face-to-face counseling sessions.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'release-concept' } },
        },
        {
            id: 'release-concept',
            phase: 'concept',
            title: 'Individual Document Preparation',
            text: 'Any examinee with complete valid scores can have their result document prepared without waiting for the entire batch to close. Successful preparation freezes the applicant’s official score snapshot for security.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'release-batch-sessions' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'printing-workflow' } },
        },
        {
            id: 'printing-workflow',
            phase: 'workflow',
            title: 'Prepare, Preview & Print Workflow',
            text: 'Open a session roster to prepare missing documents, preview the formatted result slip, and generate official print PDFs. Only confirm "Mark Printed" after physical copies are printed for the counseling table.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'release-batch-sessions' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Secure Handover & Step 6 Encounter',
            text: 'Printed documents are kept secure until the face-to-face Release Day session, where counselors hand them directly to examinees during their advising consultation!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'release-batch-releasing',
        revision: 3,
        title: 'Results Release & Certificate Printing Guide',
        domain: 'examinations',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const releaseBatchReleasingTour = buildReleaseBatchReleasingTour;
