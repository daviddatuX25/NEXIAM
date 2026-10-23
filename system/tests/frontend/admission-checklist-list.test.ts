// @vitest-environment jsdom

import { cleanup, render, screen, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';

import AdmissionIndex from '../../resources/js/Pages/Admission/Index.svelte';

afterEach(() => cleanup());

describe('admission checklist list table', () => {
    it('keeps program, status, submitted date, and review action in their table columns', () => {
        const submittedAt = '2025-03-04T12:00:00';
        const expectedDate = new Date(submittedAt).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });

        render(AdmissionIndex, {
            attempts: {
                data: [
                    {
                        id: 42,
                        version: 3,
                        reference_code: 'APP-2025-0042',
                        applicant_name: 'Ari Santos',
                        contact_email: 'ari@example.test',
                        status: 'accepted',
                        preferred_program_code: 'BSIT',
                        program_preferences: ['BSIT', 'BSCS'],
                        submitted_at: submittedAt,
                        cycle_name: '2025–2026 · First Semester',
                        document_summary: {
                            mandatory_pending_count: 2,
                            deficient_count: 0,
                            missing_count: 2,
                            status: 'current',
                        },
                    },
                ],
                current_page: 1,
                last_page: 1,
                per_page: 25,
                total: 1,
                from: 1,
                to: 1,
                links: [],
            },
            statuses: [
                { value: 'accepted', label: 'Accepted' },
                { value: 'pending', label: 'Pending' },
            ],
            cycles: [],
            status_counts: { all: 1, accepted: 1 },
            capabilities: { accept: false, dismiss: false, correct: false },
        });

        const headers = screen.getAllByRole('columnheader');
        expect(headers).toHaveLength(7);
        expect(headers.map((header) => header.textContent?.trim())).toEqual([
            '',
            'NOA / Reference',
            'Applicant',
            'Program',
            'Status',
            'Date Submitted',
            'Actions',
        ]);

        const row = screen.getAllByRole('row')[1];
        const cells = within(row).getAllByRole('cell');
        expect(cells).toHaveLength(7);
        expect(cells[3].textContent).toContain('BSIT');
        expect(cells[3].textContent).toContain('+1 more');
        const statusText = cells[4].textContent?.replace(/\s+/g, ' ').trim();
        expect(statusText).toContain('Accepted');
        expect(statusText).toContain('2 mandatory documents pending');
        expect(cells[5].textContent).toContain(expectedDate);

        const reviewLink = within(cells[6]).getByRole('link', {
            name: 'Review',
        });
        expect(
            new URL(reviewLink.getAttribute('href') ?? '', window.location.href)
                .pathname,
        ).toBe('/admissions/42');
    });

    it('renders Accept & verify all documents action on card view with dedicated action row', () => {
        render(AdmissionIndex, {
            attempts: {
                data: [
                    {
                        id: 43,
                        version: 1,
                        reference_code: 'APP-2025-0043',
                        applicant_name: 'Jordan Cruz',
                        contact_email: 'jordan@example.test',
                        status: 'pending',
                        preferred_program_code: 'BSCS',
                        program_preferences: ['BSCS'],
                        submitted_at: '2025-03-05T10:00:00',
                        cycle_name: '2025–2026 · First Semester',
                        document_summary: {
                            mandatory_pending_count: 1,
                            deficient_count: 0,
                            missing_count: 1,
                            status: 'current',
                        },
                    },
                ],
                current_page: 1,
                last_page: 1,
                per_page: 25,
                total: 1,
                from: 1,
                to: 1,
                links: [],
            },
            statuses: [
                { value: 'accepted', label: 'Accepted' },
                { value: 'pending', label: 'Pending' },
            ],
            cycles: [],
            status_counts: { all: 1, pending: 1 },
            capabilities: {
                accept: true,
                dismiss: true,
                correct: true,
                verify_documents: true,
            },
        });

        // Check that "Accept & verify all documents" buttons are rendered (both in table and card view snippets)
        const verifyAllBtns = screen.getAllByRole('button', {
            name: 'Accept & verify all documents',
        });
        expect(verifyAllBtns.length).toBeGreaterThanOrEqual(1);
        expect(verifyAllBtns[0].getAttribute('title')).toBe(
            'Accept application and verify all document requirements',
        );

        // Check that Accept and Dismiss are also rendered
        expect(
            screen.getAllByRole('button', { name: 'Accept' }).length,
        ).toBeGreaterThanOrEqual(1);
        expect(
            screen.getAllByRole('button', { name: 'Dismiss' }).length,
        ).toBeGreaterThanOrEqual(1);
    });
});
