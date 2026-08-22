import { describe, expect, it } from 'vitest';

describe('public home contract', () => {
    it('keeps the public landing institution-first and free of planning badges', () => {
        const homeCopy = {
            institution: 'Ilocos Sur Polytechnic State College',
            campus: 'Tagudin Campus',
            primaryCta: 'Explore programs',
            applicantCta: 'Applicant Portal',
            staffCta: 'Staff sign in',
        };

        expect(homeCopy.institution).toContain('Polytechnic');
        expect(homeCopy.campus).toBe('Tagudin Campus');
        expect(homeCopy.primaryCta).toContain('Explore');
        expect(homeCopy.applicantCta).toContain('Applicant');
        expect(JSON.stringify(homeCopy)).not.toContain('E2-CONNECTED');
        expect(JSON.stringify(homeCopy)).not.toContain('E1b');
    });
});
