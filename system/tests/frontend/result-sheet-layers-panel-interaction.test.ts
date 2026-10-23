// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ResultSheetLayersPanel from '../../resources/js/Components/ResultSheet/ResultSheetLayersPanel.svelte';

describe('ResultSheetLayersPanel nested lock controls', () => {
  afterEach(() => {
    cleanup();
  });

  const components = [
    {
      id: 'field-1',
      type: 'field',
      x: 10,
      y: 10,
      w: 80,
      h: 12,
      locked: false,
      props: { label: 'Applicant No', binding: 'applicant_reference' },
    },
  ];

  function renderPanel() {
    const onSelect = vi.fn();
    const onCommit = vi.fn();
    return {
      ...render(ResultSheetLayersPanel, {
        components,
        selectedIds: new Set(),
        primaryId: null,
        builderCatalog: { field: { label: 'Field' } },
        onSelect,
        onCommit,
        onDelete: vi.fn(),
        onAnnounce: vi.fn(),
      }),
      onSelect,
      onCommit,
    };
  }

  it.each([
    ['Enter', 'Enter'],
    [' ', 'Space'],
  ])('activates the lock button with %s without selecting the row', async (key, code) => {
    const { container, onSelect, onCommit } = renderPanel();
    const lockButton = container.querySelector('button[aria-label="Lock layer"]') as HTMLButtonElement;
    const row = container.querySelector('[data-result-sheet-layer="field-1"]') as HTMLElement;

    expect(lockButton).not.toBeNull();
    // jsdom does not synthesize the browser's native button click from keyboard events.
    await fireEvent.keyDown(lockButton, { key, code });
    await fireEvent.keyUp(lockButton, { key, code });
    await fireEvent.click(lockButton);

    expect(onSelect).not.toHaveBeenCalled();
    expect(onCommit).toHaveBeenCalledTimes(1);
    expect(onCommit.mock.calls[0][0]).toEqual([{ ...components[0], locked: true }]);
    expect(row.getAttribute('aria-selected')).toBe('false');
  });

  it('toggles a layer from the lock button without bubbling row selection', async () => {
    const onSelect = vi.fn();
    const onCommit = vi.fn();
    const { container } = render(ResultSheetLayersPanel, {
      components,
      selectedIds: new Set(),
      primaryId: null,
      builderCatalog: { field: { label: 'Field' } },
      onSelect,
      onCommit,
      onDelete: vi.fn(),
      onAnnounce: vi.fn(),
    });

    const lockButton = container.querySelector('button[aria-label="Lock layer"]') as HTMLButtonElement;
    await fireEvent.click(lockButton);

    expect(onSelect).not.toHaveBeenCalled();
    expect(onCommit).toHaveBeenCalledTimes(1);
    expect(onCommit.mock.calls[0][0]).toEqual([{ ...components[0], locked: true }]);
  });
});
