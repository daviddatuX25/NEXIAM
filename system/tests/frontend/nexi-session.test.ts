import { describe, expect, it } from 'vitest';
import {
  NEXI_CONTRACT_VERSION,
  isNexiSessionView,
  nexiShouldMount,
  resolveNexiSession,
  type NexiSessionView,
} from '../../resources/js/types/nexi';

function sample(availability: NexiSessionView['availability']): NexiSessionView {
  return {
    contract_version: NEXI_CONTRACT_VERSION,
    surface: { key: 'staff', version: 1 },
    context_epoch: 'epoch-1',
    page_pack: { key: 'staff.workspace.home@1', version: 1 },
    publication_version: null,
    availability,
    modes: ['inquiry'],
    active_mode: 'inquiry',
    disclosure: {
      key: 'nexi.foundation.disclosure',
      version: 1,
      text: 'Nexi is an assistant.',
    },
    conversation: { id: null, run_id: null, cursor: 0 },
    usage: {
      state: 'available',
      used: null,
      limit: 50,
      unit: 'asks',
      resets_at: null,
    },
    proposals: [],
    manual_fallbacks: [],
  };
}

describe('NexiSessionView mount matrix', () => {
  it('recognizes contract version 1 sessions', () => {
    expect(isNexiSessionView(sample('degraded'))).toBe(true);
    expect(isNexiSessionView({ enabled: true })).toBe(false);
  });

  it('resolves nested transitional share.session', () => {
    const session = sample('inquiry_only');
    expect(resolveNexiSession({ enabled: true, session })).toEqual(session);
  });

  it('mounts degraded and inquiry_only and hides hidden', () => {
    expect(nexiShouldMount(sample('degraded'))).toBe(true);
    expect(nexiShouldMount(sample('inquiry_only'))).toBe(true);
    expect(nexiShouldMount(sample('quota_exhausted'))).toBe(true);
    expect(nexiShouldMount(sample('suspended'))).toBe(true);
    expect(nexiShouldMount(sample('hidden'))).toBe(false);
    expect(nexiShouldMount(null)).toBe(false);
  });
});
