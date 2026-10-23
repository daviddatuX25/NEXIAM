// @vitest-environment jsdom

import { cleanup, fireEvent, render, waitFor } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import OmrTemplateStudio from '../../resources/js/Pages/Setup/OmrConfiguration/Canonical/OmrTemplateStudio.svelte';

afterEach(() => {
  cleanup();
  vi.unstubAllGlobals();
});

describe('Sheet Designer marker margin persistence', () => {
  it('hydrates saved content and fiducial insets independently', async () => {
    const { container } = render(OmrTemplateStudio, {
      draft: {
        id: 1,
        name: 'Persisted HTML Sheet',
        code: 'PERSISTED-HTML',
        source_kind: 'html',
        stage: 'draft',
        lock_version: 3,
        marker_profile_id: 7,
        authoring: {
          marker: {
            insets_mm: { top: 9, right: 10, bottom: 11, left: 12 },
          },
          studio: {
            current_stage: 1,
            stage1Mode: 'html',
            baseType: 'html',
            marginMm: 23,
            components: [],
            sections: [],
          },
        },
      },
      marker_profiles: [{ id: 7, code: 'target', version: 1, status: 'candidate' }],
      aptitude_areas: [],
      urls: {},
    });

    await waitFor(() => {
      expect(container.textContent).toContain('23 mm');
      expect((container.querySelector('input[aria-label=\"Top fiducial inset\"]') as HTMLInputElement)?.value).toBe('9');
    });
  });

  it('previews and persists each Sheet Designer fiducial inset through marker authority', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ status: 'saved', marker_profile_id: 7, lock_version: 4 }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const { container, getByLabelText } = render(OmrTemplateStudio, {
      draft: {
        id: 1,
        name: 'Adjustable Fiducials',
        code: 'ADJUSTABLE-FIDUCIALS',
        source_kind: 'html',
        stage: 'draft',
        lock_version: 3,
        marker_profile_id: 7,
        authoring: {
          marker: {
            insets_mm: { top: 12, right: 12, bottom: 14, left: 12 },
          },
          studio: {
            current_stage: 1,
            stage1Mode: 'html',
            baseType: 'html',
            marginMm: 23,
            components: [],
            sections: [],
          },
        },
      },
      marker_profiles: [{ id: 7, name: 'OMRChecker', code: 'target', version: 1, status: 'candidate' }],
      aptitude_areas: [],
      urls: {
        mapping: '/setup/omr/templates/canonical/drafts/1/mapping',
      },
    });

    const topInset = getByLabelText('Top fiducial inset');
    await fireEvent.input(topInset, { target: { value: '18' } });
    expect(container.querySelector('.marker-target')?.getAttribute('style')).toContain('top: 44.568px');

    await fireEvent.change(topInset);
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));

    const [, request] = fetchMock.mock.calls[0];
    expect(JSON.parse(String(request.body))).toMatchObject({
      marker_profile_id: 7,
      lock_version: 3,
      insets: { top: 18, right: 12, bottom: 14, left: 12 },
    });
  });
});
