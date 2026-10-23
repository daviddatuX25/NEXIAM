// @vitest-environment jsdom

import { render, screen } from '@testing-library/svelte';
import { describe, expect, it } from 'vitest';
import PolicyBulletinCard from '../../resources/js/Components/public/PolicyBulletinCard.svelte';

describe('public policy presentation', () => {
    it('shows institutional publication details without exposing revision metadata', () => {
        render(PolicyBulletinCard, {
            props: {
                policy: {
                    title: 'Applicant Privacy Policy',
                    excerpt: 'How the institution handles applicant information.',
                    publisher_label: 'Registrar Office',
                    published_at: '2026-10-01T00:00:00Z',
                    href: '/policies/12',
                    reference: 'Version 7',
                    revision: 7,
                    revision_id: 42,
                    revision_digest: 'private-content-digest',
                },
            },
        });

        expect(screen.getByText('Applicant Privacy Policy')).toBeTruthy();
        expect(screen.getByText('Registrar Office')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Read Applicant Privacy Policy' })).toBeTruthy();
        expect(screen.queryByText(/Version\s*7|Rev\s*7|private-content-digest/i)).toBeNull();
    });
});
