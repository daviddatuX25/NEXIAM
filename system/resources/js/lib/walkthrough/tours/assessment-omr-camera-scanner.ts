import type {
    OmrScannerContext,
    TourDefinition,
    TourDestination,
    TourPhase,
    TourStep,
} from '../types';

type Ctx = OmrScannerContext;

const done: TourDestination = { kind: 'complete' };

export function buildOmrScannerTour(c?: Readonly<Ctx>): TourDefinition<Ctx> {
    const steps: TourStep<Ctx>[] = [
        {
            id: 'welcome',
            phase: 'overview',
            title: 'Welcome to Step 4: Scoring & OMR Scanner',
            text: 'Welcome to the Answer Sheet Scanning Station! In NEXIAM’s 7-step pipeline, you are operating in Step 4: Scoring & OMR. Physical answer sheets collected from completed exam rooms in Step 3 arrive here for optical mark recognition, QR provenance verification, and score extraction.',
            emotion: 'hi',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'vision-concept' } },
        },
        {
            id: 'vision-concept',
            phase: 'concept',
            title: 'Optical Scanning & QR Provenance Verification',
            text: 'The scanner captures answer sheet images, reads QR provenance, and matches each candidate to their verified attendance record. Ensure the document is well-lit and oriented properly.',
            emotion: 'searching-paper',
            speechMode: 'typewriter',
            target: { dataTour: 'omr-scanner-capture' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'capture-workflow' } },
        },
        {
            id: 'capture-workflow',
            phase: 'workflow',
            title: 'Operating Capture & Roster Verification',
            text: 'When an answer sheet is uploaded or captured via webcam, NEXIAM grades the mark density against the approved test kit rubric. Review the captured response status before confirming the score record.',
            emotion: 'searching-paper',
            speechMode: 'typewriter',
            target: { dataTour: 'omr-scanner-capture-status' },
            advance: { kind: 'next', destination: { kind: 'step', stepId: 'guidance' } },
        },
        {
            id: 'guidance',
            phase: 'guidance',
            title: 'Handling Fallbacks & Step 5 Handoff',
            text: 'If a QR code is damaged or unreadable, authorized staff can verify the printed Display Code or manual applicant confirmation fallback. Finalized scores feed directly into Step 5: Release Scheduling and Step 6: Release Day!',
            emotion: 'celebrating',
            speechMode: 'typewriter',
            advance: { kind: 'next', destination: done },
        },
    ];

    return {
        id: 'assessment-omr-camera-scanner',
        revision: 2,
        title: 'OMR Camera Scanner Operations Guide',
        domain: 'examinations',
        persona: 'exam-administrator-guide',
        start: 'welcome',
        steps,
    };
}

export const omrScannerTour = buildOmrScannerTour;
export const assessmentOmrCameraScannerTour = buildOmrScannerTour;
