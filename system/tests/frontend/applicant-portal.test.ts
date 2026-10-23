// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
    default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));

import Portal from '../../resources/js/Pages/Applicant/Portal.svelte';

afterEach(() => cleanup());

const steps = [
    { id: 'application', label: 'My Application', href: '/applicant/application/41', status: 'done' },
    { id: 'exam_scheduling', label: 'Exam Scheduling', href: '/applicant/schedule', status: 'current' },
    { id: 'exam_monitoring', label: 'Exam Day', href: '/applicant/exam-day', status: 'upcoming' },
    { id: 'scoring', label: 'Exam Scoring', href: '/applicant/exam-scoring', status: 'done' },
    { id: 'release_scheduling', label: 'Release Scheduling', href: '/applicant/release-schedule', status: 'current' },
    {
        id: 'release_encounter',
        label: 'Release Day',
        href: '/applicant/release-day',
        status: 'action',
        context: {
            result_release: { profile: { series: [{ score: 98.5 }] } },
            program_choice: { actions: [{ label: 'Choose a program' }] },
        },
    },
    { id: 'admission_clearance', label: 'Admission Clearance', href: '/applicant/admission-clearance', status: 'auto' },
];

function renderPortal(encounterStatus = 'action', schedulingStatus = 'current', myResults = false, clearanceStatus = 'auto') {
    return render(Portal, {
        props: {
            page_state: 'ready',
            identity: { name: 'Applicant' },
            stages: [
                { id: 'results', status: 'done', profile: { series: [{ score: 98.5 }] } },
            ],
            pipeline: [
                { id: 'application', label: 'Application', steps: [steps[0]] },
                { id: 'examination', label: 'Examination', steps: steps.slice(1, 4) },
                {
                    id: 'release_of_results',
                    label: 'Release of Results',
                    steps: [
                        { ...steps[4], status: schedulingStatus },
                        {
                            ...steps[5],
                            status: encounterStatus,
                            ...(myResults ? { href: '/applicant/results', detail_label: 'View My Results' } : {}),
                        },
                    ],
                },
                { id: 'admission_clearance', label: 'Admission Clearance', steps: [{ ...steps[6], status: clearanceStatus }] },
            ],
        },
    });
}

describe('applicant portal merged release milestone', () => {
    it('keeps canonical detail destinations while merging release scheduling into the encounter', () => {
        renderPortal();

        const links = screen.getAllByRole('link', { name: /^View details:/ });
        expect(links).toHaveLength(6);
        expect(
            links.map((link) => [
                link.getAttribute('aria-label'),
                new URL(link.getAttribute('href') ?? '', window.location.href).pathname,
            ]),
        ).toEqual(
            steps.filter((step) => step.id !== 'release_scheduling').map((step) => [
                `View details: ${step.id === 'release_encounter' ? 'Release of Results' : step.label}`,
                step.href,
            ]),
        );
    });

    it('keeps each left-rail status visible without rendering nested result or program actions', () => {
        const view = renderPortal();

        expect(screen.getAllByText('Completed')).toHaveLength(2);
        expect(screen.getAllByText('Current step')).toHaveLength(1);
        expect(screen.getByText('Upcoming')).toBeTruthy();
        expect(screen.getByText('Action needed')).toBeTruthy();
        expect(screen.getByText('In progress automatically')).toBeTruthy();
        expect(view.queryByText('98.5')).toBeNull();
        expect(screen.queryByRole('link', { name: 'Choose a program' })).toBeNull();
        expect(screen.getAllByRole('link', { name: /^View details:/ })).toHaveLength(6);
    });

    it('checks the merged release milestone after the confirmed decision without a pending scheduling node', () => {
        renderPortal('done', 'upcoming');
        const release = screen.getByRole('link', { name: 'View details: Release of Results' }).closest('li')!;
        expect(release.textContent).toContain('Released');
        expect(release.textContent).toContain('Completed');
        expect(release.textContent).not.toContain('Upcoming');
        expect(screen.queryByRole('link', { name: 'View details: Release Scheduling' })).toBeNull();
    });

    it('keeps booking reachable before the encounter without marking an assigned appointment released', () => {
        renderPortal('upcoming', 'current');
        expect(new URL(screen.getByRole('link', { name: 'View details: Release of Results' }).getAttribute('href')!, window.location.href).pathname).toBe('/applicant/release-schedule');
        expect(screen.queryByText('Released')).toBeNull();
    });

    it('does not complete release merely because scheduling is complete', () => {
        renderPortal('current', 'done');
        const release = screen.getByRole('link', { name: 'View details: Release of Results' }).closest('li')!;
        expect(release.textContent).toContain('Current step');
        expect(release.textContent).not.toContain('Completed');
        expect(screen.queryByText('Released')).toBeNull();
    });
    it('labels the completed Home destination as My Results when the server resolves a safe or pending copy', () => {
        renderPortal('done', 'upcoming', true);

        const link = screen.getByRole('link', { name: 'View My Results' });
        expect(new URL(link.getAttribute('href') ?? '', window.location.href).pathname).toBe('/applicant/results');
    });
    it('thanks applicants only when clearance confirmation marks the final step done', async () => {
        const view = renderPortal('done', 'upcoming', false, 'done');

        expect(screen.getByText('Thank you. Your NEXIAM admission clearance is complete.')).toBeTruthy();
        await view.rerender({
            page_state: 'ready',
            identity: { name: 'Applicant' },
            stages: [{ id: 'results', status: 'done', profile: { series: [{ score: 98.5 }] } }],
            pipeline: [
                { id: 'application', label: 'Application', steps: [steps[0]] },
                { id: 'examination', label: 'Examination', steps: steps.slice(1, 4) },
                {
                    id: 'release_of_results',
                    label: 'Release of Results',
                    steps: [
                        { ...steps[4], status: 'upcoming' },
                        { ...steps[5], status: 'done' },
                    ],
                },
                { id: 'admission_clearance', label: 'Admission Clearance', steps: [{ ...steps[6], status: 'action' }] },
            ],
        });
        expect(screen.queryByText('Thank you. Your NEXIAM admission clearance is complete.')).toBeNull();
    });
});
