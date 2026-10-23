// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

const router = vi.hoisted(() => ({ get: vi.fn(), reload: vi.fn(), post: vi.fn(), visit: vi.fn() }));
vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...await importOriginal<Record<string, unknown>>(),
    usePage: () => ({ props: {
        auth: { user: { office: 'admin', capabilities: [] } },
        navigation: [],
        navigation_badges: { items: {} },
        institution: {},
    } }),
    router,
}));

import PersonnelIndex from '../../resources/js/Pages/Staff/Personnel/Index.svelte';

afterEach(() => cleanup());

it('mounts the actual personnel directory, switches view modes, and opens then cancels its modals', async () => {
    render(PersonnelIndex, { props: {
        users: { data: [{ id: 1, name: 'Active Staff', office: 'registrar', status: 'active', roles: [], program_codes: [], can_manage_user: true, is_self: false }], total: 1 },
        roles: [
            { name: 'program_head', display_name: 'Program Head' },
            { name: 'registrar_clerk', display_name: 'Registrar Clerk' },
            { name: 'registrar_support_staff', display_name: 'Registrar Support' },
            { name: 'guidance_clerk', display_name: 'Guidance Clerk' },
            { name: 'guidance_support_staff', display_name: 'Guidance Support' },
            { name: 'institutional_admin', display_name: 'Admin Office Staff' },
        ],
        offices: [{ value: 'admin', label: 'Admin Office' }],
        departments: [],
        programs: [{ value: 'BSIT', label: 'Information Technology', department_code: 'CCS' }],
        filters: {}, errors: {}, setup_scope_options: {}, staff_readiness: {},
        can_full_hr: true, can_manage_personnel: true, cycles: [{ code: 'AY27', name: '2027-2028', is_active: true }],
        actor_office: 'admin',
    } });

    expect(screen.getAllByText('Active Staff').length).toBeGreaterThan(0);
    await fireEvent.click(screen.getAllByRole('button', { name: 'Card view' })[0]);
    expect(screen.getAllByRole('button', { name: 'Card view' }).some((button) => button.getAttribute('data-state') === 'on')).toBe(true);
    await fireEvent.click(screen.getAllByRole('button', { name: 'Table view' })[0]);
    expect(screen.getAllByRole('button', { name: 'Table view' }).some((button) => button.getAttribute('data-state') === 'on')).toBe(true);
    await fireEvent.click(screen.getByRole('button', { name: 'Toggle filters' }));
    expect(screen.getByText('Office')).toBeTruthy();
    await fireEvent.click(screen.getByRole('button', { name: 'Onboard Staff' }));
    expect(screen.getByRole('heading', { name: 'Fast Onboard Personnel' })).toBeTruthy();
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Cycle Rollover' }));
    expect(screen.getByRole('heading', { name: 'Academic Cycle Personnel Rollover' })).toBeTruthy();
    await fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
});
