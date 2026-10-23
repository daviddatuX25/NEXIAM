// @vitest-environment jsdom

import { beforeEach, describe, expect, it } from 'vitest';
import {
    activeStaffNavSectionKey,
    isStaffNavActive,
    loadStaffNavSectionState,
    setStaffNavSectionExpanded,
} from '@/Pages/Staff/staff-nav.js';

const sections = [
    {
        key: 'governance',
        collapsible: true,
        default_collapsed: true,
        items: [{ key: 'staff', href: '/staff/personnel', match: 'prefix' }],
    },
    {
        key: 'communications_support',
        collapsible: true,
        default_collapsed: true,
        items: [{ key: 'messages', href: '/staff/inquiries', match: 'prefix' }],
    },
    {
        key: 'more_functions',
        collapsible: true,
        default_collapsed: true,
        items: [{ key: 'reports', href: '/reports', match: 'prefix' }],
    },
];

beforeEach(() => {
    localStorage.clear();
});

describe('staff navigation section state', () => {
    it('uses server defaults for a new login and remembers manual changes across refreshes', () => {
        let state = loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage);

        expect(state.expanded).toEqual({
            governance: false,
            communications_support: false,
            more_functions: false,
        });

        state = setStaffNavSectionExpanded(state, 'communications_support', true, localStorage);
        expect(loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage).expanded.communications_support).toBe(true);

        state = setStaffNavSectionExpanded(state, 'communications_support', false, localStorage);
        const afterRefresh = loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage);
        expect(afterRefresh.expanded.communications_support).toBe(false);
    });

    it('isolates preferences by both staff actor and inactivity generation', () => {
        const original = loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage);
        setStaffNavSectionExpanded(original, 'governance', true, localStorage);

        expect(loadStaffNavSectionState(sections, 72, 'login-generation-a', localStorage).expanded.governance).toBe(false);
        expect(loadStaffNavSectionState(sections, 41, 'login-generation-b', localStorage).expanded.governance).toBe(false);
    });

    it('finds an active collapsible destination for route-entry reveal without changing single-link resolution', () => {
        const activeSection = activeStaffNavSectionKey(sections, sections.flatMap((section) => section.items), '/staff/inquiries/123?tab=messages');
        const stored = loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage);

        expect(activeSection).toBe('communications_support');
        expect(isStaffNavActive(sections[1].items[0], sections.flatMap((section) => section.items), '/staff/inquiries/123?tab=messages')).toBe(true);
        expect(isStaffNavActive(sections[2].items[0], sections.flatMap((section) => section.items), '/staff/inquiries/123?tab=messages')).toBe(false);
        const revealed = setStaffNavSectionExpanded(stored, activeSection!, true, localStorage);
        expect(revealed.expanded.communications_support).toBe(true);

        const manuallyCollapsed = setStaffNavSectionExpanded(revealed, activeSection!, false, localStorage);
        expect(loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage).expanded.communications_support).toBe(false);
        expect(manuallyCollapsed.expanded.communications_support).toBe(false);
    });

    it('falls back to defaults when an identity or stored preference is unavailable', () => {
        expect(loadStaffNavSectionState(sections, 41, null, localStorage).key).toBeNull();
        localStorage.setItem('nexiam.staff.navigation:v1:41:login-generation-a', '{invalid');

        expect(loadStaffNavSectionState(sections, 41, 'login-generation-a', localStorage).expanded.more_functions).toBe(false);
    });
    it('falls back to server defaults when browser storage access throws', () => {
        const unavailableStorage = {
            getItem: () => {
                throw new Error('storage unavailable');
            },
            setItem: () => {
                throw new Error('storage unavailable');
            },
        } as unknown as Storage;

        const state = loadStaffNavSectionState(sections, 41, 'login-generation-a', unavailableStorage);
        expect(state.expanded.communications_support).toBe(false);
        expect(setStaffNavSectionExpanded(state, 'communications_support', true, unavailableStorage).expanded.communications_support).toBe(true);
    });

});
