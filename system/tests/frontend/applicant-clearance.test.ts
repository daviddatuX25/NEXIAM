// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/Pages/Applicant/ApplicantLayout.svelte', () => ({
  default: (_anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(_anchor),
}));

import AdmissionClearance from '../../resources/js/Pages/Applicant/AdmissionClearance.svelte';

afterEach(() => cleanup());
function linkPathAndQuery(href: string | null): string | null {
  if (href === null) return null;
  const destination = new URL(href, 'http://localhost');
  return `${destination.pathname}${destination.search}`;
}

const revokedWithHistoricalSheet = {
  status: 'revoked' as const,
  requirements: {
    items: [
      {
        id: 1,
        code: 'BIRTH-CERTIFICATE',
        label: 'Certificate of live birth',
        item_kind: 'document',
        is_mandatory: true,
        latest_status: 'verified',
        latest_verified_at: '2026-03-01T00:00:00Z',
        applicant_instruction: 'Bring the original document when requested.',
      },
    ],
    all_mandatory_verified: true,
    mandatory_pending_count: 0,
  },
  correction_requests: [],
  latest_receipt: {
    status: 'revoked',
    confirmed_at: null,
    revoked_at: '2026-04-05T10:20:00Z',
  },
  historical_sheet_issue: {
    receipt_number: 'ESH-OLD',
    issued_at: '2026-03-04T09:15:00Z',
    belongs_to_latest_clearance: false,
  },
};

describe('admission clearance page', () => {
  it('keeps a historical sheet issue separate from the latest revoked clearance', () => {
    render(AdmissionClearance, {
      props: { page_state: 'ready', clearance: revokedWithHistoricalSheet },
    });

    expect(screen.getByRole('heading', { name: 'Clearance reopened' })).toBeTruthy();
    expect(screen.getByText('ESH-OLD')).toBeTruthy();
    expect(linkPathAndQuery(screen.getByRole('link', { name: 'Message the Registrar' }).getAttribute('href')))
      .toBe('/applicant/admission-support?group=offices');
    expect(screen.getByText(/Certificate of live birth/).textContent).toContain('Required');
    expect(screen.getByText('Bring the original document when requested.')).toBeTruthy();
  });

  it('offers a Registrar contact path when the account has no application', () => {
    render(AdmissionClearance, { props: { page_state: 'no_attempt', clearance: null } });

    expect(screen.getByRole('heading', { name: 'No application found' })).toBeTruthy();
    expect(linkPathAndQuery(screen.getByRole('link', { name: 'Message the Registrar' }).getAttribute('href')))
      .toBe('/applicant/admission-support?group=offices');
  });

  it('shows completion only for current confirmed clearance and withdraws it on reopening', async () => {
    const view = render(AdmissionClearance, {
      props: {
        page_state: 'ready',
        clearance: {
          ...revokedWithHistoricalSheet,
          status: 'confirmed',
          latest_receipt: { ...revokedWithHistoricalSheet.latest_receipt, status: 'confirmed', revoked_at: null },
          historical_sheet_issue: null,
        },
      },
    });
    expect(screen.getByRole('heading', { name: 'Admission clearance confirmed' })).toBeTruthy();
    expect(screen.getByText(/Thank you for completing your application/)).toBeTruthy();
    expect(screen.queryByText('Historical sheet handoff')).toBeNull();
    await view.rerender({ page_state: 'ready', clearance: revokedWithHistoricalSheet });
    expect(screen.queryByText(/Thank you for completing your application/)).toBeNull();
    expect(screen.getByRole('heading', { name: 'Clearance reopened' })).toBeTruthy();
  });

  it.each(['not_ready', 'ready'] as const)('does not treat %s or historical handoff as completion', (status) => {
    render(AdmissionClearance, {
      props: { page_state: 'ready', clearance: { ...revokedWithHistoricalSheet, status, latest_receipt: null } },
    });
    expect(screen.queryByRole('heading', { name: 'Admission clearance confirmed' })).toBeNull();
    expect(screen.queryByText(/Thank you for completing your application/)).toBeNull();
    expect(screen.getByText('ESH-OLD')).toBeTruthy();
  });
  it('shows only explicit current correction items and applicant instructions', () => {
    render(AdmissionClearance, {
      props: {
        page_state: 'ready',
        clearance: {
          ...revokedWithHistoricalSheet,
          correction_requests: [{
            recorded_at: '2026-04-10T09:00:00Z',
            items: [{ label: 'Transcript', applicant_instruction: 'Upload a readable copy.' }],
          }],
        },
      },
    });

    expect(screen.getByRole('heading', { name: 'Requested document corrections' })).toBeTruthy();
    expect(screen.getByText('Upload a readable copy.')).toBeTruthy();
  });
  });
