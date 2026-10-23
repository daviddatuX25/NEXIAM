import type {
    AdmissionSupportSetupContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = AdmissionSupportSetupContext;

const done: TourDestination = { kind: 'complete' };

export function buildAdmissionSupportTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Decision Support',
            text: 'Open TabFM to manage program benchmarks. The empty model slot explains how to request another model.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'authority-concept' } },
        },
        {
            id: 'authority-concept',
            phase: 'concept',
            title: 'Staff make the decision',
            text: 'TabFM compares applicant results with program benchmarks. Its results support counseling; they do not admit or reject an applicant.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'benchmark-status-badge' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'studios-workflow' } },
        },
        {
            id: 'studios-workflow',
            phase: 'workflow',
            title: 'Prepare and review benchmarks',
            text: 'Choose a program in TabFM and save your changes as a draft. Guidance reviews the exact draft before publishing. A published version needs a separate action before it is used.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'tabfm-studio-card' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Check what is in use',
            text: 'The status shows whether the saved benchmarks need review. Open Previous versions to see published versions. Saving a draft does not change the version in use.',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'admission-support',
        revision: 3,
        title: 'Decision Support guide',
        domain: 'setup',
        persona: 'staff-copilot',
        start: 'welcome',
        steps,
    };
}

export const admissionSupportTour = buildAdmissionSupportTour;
