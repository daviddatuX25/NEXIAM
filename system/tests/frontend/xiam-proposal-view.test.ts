import { describe, expect, it } from 'vitest';
import {
    canConfirmXiamProposal,
    decodeXiamProposalView,
    formatXiamProposalFieldChange,
    formatXiamProposalInstant,
    formatXiamProposalValue,
    type XiamProposalInstant,
    type XiamProposalValue,
} from '../../resources/js/lib/xiam-proposal-view';
import { getXiamActionPresentation } from '../../resources/js/lib/xiam-presentation';

const ACTION_IDS = [
    'admission.office_inquiries.open@1',
    'admission.office_inquiries.reply@1',
    'setup.cycles.activate@1',
    'setup.rooms.update@1',
    'setup.quotas.upsert@1',
    'setup.rooms.create_draft@1',
    'setup.cycles.create_draft@1',
    'setup.notifications.update_admission@1',
    'setup.notifications.update_exam@1',
    'setup.windows.store@1',
    'exams.slot.reserve@1',
    'exams.session.reservation.open@1',
    'exams.session.assign@1',
    'exams.session.publish@1',
    'exams.session.create@1',
    'exams.attendance.record@1',
    'exams.session.start@1',
    'exams.session.close@1',
    'consultation.session.create@1',
    'consultation.session.reservation.open@1',
    'consultation.decision.record@2',
    'consultation.session.cancel@1',
    'admissions.attempt.accept@1',
    'admissions.attempt.bulk_accept@1',
    'admissions.attempt.request_correction@1',
    'assessment.scores.record_manual@1',
    'assessment.grading.finalize@1',
    'release.result.publish@2',
    'registrar.enrollment.stamp_enrolling@1',
    'registrar.enrollment.stamp_enrolled@1',
    'registrar.enrollment.reverse_stamp@1',
];

const instant = (value: string): XiamProposalInstant => ({
    type: 'instant',
    value,
    precision: 'second',
    display_zone: 'Asia/Manila',
});

function proposal(overrides: Record<string, unknown> = {}) {
    return {
        version: 1,
        proposal_id: 'proposal-public-1',
        run_id: 'run-public-1',
        capability_id: 'exams.session.create@1',
        operation: 'create',
        title: 'Create exam session',
        sections: [
            {
                kind: 'fields',
                key: 'session',
                label: 'Session',
                fields: [
                    {
                        key: 'session.code',
                        label: 'Code',
                        role: 'choice',
                        change: 'set',
                        before: { type: 'missing' },
                        after: { type: 'code', value: 'EX-001' },
                    },
                ],
            },
        ],
        preflight: { outcome: 'pass', checks: [] },
        safety: {
            tier: 't1',
            confirmation_phrase: null,
            requires_reauth: false,
            expires_at: '2026-10-08T02:00:00Z',
        },
        status: 'pending',
        confirmable: true,
        error: null,
        receipt: null,
        ...overrides,
    };
}

describe('Xiam proposal view strict wire decoder', () => {
    it('accepts a complete frozen v1 view without rewriting its values and gates confirmation by expiry', () => {
        const wire = proposal();
        const decoded = decodeXiamProposalView(wire);
        expect(decoded).toBe(wire);
        expect(decoded?.version).toBe(1);
        expect(
            decoded &&
                canConfirmXiamProposal(
                    decoded,
                    Date.parse('2026-10-08T01:00:00Z'),
                ),
        ).toBe(true);
        expect(
            decoded &&
                canConfirmXiamProposal(
                    decoded,
                    Date.parse('2026-10-08T02:00:00Z'),
                ),
        ).toBe(false);
    });
    it('requires the complete receipt and validated navigation references', () => {
        const receipt = {
            receipt_id: 'receipt-1',
            outcome_label: 'Decision recorded',
            confirmed_at: instant('2026-10-08T02:00:00Z'),
            references: [
                {
                    id: 'consultation.session',
                    label: 'Consultation session',
                    href: '/consultations/sessions/1',
                    version: 1,
                },
            ],
        };
        expect(decodeXiamProposalView(proposal({ receipt }))?.receipt).toBe(
            receipt,
        );
        expect(
            decodeXiamProposalView(
                proposal({
                    receipt: {
                        ...receipt,
                        references: [
                            {
                                ...receipt.references[0],
                                href: '//outside.example/path',
                            },
                        ],
                    },
                }),
            ),
        ).toBeNull();
    });

    it.each([
        ['unknown version', { version: 2 }],
        ['unknown envelope key', { debug: 'private' }],
        ['unknown operation', { operation: 'delete' }],
        ['unknown status', { status: 'committed' }],
        [
            'unknown nested value',
            {
                sections: [
                    {
                        kind: 'fields',
                        key: 's',
                        label: 'S',
                        fields: [
                            {
                                key: 'x',
                                label: 'X',
                                role: 'choice',
                                change: 'set',
                                before: { type: 'missing' },
                                after: { type: 'object', value: {} },
                            },
                        ],
                    },
                ],
            },
        ],
        [
            'unknown nested key',
            { preflight: { outcome: 'pass', checks: [], private: true } },
        ],
        [
            'missing required field',
            {
                sections: [
                    {
                        kind: 'fields',
                        key: 's',
                        label: 'S',
                        fields: [{ key: 'x' }],
                    },
                ],
            },
        ],
        [
            'unknown enum option',
            {
                sections: [
                    {
                        kind: 'fields',
                        key: 's',
                        label: 'S',
                        fields: [
                            {
                                key: 'x',
                                label: 'X',
                                role: 'choice',
                                change: 'set',
                                before: { type: 'missing' },
                                after: {
                                    type: 'enum',
                                    code: 'mystery',
                                    label: 'Mystery',
                                    extra: false,
                                },
                            },
                        ],
                    },
                ],
            },
        ],
        [
            'actionable failed preflight',
            { preflight: { outcome: 'unknown', checks: [] } },
        ],
        [
            'unknown preflight check',
            {
                preflight: {
                    outcome: 'pass',
                    checks: [
                        {
                            key: 'x',
                            label: 'Check',
                            status: 'unknown',
                            detail: null,
                        },
                    ],
                },
            },
        ],
        [
            'unknown integer semantic',
            {
                sections: [
                    {
                        kind: 'fields',
                        key: 's',
                        label: 'S',
                        fields: [
                            {
                                key: 'x',
                                label: 'X',
                                role: 'choice',
                                change: 'set',
                                before: { type: 'missing' },
                                after: {
                                    type: 'integer',
                                    value: 1,
                                    semantic: 'minutes',
                                    unit: 'minute',
                                },
                            },
                        ],
                    },
                ],
            },
        ],
        ['nonpending actionable status', { status: 'executing' }],
        ['incomplete actionable review', { sections: [] }],
    ])('rejects %s without producing a confirmable card', (_name, override) => {
        expect(decodeXiamProposalView(proposal(override))).toBeNull();
    });

    it('rejects offsetless, fractional, invalid-calendar and impossible clock timestamps', () => {
        for (const value of [
            '2024-02-29T12:00:00',
            '2024-02-29T12:00:00.123Z',
            '2023-02-29T12:00:00Z',
            '2024-02-30T12:00:00+08:00',
            '2024-02-29T24:00:00Z',
            '2024-02-29T12:00:60Z',
        ]) {
            const decoded = decodeXiamProposalView(
                proposal({
                    sections: [
                        {
                            kind: 'fields',
                            key: 'time',
                            label: 'Time',
                            fields: [
                                {
                                    key: 'starts_at',
                                    label: 'Starts',
                                    role: 'choice',
                                    change: 'set',
                                    before: { type: 'missing' },
                                    after: {
                                        type: 'instant',
                                        value,
                                        precision: 'second',
                                        display_zone: 'Asia/Manila',
                                    },
                                },
                            ],
                        },
                    ],
                }),
            );
            expect(decoded, value).toBeNull();
        }
        expect(
            decodeXiamProposalView(
                proposal({
                    sections: [
                        {
                            kind: 'fields',
                            key: 'date',
                            label: 'Date',
                            fields: [
                                {
                                    key: 'cycle.start',
                                    label: 'Start date',
                                    role: 'choice',
                                    change: 'set',
                                    before: { type: 'missing' },
                                    after: {
                                        type: 'date',
                                        value: '2023-02-29',
                                    },
                                },
                            ],
                        },
                    ],
                }),
            ),
        ).toBeNull();
    });

    it('accepts leap-day instants with Z or numeric offsets and keeps exact seconds', () => {
        for (const value of [
            '2024-02-29T12:34:56Z',
            '2024-02-29T12:34:56+08:00',
            '2024-02-29T12:34:56-05:30',
        ]) {
            expect(
                decodeXiamProposalView(
                    proposal({
                        sections: [
                            {
                                kind: 'fields',
                                key: 'time',
                                label: 'Time',
                                fields: [
                                    {
                                        key: 'starts_at',
                                        label: 'Starts',
                                        role: 'choice',
                                        change: 'set',
                                        before: { type: 'missing' },
                                        after: {
                                            type: 'instant',
                                            value,
                                            precision: 'second',
                                            display_zone: 'Asia/Manila',
                                        },
                                    },
                                ],
                            },
                        ],
                    }),
                ),
            ).not.toBeNull();
        }
    });

    it('preserves group structure and rejects incomplete nested preflight', () => {
        const grouped = proposal({
            sections: [
                {
                    kind: 'groups',
                    key: 'targets',
                    label: 'Applicants',
                    groups: [
                        {
                            key: 'attempt-a',
                            label: 'Applicant A',
                            context: {
                                type: 'record',
                                label: 'Applicant A',
                                context: ['Cycle 2026'],
                            },
                            fields: [
                                {
                                    key: 'status',
                                    label: 'Status',
                                    role: 'effect',
                                    change: 'set',
                                    before: {
                                        type: 'enum',
                                        code: 'pending',
                                        label: 'Pending',
                                    },
                                    after: {
                                        type: 'enum',
                                        code: 'accepted',
                                        label: 'Accepted',
                                    },
                                },
                            ],
                            preflight: { outcome: 'pass', checks: [] },
                        },
                    ],
                },
            ],
        });
        expect(decodeXiamProposalView(grouped)?.sections[0]?.kind).toBe(
            'groups',
        );
        const invalid = proposal({
            sections: [
                {
                    kind: 'groups',
                    key: 'targets',
                    label: 'Applicants',
                    groups: [
                        {
                            key: 'attempt-a',
                            label: 'Applicant A',
                            context: {
                                type: 'record',
                                label: 'Applicant A',
                                context: ['Cycle 2026'],
                            },
                            fields: [],
                            preflight: { outcome: 'pass' },
                        },
                    ],
                },
            ],
        });
        expect(decodeXiamProposalView(invalid)).toBeNull();
    });
});

describe('Xiam proposal value formatting', () => {
    it('formats dates as stable date-only values and instants in explicit Manila 12-hour time', () => {
        const date = { type: 'date', value: '2024-02-29' } as const;
        const noon = instant('2026-10-08T04:00:00Z');
        const midnight = instant('2026-10-07T16:00:00Z');
        const initialDate = formatXiamProposalValue(date);
        const previousTimezone = process.env.TZ;
        try {
            process.env.TZ = 'Pacific/Honolulu';
            expect(formatXiamProposalValue(date)).toBe(initialDate);
        } finally {
            if (previousTimezone === undefined) {
                delete process.env.TZ;
            } else {
                process.env.TZ = previousTimezone;
            }
        }
        expect(initialDate).toBe('Feb 29, 2024');
        expect(formatXiamProposalInstant(noon)).toBe(
            'Oct 8, 2026, 12:00 PM (Asia/Manila, UTC+08:00)',
        );
        expect(formatXiamProposalInstant(midnight)).toBe(
            'Oct 8, 2026, 12:00 AM (Asia/Manila, UTC+08:00)',
        );
    });

    it('renders both absolute overnight interval endpoints and retains nonzero seconds', () => {
        const interval: XiamProposalValue = {
            type: 'interval',
            start: instant('2026-10-08T15:59:05Z'),
            end: instant('2026-10-09T00:00:07Z'),
        };
        const output = formatXiamProposalValue(interval);
        expect(output).toContain('Oct 8, 2026, 11:59:05 PM');
        expect(output).toContain('Oct 9, 2026, 8:00:07 AM');
        expect(output.match(/Asia\/Manila, UTC\+08:00/g)).toHaveLength(2);
    });

    it('distinguishes missing, null, empty, zero, false and unchanged values', () => {
        expect(formatXiamProposalValue({ type: 'missing' })).toBe(
            'Not provided',
        );
        expect(formatXiamProposalValue({ type: 'null' })).toBe('None');
        expect(
            formatXiamProposalValue({
                type: 'text',
                value: '',
                multiline: true,
            }),
        ).toBe('Empty');
        expect(
            formatXiamProposalValue({
                type: 'integer',
                value: 0,
                semantic: 'count',
                unit: 'applicants',
            }),
        ).toBe('0 applicants');
        expect(
            formatXiamProposalValue({
                type: 'integer',
                value: 1234567890,
                semantic: 'integer',
                unit: null,
            }),
        ).toBe('1234567890');
        expect(
            formatXiamProposalValue({
                type: 'boolean',
                value: false,
                true_label: 'Enabled',
                false_label: 'Disabled',
            }),
        ).toBe('Disabled');
        expect(
            formatXiamProposalFieldChange({
                key: 'enabled',
                label: 'Enabled',
                role: 'effect',
                change: 'unchanged',
                before: {
                    type: 'boolean',
                    value: false,
                    true_label: 'Enabled',
                    false_label: 'Disabled',
                },
                after: {
                    type: 'boolean',
                    value: false,
                    true_label: 'Enabled',
                    false_label: 'Disabled',
                },
            }),
        ).toBe('No change');
    });

    it('keeps exact long multiline content and safely formats lists, codes and durations', () => {
        const message = `First line\n${'complete reviewed message '.repeat(200)}\nFinal line`;
        expect(
            formatXiamProposalValue({
                type: 'text',
                value: message,
                multiline: true,
            }),
        ).toBe(message);
        expect(
            formatXiamProposalValue({
                type: 'code',
                value: '123456789012345678',
            }),
        ).toBe('123456789012345678');
        expect(
            formatXiamProposalValue({
                type: 'duration',
                value: 90,
                unit: 'minute',
            }),
        ).toBe('1 hour 30 minutes');
        expect(
            formatXiamProposalValue({
                type: 'duration',
                value: 0,
                unit: 'minute',
            }),
        ).toBe('0 minutes');
        expect(
            formatXiamProposalValue({
                type: 'list',
                items: [],
                empty_label: 'No reminders',
            }),
        ).toBe('No reminders');
        expect(
            formatXiamProposalValue({
                type: 'list',
                items: [
                    { type: 'code', value: '01' },
                    {
                        type: 'text',
                        value: 'Line one\nLine two',
                        multiline: true,
                    },
                ],
                empty_label: 'Empty',
            }),
        ).toBe('01\nLine one\nLine two');
    });
});

describe('Xiam action vocabulary', () => {
    it('provides exact-version labels for all 31 bounded mutation definitions only', () => {
        expect(new Set(ACTION_IDS).size).toBe(31);
        for (const id of ACTION_IDS) {
            expect(getXiamActionPresentation(id).title, id).not.toBe(
                'Review changes',
            );
        }
        expect(
            getXiamActionPresentation('consultation.decision.record@1').title,
        ).toBe('Review changes');
    });

    it('does not claim effects for retired enrollment proposals', () => {
        for (const id of ACTION_IDS.filter((candidate) =>
            candidate.startsWith('registrar.enrollment.'),
        )) {
            const presentation = getXiamActionPresentation(id);
            expect(presentation.title).toContain('retired');
            expect(presentation.successMessage).toContain('no new changes');
        }
    });
});
