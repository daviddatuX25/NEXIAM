import { describe, expect, it } from 'vitest';

describe('public home contract', () => {
    it('keeps product landing free of planning phase badges', () => {
        const homeCopy = {
            title: 'NEXIAM',
            primaryCta: 'Apply for admission',
            staffCta: 'Staff sign in',
        };

        expect(homeCopy.title).toBe('NEXIAM');
        expect(homeCopy.primaryCta).toContain('Apply');
        expect(JSON.stringify(homeCopy)).not.toContain('E2-CONNECTED');
        expect(JSON.stringify(homeCopy)).not.toContain('E1b');
    });
});
