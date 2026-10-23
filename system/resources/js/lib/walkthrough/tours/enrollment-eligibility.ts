import type {
    EnrollmentEligibilityContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = EnrollmentEligibilityContext;

const done: TourDestination = { kind: 'complete' };

export function buildEnrollmentEligibilityTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 7: Admission Clearance',
            text: 'Welcome to the Admission Clearance Counter! In NEXIAM’s 7-step pipeline, you are operating in Step 7: Admission Clearance—the final operational stage in NEXIAM. Applicants who have completed application review (Step 1), testing (Step 3), scoring (Step 4), and advising (Step 6) arrive here for final document verification and clearance confirmation. External enrollment is not tracked here.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'pre-clearance-milestones' } },
        },
        {
            id: 'pre-clearance-milestones',
            phase: 'concept',
            title: '1. Pre-Clearance Milestones',
            text: 'Review the current prerequisite statuses shown for the application. A supplemental acknowledgement of a program selection is optional; other current prerequisites and required checks still apply.',
            emotion: 'thinking',
            speechMode: 'typewriter',
            target: { dataTour: 'pre-clearance-milestones' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'document-requirements' } },
        },
        {
            id: 'document-requirements',
            phase: 'workflow',
            title: '2. Document Requirements Panel',
            text: 'Review each submitted document against its displayed requirement and current check status. Unreviewed documents are not automatically a correction request; follow up only on items explicitly marked for correction.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'document-requirements-panel' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'confirm-clearance' } },
        },
        {
            id: 'confirm-clearance',
            phase: 'workflow',
            title: '3. Confirming Admission Clearance',
            text: 'When the page shows all current requirements ready, an authorized Registrar may confirm Admission Clearance. This records current clearance in NEXIAM; it does not assert external enrollment or a separate sheet handoff. Use the reopen control only when authorized and when a correction is needed.',
            emotion: 'talking-presenting',
            speechMode: 'typewriter',
            target: { dataTour: 'admission-clearance-action' },
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'enrollment-eligibility',
        revision: 3,
        title: 'Admission Clearance Guide',
        domain: 'admissions',
        start: 'welcome',
        steps,
    };
}

export const enrollmentEligibilityTour = buildEnrollmentEligibilityTour;
