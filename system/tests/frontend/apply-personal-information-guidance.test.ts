// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

vi.mock('@/Layouts/HomeLayout.svelte', () => ({
    default: (anchor, { children }) => children?.(anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/ApplicantBirthdateInput.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/ApplicantIntakeSuggestionInput.svelte', () => ({
    default: () => null,
}));
vi.mock('@/Components/PhilippineAddressSelector.svelte', () => ({
    default: () => null,
}));

import Apply from '../../resources/js/Pages/Admission/Apply.svelte';

beforeEach(() => {
    window.sessionStorage.clear();
});

afterEach(() => {
    cleanup();
    window.sessionStorage.clear();
});

it('moves Personal Information guidance into its header popover', async () => {
    render(Apply, {
        props: {
            page_state: 'ready',
            institution: { id: 1, name: 'Synthetic Institute' },
            cycle: { id: 1, name: 'Synthetic cycle' },
            window: { id: 1, name: 'Synthetic window' },
            programs: [],
            privacy_policy: null,
            intake_suggestions: { schools: [], shs: [], programs: [] },
        },
    });

    const heading = screen.getByRole('heading', {
        name: 'Personal information',
    });
    const section = heading.closest('section');
    expect(section).not.toBeNull();

    expect(within(section).queryByText('Official PSA records')).toBeNull();
    expect(
        within(section).queryByText(/Enter your legal name, birthdate/),
    ).toBeNull();
    expect(screen.getByLabelText(/First name/)).toBeTruthy();

    await fireEvent.click(
        within(section).getByRole('button', { name: 'More information' }),
    );

    expect(within(section).getByRole('tooltip')).toBeTruthy();
    expect(within(section).getByText('Official PSA records')).toBeTruthy();
    expect(
        within(section).getByText(/Enter your legal name, birthdate/),
    ).toBeTruthy();
});
