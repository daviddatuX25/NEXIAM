// @vitest-environment jsdom

import { createRawSnippet } from 'svelte';
import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import DocumentRequirementCard from '../../resources/js/Components/Admission/DocumentRequirementCard.svelte';

afterEach(() => cleanup());

describe('DocumentRequirementCard', () => {
    it('renders documents and containers with the matching item kind label', () => {
        const { container, rerender } = render(DocumentRequirementCard, {
            label: 'Birth certificate',
            itemKind: 'document',
        });

        expect(container.querySelector('[data-requirement-card]')?.getAttribute('data-item-kind')).toBe('document');
        expect(screen.getByText('Document / Paper')).toBeTruthy();

        rerender({ label: 'Birth certificate', itemKind: 'container' });

        expect(container.querySelector('[data-requirement-card]')?.getAttribute('data-item-kind')).toBe('container');
        expect(screen.getByText('Folder / Envelope')).toBeTruthy();
        expect(screen.queryByText('Document / Paper')).toBeNull();
    });

    it('shows code, requirement status, track, and an accessible label heading', () => {
        const { container } = render(DocumentRequirementCard, {
            id: 'requirement-cedula',
            label: 'Certificate of live birth',
            code: 'DOC-001',
            isMandatory: true,
            trackLabel: 'First-year applicant',
        });

        const card = container.querySelector('[data-requirement-card]');
        const heading = screen.getByRole('heading', { name: 'Certificate of live birth' });

        expect(card?.getAttribute('aria-labelledby')).toBe('requirement-cedula');
        expect(heading.getAttribute('id')).toBe('requirement-cedula');
        expect(screen.getByText('DOC-001')).toBeTruthy();
        expect(screen.getByText('Mandatory')).toBeTruthy();
        expect(screen.getByText('First-year applicant')).toBeTruthy();
    });

    it('shows optional status and keeps provided actions in a nowrap action cell', () => {
        const { container } = render(DocumentRequirementCard, {
            label: 'Transfer credential',
            isMandatory: false,
            actions: createRawSnippet(() => ({ render: () => '<span>Action</span>' })),
        });

        expect(screen.getByText('Optional')).toBeTruthy();

        const actionCell = container.querySelector('[data-requirement-card-actions]');
        expect(actionCell?.classList.contains('flex-nowrap')).toBe(true);
        expect(actionCell?.classList.contains('whitespace-nowrap')).toBe(true);
        expect(actionCell?.classList.contains('shrink-0')).toBe(true);
    });

    it('applies compact spacing classes and hides empty content wrappers when density is compact', () => {
        const { container } = render(DocumentRequirementCard, {
            label: 'Compact requirement card',
            density: 'compact',
            content: createRawSnippet(() => ({ render: () => '<span></span>' })),
            actions: createRawSnippet(() => ({ render: () => '<span>Compact action</span>' })),
        });

        const header = container.querySelector('header');
        const heading = screen.getByRole('heading', { name: 'Compact requirement card' });
        const actionCell = container.querySelector('[data-requirement-card-actions]');

        expect(header?.className).toContain('px-3 py-1.5');
        expect(heading.className).toContain('text-sm font-semibold leading-snug');
        expect(actionCell?.className).toContain('px-3 py-1.5');
    });
});
