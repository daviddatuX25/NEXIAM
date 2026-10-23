// @vitest-environment jsdom

import { cleanup, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import StudioExportVerifyPanel from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/StudioExportVerifyPanel.svelte';

afterEach(() => cleanup());

describe('Studio digital verification queue', () => {
  it('shows planned work without presenting an unclaimed run as active 0/0 progress', () => {
    const { container } = render(StudioExportVerifyPanel, {
      draft: { id: 1, stage: 'mapping_complete', mapping_current: true },
      urls: { digital_verification: '/verify' },
      verification_run: {
        run_id: 9,
        status: 'queued',
        mode: 'digital',
        target_current: true,
        progress: { done: 0, total: 0, percent: 0 },
        evidence: [],
      },
    });

    expect(container.textContent).toContain('Waiting');
    expect(container.textContent).toContain('0 / 14 variants planned');
    expect(container.textContent).not.toContain('Running digital verification…');
    expect(container.textContent).not.toContain('0 / 0 variants');
    expect(container.querySelector('[role="progressbar"]')).toBeNull();
  });
});
