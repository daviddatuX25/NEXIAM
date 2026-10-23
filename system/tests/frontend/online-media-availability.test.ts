// @vitest-environment jsdom

import { cleanup, render } from '@testing-library/svelte';
import { afterEach, expect, it } from 'vitest';
import ApplicantPortal from '../../resources/js/Pages/Applicant/Portal.svelte';

afterEach(cleanup);

it('does not offer applicant join credentials while media health is unverified', () => {
    const view = render(ApplicantPortal, {
        page_state: 'ready',
        identity: { name: 'A. Applicant' },
        stages: [],
        notices: [],
        quick_links: [],
        active_online_encounter: {
            id: 7,
            join_available: false,
            url: null,
            unavailable_reason: 'Online consultation is unavailable: live health was not verified.',
        },
    });
    expect(view.queryByRole('link', { name: 'Join Consultation Call' })).toBeNull();
});
