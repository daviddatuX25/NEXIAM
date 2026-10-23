import { getTour } from '../../resources/js/lib/walkthrough/tours';
import { describe, expect, it } from 'vitest';
import { resolveStepEmotion, resolveTour } from '../../resources/js/lib/walkthrough/resolver';
import { PHASE_DEFAULT_EMOTIONS, type TourDefinition } from '../../resources/js/lib/walkthrough/types';
import { buildAdmissionSupportTour } from '../../resources/js/lib/walkthrough/tours/admission-support';
import { buildOmrScannerTour } from '../../resources/js/lib/walkthrough/tours/assessment-omr-camera-scanner';
import { buildApplicantAccessKeySafetyTour } from '../../resources/js/lib/walkthrough/tours/applicant-access-key-safety';

describe('Walkthrough Hierarchical Emotion Resolver', () => {
    it('prioritizes explicit step emotion over phase defaults', () => {
        const emotion = resolveStepEmotion('star-eyes', 'concept');
        expect(emotion).toBe('star-eyes');
    });

    it('falls back to phase defaults when step emotion is omitted', () => {
        expect(resolveStepEmotion(undefined, 'concept')).toBe('thinking');
        expect(resolveStepEmotion(undefined, 'workflow')).toBe('talking-presenting');
        expect(resolveStepEmotion(undefined, 'overview')).toBe('hi');
        expect(resolveStepEmotion(undefined, 'guidance')).toBe('thumbs-up');
        expect(resolveStepEmotion(undefined, 'milestone')).toBe('celebrating');
        expect(resolveStepEmotion(undefined, 'diagnostic')).toBe('shock');
        expect(resolveStepEmotion(undefined, 'recovery')).toBe('sad');
    });

    it('falls back to idle for unknown phase without emotion', () => {
        expect(resolveStepEmotion(undefined, undefined)).toBe('idle');
    });

    it('resolves admission support tour with domain-matched staging', () => {
        const tour = resolveTour(buildAdmissionSupportTour());
        const emotions = tour.steps.map((s) => s.emotion);

        expect(emotions).toContain('hi');
        expect(emotions).toContain('thinking');
        expect(emotions).toContain('talking-presenting');
        expect(emotions).toContain('celebrating');
    });

    it('resolves OMR scanner tour with searching-paper physical pose', () => {
        const tour = resolveTour(buildOmrScannerTour());
        const emotions = tour.steps.map((s) => s.emotion);

        expect(emotions).toContain('searching-paper');
    });

    it('resolves applicant access key tour with searching-phone physical pose', () => {
        const tour = resolveTour(buildApplicantAccessKeySafetyTour());
        const emotions = tour.steps.map((s) => s.emotion);

        expect(emotions).toContain('searching-phone');
    });

    it('resolves applicant apply guide tour without a grade collection step', () => {
        const tour = getTour('applicant-apply-guide');
        expect(tour).not.toBeNull();
        expect(tour!.steps.map((s) => s.id)).toEqual(['welcome', 'personal-identity', 'degree-preferences']);
    });

    it('resolves applicant consultation tour with advising workflow steps', () => {
        const tour = getTour('applicant-consultation');
        expect(tour).not.toBeNull();
        expect(tour!.steps.map((s) => s.id)).toEqual(['welcome', 'schedule-concept', 'preparation-concept']);
    });

});
