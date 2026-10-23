// @vitest-environment jsdom

import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@/Components/qr/QrScanner.svelte', () => ({ default: () => null }));

import ApplicantLookupDialog from '../../resources/js/Components/consultation/ApplicantLookupDialog.svelte';

afterEach(cleanup);

it('focuses manual search when staff switch to applicant lookup', async () => {
    render(ApplicantLookupDialog, {
        props: { open: true, onOpenChange: vi.fn() },
    });

    await fireEvent.click(screen.getByRole('tab', { name: 'Manual Search' }));

    const searchInput = screen.getByRole('textbox', {
        name: 'Search accepted examinees',
    });
    await waitFor(() => expect(document.activeElement).toBe(searchInput));
});
