import { describe, expect, it } from 'vitest';
import { isNexiSessionView } from '../../resources/js/types/nexi';

describe('nexi session still validates after run governance', () => {
  it('accepts session with populated conversation cursor', () => {
    expect(
      isNexiSessionView({
        contract_version: '1',
        surface: { key: 'staff', version: 1 },
        context_epoch: 'epoch',
        page_pack: null,
        publication_version: null,
        availability: 'degraded',
        modes: ['inquiry'],
        active_mode: 'inquiry',
        disclosure: { key: 'd', version: 1, text: 't' },
        conversation: { id: 'c1', run_id: 'r1', cursor: 3 },
        usage: { state: 'available', used: 1, limit: 50, unit: 'asks', resets_at: null },
        proposals: [],
        manual_fallbacks: [],
      }),
    ).toBe(true);
  });
});
