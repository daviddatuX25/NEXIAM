import type {
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
    XiamKnowledgeContext,
} from '../types';

type Ctx = XiamKnowledgeContext;

const done: TourDestination = { kind: 'complete' };

export function buildXiamKnowledgeTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'FAQs Hub',
            text: 'Welcome to the FAQs Hub. Manage verified questions and answers published across the institution.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'pipeline-concept' } },
        },
        {
            id: 'pipeline-concept',
            phase: 'concept',
            title: 'Review & Publication',
            text: 'Each FAQ moves through draft, verification, and supervisor review before publication. Active public FAQs are published to the public FAQ page and applicant inquiries.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'governance-pipeline' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'catalog-workflow' } },
        },
        {
            id: 'catalog-workflow',
            phase: 'workflow',
            title: 'Filters & Search',
            text: 'Filter by issuing office (Admin, Registrar, Guidance) or search question keywords to inspect active and draft entries.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'knowledge-catalog-filters' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Authoring FAQs',
            text: 'Authorized staff can draft new FAQs. Once approved and published, they update immediately across the portal.',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'xiam-knowledge-governance',
        revision: 3,
        title: 'FAQs Guide',
        domain: 'communications',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const xiamKnowledgeTour = buildXiamKnowledgeTour;
