import type {
    SetupReadinessContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = SetupReadinessContext;

const done: TourDestination = { kind: 'complete' };

export function buildSetupReadinessTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Institutional Setup & Readiness Hub',
            text: 'Welcome to the Setup & Readiness Hub! This is where university administrators and committee heads verify that all institutional parameters, testing environments, and scoring policies are fully calibrated before admissions launch.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'pillars-concept' } },
        },
        {
            id: 'pillars-concept',
            phase: 'concept',
            title: 'The Four Readiness Pillars',
            text: 'Readiness is organized around four core pillars: Academic Cycles (intake years & terms), Degree Programs (strands, cut-offs, & quotas), Examination Architecture (OMR templates, shifts, & rooms), and Scoring Rules (aptitude areas & weights). Every pillar must be validated to protect admissions integrity.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'readiness-summary-card' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'categories-workflow' } },
        },
        {
            id: 'categories-workflow',
            phase: 'workflow',
            title: 'Navigating Categories & Resolving Checks',
            text: 'Each readiness card shows the operational status of that category with a direct configuration link. If an item needs attention—such as an unassigned room or an unactivated result template—clicking into that category takes you straight to the authoring interface.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'readiness-groups-list' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Audit-Proof Launch Standards',
            text: 'The system prevents accidental operational launch while critical prerequisites are incomplete. Once all categories reach 100% readiness, admissions intake can open with complete confidence that every applicant will experience a smooth, fair journey!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'setup-readiness-hub',
        revision: 2,
        title: 'Institutional Readiness & Setup Guide',
        domain: 'setup',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const setupReadinessHubTour = buildSetupReadinessTour;
