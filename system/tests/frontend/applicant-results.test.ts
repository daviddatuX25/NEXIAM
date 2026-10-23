// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
  default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));

import Results from '../../resources/js/Pages/Applicant/Results.svelte';

afterEach(() => cleanup());

const baseProps = {
  page_state: 'unlocked' as const,
  release: { mode: 'online', released_at: '2026-10-19T10:00:00Z', version: 1 },
  score_profile: [],
  can_download_pdf: true,
  is_advised: false,
  attempt: 'APP-001',
};

describe('Applicant Results page', () => {
  it('offers attachment download and separate inline print controls only for a safe stored PDF', () => {
    render(Results, { props: baseProps });

    const download = screen.getByRole('link', { name: 'Download Official Result Sheet (PDF)' });
    expect(download.getAttribute('href')).toBe('/applicant/results/pdf?presentation=attachment');

    const print = screen.getByRole('link', { name: 'Print Official Result Sheet' });
    expect(print.getAttribute('href')).toBe('/applicant/results/pdf?presentation=inline');
    expect(print.getAttribute('target')).toBe('_blank');
    expect(print.getAttribute('rel')).toBe('noopener noreferrer');
  });

  it('shows completed F2F PDF preparation without suggesting scoring is still in progress', () => {
    render(Results, { props: { ...baseProps, page_state: 'preparing_pdf', can_download_pdf: false } });

    expect(screen.getByRole('heading', { name: 'Preparing your official PDF' })).toBeTruthy();
    expect(screen.queryByText('Scoring in Progress')).toBeNull();
    expect(screen.queryByRole('link', { name: 'Download Official Result Sheet (PDF)' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Print Official Result Sheet' })).toBeNull();
  });

  it('does not expose print or download controls when the stored PDF is unavailable', () => {
    render(Results, { props: { ...baseProps, page_state: 'unavailable', can_download_pdf: false } });

    expect(screen.getByRole('heading', { name: 'Result PDF unavailable' })).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Download Official Result Sheet (PDF)' })).toBeNull();
    expect(screen.queryByRole('link', { name: 'Print Official Result Sheet' })).toBeNull();
  });

  it('keeps official PDF controls reachable when score aliases share a code', () => {
    render(Results, { props: {
      ...baseProps,
      score_profile: [
        { area_code: 'PROFILE_VERBAL', area_name: 'Verbal Aptitude', score: 88, available: true },
        { area_code: 'PROFILE_VERBAL', area_name: 'Profile Verbal Reasoning', score: 91, available: true },
      ],
    } });
    expect(screen.getByRole('link', { name: 'Download Official Result Sheet (PDF)' })).toBeTruthy();
    expect(screen.getByRole('cell', { name: 'Verbal Aptitude' })).toBeTruthy();
    expect(screen.getByRole('cell', { name: 'Profile Verbal Reasoning' })).toBeTruthy();
  });
});
