// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import XiamProposalCard from '../../resources/js/Components/kit/Xiam/XiamProposalCard.svelte';
import { getXiamActionPresentation } from '../../resources/js/lib/xiam-presentation';

afterEach(cleanup);

const baseProposal = {
    id: '4d6eaa41-1d86-4a09-bb40-8f358404e692',
    capability: 'exams.session.create@1',
    title: 'Allowlisted action proposal',
    status: 'pending',
    confirmation_phrase: 'CREATE EXAM SESSION',
    requires_reauth: true,
    rows: [{ entity: 'Room', current: 'None', proposed: 'Room 7' }],
};

describe('Xiam proposal presentation', () => {
    it('shows failed checks as failures and prevents confirmation', () => {
        const onConfirm = vi.fn();
        render(XiamProposalCard, {
            props: {
                proposal: {
                    ...baseProposal,
                    preflight: [{ key: 'room_suitable', status: 'fail', label: 'capability.exam@1' }],
                },
                onConfirm,
            },
        });


        expect(screen.getByText(/choose a room configured for exams/i)).toBeTruthy();
        expect(screen.queryByText(/room is suitable/i)).toBeNull();
        expect(screen.getByText('Needs attention')).toBeTruthy();
        expect(screen.queryByText('Passed')).toBeNull();
        expect(screen.getByRole('button', { name: /confirm & execute/i }).hasAttribute('disabled')).toBe(true);
        expect(screen.getByRole('status').textContent).toContain('Recent sign-in required');
        expect(screen.queryByText(/capability\.exam/)).toBeNull();
    });

    it('requires the exact confirmation phrase and passes it unchanged', async () => {
        const onConfirm = vi.fn();
        const v1Proposal = {
            ...baseProposal,
            proposal_view: {
                version: 1,
                proposal_id: baseProposal.id,
                run_id: '7b282c0f-6242-4217-ba55-9005080e74e6',
                capability_id: baseProposal.capability,
                operation: 'create',
                title: baseProposal.title,
                sections: [
                    {
                        kind: 'fields',
                        key: 'details',
                        label: 'Details',
                        fields: [
                            {
                                key: 'room',
                                label: 'Room',
                                role: 'choice',
                                change: 'set',
                                before: { type: 'missing' },
                                after: { type: 'text', value: 'Room 7', multiline: false },
                            },
                        ],
                    },
                ],
                preflight: { outcome: 'pass', checks: [] },
                safety: {
                    tier: 't2',
                    confirmation_phrase: baseProposal.confirmation_phrase,
                    requires_reauth: true,
                    expires_at: '2026-10-08T02:00:00Z',
                },
                status: 'pending',
                confirmable: true,
                error: null,
                receipt: null,
            },
        };
        render(XiamProposalCard, { props: { proposal: v1Proposal, onConfirm } });
        const button = screen.getByRole('button', { name: /confirm & execute/i });
        const input = screen.getByRole('textbox', { name: /type create exam session to confirm/i });
        expect(button.hasAttribute('disabled')).toBe(true);
        await fireEvent.input(input, { target: { value: 'create exam session' } });
        expect(button.hasAttribute('disabled')).toBe(true);
        await fireEvent.input(input, { target: { value: 'CREATE EXAM SESSION' } });
        expect(button.hasAttribute('disabled')).toBe(false);
        await fireEvent.click(button);
        expect(onConfirm).toHaveBeenCalledWith({ phrase: 'CREATE EXAM SESSION' });
    });

    it('shows older format notice and prevents confirmation when decodedView is null', () => {
        const onConfirm = vi.fn();
        render(XiamProposalCard, {
            props: {
                proposal: baseProposal,
                onConfirm,
            },
        });

        expect(
            screen.getByText(
                'This proposal uses an older format and is no longer actionable. Please submit a new request.',
            ),
        ).toBeTruthy();
        const button = screen.getByRole('button', { name: /confirm & execute/i });
        expect(button.hasAttribute('disabled')).toBe(true);
    });

    it('shows edits with explicit Before and After values', () => {
        render(XiamProposalCard, {
            props: {
                proposal: {
                    ...baseProposal,
                    confirmation_phrase: null,
                    rows: [{ entity: 'Room capacity', current: '20', proposed: '30' }],
                },
            },
        });
        expect(screen.getByText('Before:').parentElement?.textContent).toContain('20');
        expect(screen.getByText('After:').parentElement?.textContent).toContain('30');
    });

    it('collapses committed proposals to one completion line without internal identifiers', () => {
        const { container } = render(XiamProposalCard, {
            props: {
                proposal: {
                    ...baseProposal,
                    status: 'committed',
                    preflight: [{ key: 'room_suitable', status: 'pass' }],
                    rows: [{ entity: 'Room', current: 'None', proposed: 'Room 7' }],
                    digest: 'sha256:private-digest-value',
                    safety_tier: 't3',
                },
            },
        });

        expect(screen.getByRole('status').textContent).toContain('Exam session created.');
        expect(container.textContent).not.toContain('Room 7');
        expect(container.textContent).not.toContain('Passed');
        expect(container.textContent).not.toContain(baseProposal.id);
        expect(container.textContent).not.toMatch(/capability|tier|digest|sha256|exams\.session/);
    });

    it('uses neutral presentation for unknown action identifiers', () => {
        expect(getXiamActionPresentation('unmapped.domain.action@9', 'Allowlisted action proposal')).toEqual({
            title: 'Review changes',
            successMessage: 'Changes saved.',
        });
        expect(getXiamActionPresentation('unknown@1', 'Readable supplied title')).toEqual({
            title: 'Readable supplied title',
            successMessage: 'Changes saved.',
        });
    });
});
