import type {
    ApplicantQrContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = ApplicantQrContext;

const done: TourDestination = { kind: 'complete' };

export function buildApplicantQrTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Meet Your All-in-One Campus QR Pass!',
            text: "Mabuhay! This permanent code identifies your admission attempt whenever university staff look up your record. Whether you're checking in on test day in Step 3, meeting a counselor in Step 6, or finishing verification in Step 7, staff can scan this to assist you in seconds!",
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'pass-concept' } },
        },
        {
            id: 'pass-concept',
            phase: 'concept',
            title: 'One Permanent Pass for Every Step',
            text: "You only need this one code! It identifies your admission attempt across every stage. It contains no private test scores or sensitive secrets; it's simply a secure record locator so university staff can assist you quickly and accurately.",
            emotion: 'searching-phone',
            speechMode: 'typewriter',
            target: { dataTour: 'qr-card' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'print-workflow' } },
        },
        {
            id: 'print-workflow',
            phase: 'workflow',
            title: 'Save to Phone & Print a Paper Backup',
            text: "Tap 'Print approved copy' or save the PDF on your phone right now. Pro-tip from Kuya Xiam: definitely print out a physical paper backup to slip into your exam envelope! If your phone battery runs low or signals are weak on exam morning, you'll be totally stress-free.",
            emotion: 'searching-phone',
            speechMode: 'typewriter',
            target: { dataTour: 'qr-print-button' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Ready for the Next Step!',
            text: "Present your QR pass along with a valid ID whenever university staff request it on campus. Keep it handy, stay relaxed, and check your portal whenever you want to see what's coming up next!",
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'applicant-qr',
        revision: 3,
        title: 'NEXIAM QR Guide',
        domain: 'applicant',
        persona: 'applicant-mentor',
        start: 'welcome',
        steps,
    };
}

export const applicantQrTour = buildApplicantQrTour;
