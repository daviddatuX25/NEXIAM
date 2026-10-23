// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));

import ApplicantHome from '../../resources/js/Pages/Applicant/ApplicantHome.svelte';

afterEach(() => cleanup());

const canonicalPath = (link: HTMLElement): string => new URL(link.getAttribute('href')!, window.location.origin).pathname;

it('keeps both authorized Release Day destinations reachable from the grouped milestone and marks the selected route', () => {
    render(ApplicantHome, {
        props: {
            page_state: 'ready',
            stages: [],
            compact: true,
            showTour: false,
            selectedHref: '/applicant/release-day',
            pipeline: [
                { id: 'release_of_results', label: 'Release of Results', steps: [
                    { id: 'release_scheduling', label: 'Release Day Scheduling', status: 'done', href: '/applicant/release-schedule' },
                    { id: 'release_encounter', label: 'Release Day', status: 'done', href: '/applicant/results', detail_label: 'View My Results' },
                ] },
            ],
            secondaryLinks: [
                { key: 'release_schedule', label: 'Release Day Scheduling', href: '/applicant/release-schedule' },
                { key: 'release_day', label: 'Release Day', href: '/applicant/release-day' },
                { key: 'results', label: 'My Results', href: '/applicant/results' },
                { key: 'program_choice', label: 'Program Choice', href: '/applicant/program-choice' },
                { key: 'access_history', label: 'Privacy & access history', href: '/applicant/access-history' },
            ],
        },
    });

    const releaseDayLink = screen.getByRole('link', { name: 'Release Day' });
    expect(canonicalPath(releaseDayLink)).toBe('/applicant/release-day');
    expect(releaseDayLink.getAttribute('aria-current')).toBe('page');
    expect(canonicalPath(screen.getByRole('link', { name: 'Release Day Scheduling' }))).toBe('/applicant/release-schedule');
    expect(canonicalPath(screen.getByRole('link', { name: 'View My Results' }))).toBe('/applicant/results');
    expect(canonicalPath(screen.getByRole('link', { name: 'Your program plans' }))).toBe('/applicant/program-choice');
    expect(canonicalPath(screen.getByRole('link', { name: 'Privacy & access history' }))).toBe('/applicant/access-history');
});
