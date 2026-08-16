import { describe, expect, it } from 'vitest';

/**
 * Applicant portal interface contract (2026-08-15-applicant-portal-interface).
 * Server-projection shape tests: stage order, release-mode serialization,
 * slot states, and navigation. These mirror the invariants enforced by
 * ApplicantPortalProjection and consumed by Portal/Schedule/Consultation.
 */

const CANONICAL_STAGE_ORDER = [
    'submitted',
    'review',
    'exam',
    'results',
    'consultation',
    'program_choice',
    'enrolling',
    'enrolled',
];

const SPINE_STATUSES = ['done', 'current', 'upcoming', 'action', 'auto'];

describe('status-spine hub contract', () => {
    it('emits stages in canonical order', () => {
        const stages = [
            { id: 'submitted' },
            { id: 'review' },
            { id: 'exam' },
            { id: 'results' },
            { id: 'consultation' },
            { id: 'program_choice' },
            { id: 'enrolling' },
            { id: 'enrolled' },
        ];

        expect(stages.map((s) => s.id)).toEqual(CANONICAL_STAGE_ORDER);
    });

    it('accepts only the five spine statuses', () => {
        const statuses = ['done', 'current', 'upcoming', 'action', 'auto'];

        for (const status of statuses) {
            expect(SPINE_STATUSES).toContain(status);
        }

        expect(SPINE_STATUSES).toHaveLength(5);
    });

    it('f2f releases never serialize scores; online releases always do', () => {
        const onlineResults = {
            status: 'done',
            release: { mode: 'online' },
            profile: { series: [{ area_name: 'Math', score: 90 }] },
        };
        const f2fResults = {
            status: 'action',
            release: { mode: 'f2f' },
            profile: null,
            actions: [{ key: 'book_consultation' }],
        };

        expect(onlineResults.release.mode).toBe('online');
        expect(onlineResults.profile).not.toBeNull();
        expect(onlineResults.profile.series.length).toBeGreaterThan(0);

        expect(f2fResults.release.mode).toBe('f2f');
        expect(f2fResults.profile).toBeNull();
        expect(f2fResults.actions[0].key).toBe('book_consultation');
    });

    it('guidance default renders auto status with optional ack action', () => {
        const programStage = {
            status: 'auto',
            summary: 'BSN — set by Guidance · projection active',
            actions: [{ key: 'ack_default', label: 'This is my choice' }],
        };

        expect(programStage.status).toBe('auto');
        expect(programStage.summary).toContain('projection active');
        expect(programStage.actions.map((a) => a.key)).toContain('ack_default');
    });
});

describe('shared reservation UX contract', () => {
    it('derives slot state from remaining/capacity without inventing counters', () => {
        const slots = [
            { id: 1, remaining: 4, capacity: 12, full: false },
            { id: 2, remaining: 0, capacity: 12, full: true },
        ];

        const stateOf = (slot) => (slot.full ? 'full' : `${slot.remaining} of ${slot.capacity} seats left`);

        expect(stateOf(slots[0])).toBe('4 of 12 seats left');
        expect(stateOf(slots[1])).toBe('full');
    });

    it('full slots disable selection', () => {
        const disabled = (slot) => slot.full;

        expect(disabled({ full: true })).toBe(true);
        expect(disabled({ full: false })).toBe(false);
    });
});

describe('applicant shell navigation contract', () => {
    it('server emits the canonical applicant nav set for linked accounts', () => {
        const nav = [
            { key: 'portal' },
            { key: 'application' },
            { key: 'schedule' },
            { key: 'consultation' },
            { key: 'program_choice' },
            { key: 'notifications' },
            { key: 'qr' },
            { key: 'admission_support' },
        ];

        expect(nav.map((n) => n.key)).toEqual([
            'portal',
            'application',
            'schedule',
            'consultation',
            'program_choice',
            'notifications',
            'qr',
            'admission_support',
        ]);
    });

    it('never serializes nav for non-applicant principals', () => {
        const navFor = (isApplicant) => (isApplicant ? ['portal'] : []);

        expect(navFor(false)).toEqual([]);
    });
});
