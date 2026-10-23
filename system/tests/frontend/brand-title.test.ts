// @vitest-environment jsdom

import { cleanup, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import BrandTitle from '../../resources/js/Components/kit/BrandTitle.svelte';
import ApplicantHome from '../../resources/js/Pages/Applicant/ApplicantHome.svelte';

const mockPage = vi.hoisted(() => ({
    props: {
        institution: {
            name: 'Ilocos Sur Polytechnic State College',
            short_name: 'ISPSC',
            campus: 'Tagudin Campus',
        },
    },
    url: '/applicant/home',
}));

vi.mock('@inertiajs/svelte', () => ({
    usePage: () => mockPage,
    Link: () => null,
}));

afterEach(() => {
    cleanup();
});
describe('BrandTitle powered by NEXIAM logo branding', () => {
    it('renders the institutional title alongside the canonical NexiamLogo emblem', () => {
        const institution = {
            name: 'Ilocos Sur Polytechnic State College',
            short_name: 'ISPSC',
            campus: 'Tagudin Campus',
        };

        const { container } = render(BrandTitle, {
            props: { institution },
        });

        // Verifies the portal title text
        expect(container.textContent).toContain('ISPSC Tagudin Admission Portal');

        // Verifies the powered by label
        expect(container.textContent).toContain('powered by');
        expect(container.textContent).toContain('NEXIAM');

        // Verifies the canonical NexiamLogo img is present rather than a generic text box
        const nexiamLogoImg = container.querySelector('img[alt="NEXIAM"]');
        expect(nexiamLogoImg).not.toBeNull();
        expect(nexiamLogoImg?.getAttribute('src')).toBe('/assets/branding/nexiam/nexiam-logo-only.png');
    });

    it('renders the powered by NEXIAM logo footer in ApplicantHome', () => {
        const { container } = render(ApplicantHome, {
            props: {
                hideFooter: false,
                showTour: false,
            },
        });

        expect(container.textContent).toContain('Admissions & testing powered by');
        expect(container.textContent).toContain('NEXIAM');
        const nexiamLogoImg = container.querySelector('footer img[alt="NEXIAM"]');
        expect(nexiamLogoImg).not.toBeNull();
        expect(nexiamLogoImg?.getAttribute('src')).toBe('/assets/branding/nexiam/nexiam-logo-only.png');
    });
});
