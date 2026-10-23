// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor, within } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi, type Mock } from 'vitest';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';
import StudioBubblePanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioBubblePanel.svelte';

function jsonResponse(payload: Record<string, unknown> = {}, status = 200) {
  return { ok: status >= 200 && status < 300, status, json: async () => payload };
}

describe('Canonical Challenge Recovery in StudioExportVerifyPanel', () => {
  let fetchMock: Mock;

  beforeEach(() => {
    fetchMock = vi.fn().mockImplementation(async () => jsonResponse({ status: 'ok' }));
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    cleanup();
    document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
    document.head.replaceChildren();
    vi.unstubAllGlobals();
  });

  it('renders quiet repair_reference recovery row with "Update challenge sheet" heading and "Update sheet" button', () => {
    const run = {
      run_id: 101,
      status: 'awaiting_print',
      mode: 'quick_rehearsal',
      available_actions: ['print_challenge'],
      challenge: {
        challenge_id: 1,
        challenge_code: 'CHAL-REP-101',
        mode: 'quick',
        status: 'created',
        print_url: '/runs/101/sheet.pdf',
        guide_url: '/runs/101/guide.pdf',
      },
      challenge_recovery: {
        kind: 'repair_reference',
        action_url: '/setup/omr/templates/verification-runs/101/challenge/regenerate',
        reason_code: 'renderer_outdated',
        detail: 'Renderer outdated.',
      },
      evidence: [],
    };

    const { getByRole, getByText, queryByRole, queryByText } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'digital_verified' },
      urls: {},
      verification_run: run,
    });

    expect(getByText('Update challenge sheet')).not.toBeNull();
    expect(getByRole('button', { name: /Update sheet/i })).not.toBeNull();
    expect(queryByRole('button', { name: /Mark printed/i })).toBeNull();

    // Must never show technical renderer/manifest/plan language or duplicate badges
    expect(queryByText(/renderer/i)).toBeNull();
    expect(queryByText(/manifest/i)).toBeNull();
    expect(queryByText(/Repair required/i)).toBeNull();
  });

  it('executing repair_reference posts action_url with idempotency_key, hydrates status, and opens guide modal', async () => {
    const run = {
      run_id: 102,
      status: 'awaiting_print',
      mode: 'quick_rehearsal',
      available_actions: ['print_challenge'],
      challenge: {
        challenge_id: 2,
        challenge_code: 'CHAL-REP-102',
        mode: 'quick',
        status: 'created',
        print_url: '/runs/102/sheet.pdf',
        guide_url: '/runs/102/guide.pdf',
      },
      challenge_recovery: {
        kind: 'repair_reference',
        action_url: '/setup/omr/templates/verification-runs/102/challenge/regenerate',
        reason_code: 'renderer_outdated',
        detail: 'Renderer outdated.',
      },
      evidence: [],
    };

    fetchMock.mockImplementation(async (url: string) => {
      if (url.includes('/challenge/regenerate')) {
        return jsonResponse({
          status: 'awaiting_print',
          status_url: '/setup/omr/templates/verification-runs/102/status',
        });
      }
      if (url.includes('/status')) {
        return jsonResponse({
          run_id: 102,
          status: 'awaiting_print',
          mode: 'quick_rehearsal',
          available_actions: ['print_challenge'],
          challenge: {
            challenge_id: 2,
            challenge_code: 'CHAL-REP-102',
            mode: 'quick',
            status: 'created',
            print_url: '/runs/102/sheet.pdf',
            guide_url: '/runs/102/guide.pdf',
          },
          challenge_recovery: null,
          evidence: [],
        });
      }
      return jsonResponse({});
    });

    const { getByRole, queryByRole } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'digital_verified' },
      urls: {},
      verification_run: run,
    });

    const updateBtn = getByRole('button', { name: /Update sheet/i });
    await fireEvent.click(updateBtn);

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalled();
      const regenerateCall = fetchMock.mock.calls.find(([url]: [string]) => url.includes('/challenge/regenerate'));
      expect(regenerateCall).toBeDefined();
      const [, opts] = regenerateCall as [string, RequestInit];
      expect(opts.method).toBe('POST');
      const body = JSON.parse(opts.body as string);
      expect(body.idempotency_key).toBeDefined();
    });

    // Modal opens after repair
    await waitFor(() => {
      expect(queryByRole('dialog')).not.toBeNull();
    });
  });

  it('renders quiet restart_rehearsal recovery row with "Layout changed" heading, copy, and "Start new rehearsal" button', () => {
    const run = {
      run_id: 201,
      status: 'awaiting_print',
      mode: 'quick_rehearsal',
      available_actions: ['print_challenge'],
      challenge: {
        challenge_id: 3,
        challenge_code: 'CHAL-RES-201',
        mode: 'quick',
        status: 'created',
        print_url: '/runs/201/sheet.pdf',
        guide_url: '/runs/201/guide.pdf',
      },
      challenge_recovery: {
        kind: 'restart_rehearsal',
        action_url: '/setup/omr/templates/verification-runs/201/challenge/restart',
        reason_code: 'target_changed',
        detail: 'Target changed.',
      },
      evidence: [],
    };

    const { getByRole, getByText, queryByRole, queryByText } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'digital_verified' },
      urls: {},
      verification_run: run,
    });

    expect(getByText('Layout changed')).not.toBeNull();
    expect(getByText(/This test belongs to an older layout/)).not.toBeNull();
    expect(getByRole('button', { name: /Start new rehearsal/i })).not.toBeNull();
    expect(queryByRole('button', { name: /Mark printed/i })).toBeNull();
    expect(queryByRole('button', { name: /Create hand-shaded/i })).toBeNull();

    // No technical jargon or duplicate badges
    expect(queryByText(/plan_hash/i)).toBeNull();
    expect(queryByText(/manifest/i)).toBeNull();
  });

  it('executing restart_rehearsal prepares current target, starts digital verification, and keeps quick challenge locked', async () => {
    const run = {
      run_id: 202,
      status: 'awaiting_print',
      mode: 'quick_rehearsal',
      available_actions: ['print_challenge'],
      challenge: {
        challenge_id: 4,
        challenge_code: 'CHAL-RES-202',
        mode: 'quick',
        status: 'created',
        print_url: '/runs/202/sheet.pdf',
        guide_url: '/runs/202/guide.pdf',
      },
      challenge_recovery: {
        kind: 'restart_rehearsal',
        action_url: '/setup/omr/templates/verification-runs/202/challenge/restart',
        reason_code: 'target_changed',
        detail: 'Target changed.',
      },
      evidence: [],
    };

    fetchMock.mockImplementation(async (url: string) => {
      if (url.includes('/challenge/restart')) {
        return jsonResponse({
          run_id: 203,
          status: 'queued',
          status_url: '/setup/omr/templates/verification-runs/203/status',
          challenge_id: null,
          challenge_code: null,
          digital_reverification: true,
          lock_version: 11,
        });
      }
      if (url.includes('/203/status')) {
        return jsonResponse({
          run_id: 203,
          status: 'queued',
          phase: 'queued',
          mode: 'digital',
          available_actions: [],
          challenge: null,
          challenge_recovery: null,
          evidence: [],
          progress: { done: 0, total: 14, percent: 0 },
          run_version: 0,
          plan: { plan_version: 'v2', challenge_code: null, expected_vector_hash: null },
          target: { kind: 'candidate', id: 1, hashes: {} },
        });
      }
      return jsonResponse({});
    });

    // A LAN-IP page can keep a stale Inertia meta token after session rotation;
    // Laravel's encrypted XSRF cookie is the current same-origin token.
    document.head.innerHTML = '<meta name="csrf-token" content="stale-meta-token">';
    document.cookie = 'XSRF-TOKEN=encrypted%2Btoken%3D%3D; path=/';
    const { container, getByRole, queryByRole } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'digital_verified' },
      urls: {},
      verification_run: run,
    });

    const restartBtn = getByRole('button', { name: /Start new rehearsal/i });
    await fireEvent.click(restartBtn);

    await waitFor(() => {
      const restartCall = fetchMock.mock.calls.find(([url]: [string]) => url.includes('/challenge/restart'));
      expect(restartCall).toBeDefined();
      const [, opts] = restartCall as [string, RequestInit];
      expect(opts.method).toBe('POST');
      const headers = opts.headers as Record<string, string>;
      expect(headers['X-XSRF-TOKEN']).toBe('encrypted+token==');
      expect(headers['X-CSRF-TOKEN']).toBeUndefined();
      expect(opts.credentials).toBe('same-origin');
      const body = JSON.parse(opts.body as string);
      expect(body.idempotency_key).toBeDefined();
    });

    // Hydrates the new digital run, keeps quick challenge creation locked, and does NOT open modal.
    await waitFor(() => {
      const currentLi = container.querySelector('li[aria-current="step"]');
      expect(currentLi?.textContent).toContain('Digital engine check');
      expect(currentLi?.textContent).toContain('Waiting');
      expect(queryByRole('dialog')).toBeNull();
      expect(container.textContent).toContain('Current layout prepared');
      expect(container.textContent).toContain('A quick rehearsal will unlock after it passes.');
    });
  });

  it('displays safe actionable error detail and code in technical details when recovery fails', async () => {
    const run = {
      run_id: 301,
      status: 'awaiting_print',
      mode: 'quick_rehearsal',
      available_actions: ['print_challenge'],
      challenge: {
        challenge_id: 6,
        challenge_code: 'CHAL-ERR-301',
        mode: 'quick',
        status: 'created',
        print_url: '/runs/301/sheet.pdf',
      },
      challenge_recovery: {
        kind: 'restart_rehearsal',
        action_url: '/setup/omr/templates/verification-runs/301/challenge/restart',
        reason_code: 'target_changed',
        detail: 'Target changed.',
      },
      evidence: [],
    };

    fetchMock.mockImplementation(async (url: string) => {
      if (url.includes('/challenge/restart')) {
        return jsonResponse(
          {
            code: 'restart_conflict',
            detail: 'The challenge cannot be restarted while captures are active.',
          },
          422,
        );
      }
      return jsonResponse({});
    });

    const { container, getByRole, getByText } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'digital_verified' },
      urls: {},
      verification_run: run,
    });

    const restartBtn = getByRole('button', { name: /Start new rehearsal/i });
    await fireEvent.click(restartBtn);

    await waitFor(() => {
      expect(getByText('The challenge cannot be restarted while captures are active.')).not.toBeNull();
    });

    // Technical details are in expandable section
    const details = container.querySelector('details');
    expect(details).not.toBeNull();
    expect(details?.textContent).toContain('restart_conflict');
  });

  it('renders no recovery action buttons when challenge_recovery is null (evidence_immutable)', () => {
    const run = {
      run_id: 401,
      status: 'awaiting_capture',
      mode: 'quick_rehearsal',
      available_actions: ['submit_capture'],
      challenge: {
        challenge_id: 7,
        challenge_code: 'CHAL-IMM-401',
        mode: 'quick',
        status: 'printed',
        print_url: '/runs/401/sheet.pdf',
        guide_url: '/runs/401/guide.pdf',
      },
      challenge_recovery: {
        kind: null,
        action_url: null,
        reason_code: 'evidence_immutable',
        detail: 'Captures exist; evidence is immutable.',
      },
      evidence: [
        {
          capture_id: 1,
          attempt: 1,
          page_no: 1,
          status: 'pass',
          error_code: null,
          variant: null,
          expected: null,
          actual: null,
          input_hash: null,
          evidence_hash: null,
          expected_vector_hash: null,
          detected_vector_hash: null,
          vector_match: true,
          summary: null,
          captured_at: '2026-08-29T10:00:00Z',
          urls: { input: null, aligned: null, overlay: null },
        },
      ],
    };

    const { queryByRole, queryByText } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'digital_verified' },
      urls: {},
      verification_run: run,
    });

    // No contradictory update or restart buttons
    expect(queryByRole('button', { name: /Update sheet/i })).toBeNull();
    expect(queryByRole('button', { name: /Start new rehearsal/i })).toBeNull();
    expect(queryByText('Update challenge sheet')).toBeNull();
    expect(queryByText('Layout changed')).toBeNull();
  });
});

describe('Digital start target isolation in StudioExportVerifyPanel', () => {
  afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
  });

  it('clears stale physical state before polling and ignores a stale status response', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    const stalePhysicalRun = {
      run_id: 18,
      status: 'awaiting_capture',
      mode: 'quick_rehearsal',
      target_current: false,
      target_stale: true,
      available_actions: ['submit_capture'],
      challenge: {
        challenge_id: 8,
        challenge_code: 'STALE-18',
        mode: 'quick',
        status: 'printed',
        print_url: '/runs/18/sheet.pdf',
        guide_url: '/runs/18/guide.pdf',
      },
      evidence: [],
    };
    fetchMock.mockImplementation(async (url: string) => {
      if (url === '/start') {
        return jsonResponse({
          run_id: 203,
          status: 'queued',
          mode: 'digital',
          target_current: true,
          target_stale: false,
          digital_verification_current: false,
          status_url: '/runs/203/status',
        }, 202);
      }
      if (url === '/runs/203/status') {
        // A proxy/cache can briefly return the old physical row; it must not
        // be merged into the newly started digital run.
        return jsonResponse({ ...stalePhysicalRun, run_id: 18 });
      }
      return jsonResponse({});
    });

    const { container, getByRole } = render(StudioExportVerifyPanel, {
      draft: { id: 13, stage: 'mapping_complete', mapping_current: true, digital_verification_current: false },
      urls: { digital_verification: '/start' },
      verification_run: stalePhysicalRun,
    });

    await fireEvent.click(getByRole('button', { name: /Run digital verification/i }));
    await waitFor(() => {
      expect(fetchMock.mock.calls.some(([url]: [string]) => url === '/runs/203/status')).toBe(true);
      expect(container.textContent).toContain('Digital engine check');
      expect(container.textContent).toContain('0 of 5 complete');
      expect(container.textContent).not.toContain('1 of 5 complete');
    });
  });
});

describe('Typed Preview Errors in StudioBubblePanel', () => {
  afterEach(() => {
    cleanup();
  });

  it('displays safe actionable detail in headline and terse code in technical details', () => {
    const { getByText, queryByText } = render(StudioBubblePanel, {
      previewError: {
        code: 'marker_missing',
        detail: 'Fiducial markers could not be detected on the sheet.',
      },
    });

    expect(getByText('Recognition mapping needs attention: Fiducial markers could not be detected on the sheet.')).not.toBeNull();
    const technicalDetails = getByText('Technical details');
    expect(technicalDetails).not.toBeNull();
    expect(technicalDetails.parentElement?.textContent).toContain('marker_missing');
    // Ensure code is not repeated in headline
    expect(queryByText('Recognition mapping needs attention: marker_missing')).toBeNull();
  });

  it('displays safe detail without technical details section when no code is provided', () => {
    const { getByText, queryByText } = render(StudioBubblePanel, {
      previewError: 'Sheet dimensions exceed printable bounds.',
    });

    expect(getByText('Recognition mapping needs attention: Sheet dimensions exceed printable bounds.')).not.toBeNull();
    expect(queryByText('Technical details')).toBeNull();
  });

  it('preserves specific safe worker detail and avoids generic worker phrase', () => {
    const { getByText, queryByText } = render(StudioBubblePanel, {
      previewError: {
        code: 'unsafe_shift',
        detail: 'Mapped bubble fields extend beyond safe printable margins.',
      },
    });

    expect(getByText('Recognition mapping needs attention: Mapped bubble fields extend beyond safe printable margins.')).not.toBeNull();
    expect(queryByText(/The OMR worker rejected the layout verification/i)).toBeNull();
  });

  it('formats previewError object with message property without rendering [object Object]', () => {
    const { getByText, queryByText } = render(StudioBubblePanel, {
      previewError: {
        message: 'Invalid coordinate bounds for bubble grid.',
      },
    });

    expect(getByText('Recognition mapping needs attention: Invalid coordinate bounds for bubble grid.')).not.toBeNull();
    expect(queryByText(/\[object Object\]/)).toBeNull();
  });

  it('formats previewError object with code property without rendering [object Object]', () => {
    const { getByText, queryByText } = render(StudioBubblePanel, {
      previewError: {
        code: 'marker_missing',
      },
    });

    expect(getByText('Recognition mapping needs attention: marker_missing')).not.toBeNull();
    expect(queryByText(/\[object Object\]/)).toBeNull();
  });
});
