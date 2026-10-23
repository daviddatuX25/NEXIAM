// @vitest-environment jsdom

import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SetupIndex from '../../resources/js/Pages/Setup/Index.svelte';

const mockPageProps = {
    auth: {
        user: {
            id: 1,
            name: 'Admin User',
            is_applicant: false,
        },
    },
    staff_navigation: [],
};

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();
    return {
        ...actual,
        usePage: () => ({ props: mockPageProps, url: '/setup' }),
        router: {
            get: vi.fn(),
            post: vi.fn(),
            visit: vi.fn(),
            reload: vi.fn(),
        },
    };
});

describe('Setup Hub Component Dynamic Search Integration', () => {
    const sampleReadiness = {
        overall_percent: 75,
        passed: 3,
        total: 4,
        groups: [],
        stages: [
            {
                stage: 1,
                title: 'Stage 1: Institutional Governance & Curriculum',
                cards: [
                    {
                        key: 'cycles',
                        label: 'Academic Cycles',
                        description:
                            'Establish academic years, terms, and application intake windows.',
                        summary_metric: '3 active cycles',
                        icon: 'CalendarRange',
                        href: '/setup/cycles',
                        desk: 'admin',
                        capability: 'setup.cycles.manage',
                        status: 'ready',
                        in_navigation: true,
                        checks: [
                            {
                                label: 'Active academic cycle defined',
                                detail: '2026-2027 Academic Year',
                                passed: true,
                            },
                        ],
                    },
                ],
            },
            {
                stage: 3,
                title: 'Stage 3: Examination Operations & Logistics',
                cards: [
                    {
                        key: 'rooms',
                        label: 'Campus Rooms',
                        description:
                            'Configure physical rooms, capacities, building locations, GPS coordinates, and entrance photos.',
                        summary_metric: '12 rooms ready',
                        icon: 'DoorOpen',
                        href: '/setup/rooms',
                        desk: 'admin',
                        capability: 'setup.rooms.manage',
                        status: 'ready',
                        in_navigation: true,
                        checks: [
                            {
                                label: 'Room capacity set',
                                detail: 'Minimum 30 seats per room',
                                passed: true,
                            },
                        ],
                    },
                    {
                        key: 'answer_sheets',
                        label: 'Answer Sheets',
                        description:
                            'Upload, calibrate, and print physical answer sheets for optical mark reading.',
                        summary_metric: 'Draft 2 active',
                        icon: 'ScanLine',
                        href: '/setup/exam-materials/answer-sheets',
                        desk: 'testing',
                        capability: 'guidance.scoring.omr',
                        status: 'ready',
                        in_navigation: true,
                        checks: [
                            {
                                label: 'Canonical answer sheet active',
                                detail: 'Draft 2 / 190 guides',
                                passed: true,
                            },
                        ],
                    },
                ],
            },
        ],
        cards: [],
    };

    const sampleCapabilities = {
        setup: true,
        setup_institution: true,
        setup_cycles: true,
        setup_rooms: true,
        setup_programs: true,
        requirements: true,
        schedule: true,
        homepage_manage: true,
        program_public_profiles: true,
        ai_governance: true,
        assessment: true,
        scoring_omr: true,
        booklet: true,
        quota: true,
        knowledge: true,
        consultation_policy: true,
        report_templates: true,
        notifications: true,
        examination_notifications: true,
        results_notifications: true,
        admissions_notifications: true,
        communications: true,
        announcements: true,
        public_policies: true,
    };

    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
    });

    afterEach(() => {
        cleanup();
    });

    it.each(['admin', 'registrar', 'testing', 'guidance'])(
        'keeps the notification hub reachable from the authorized %s desk',
        async (desk) => {
            const notificationCard = {
                key: 'notifications',
                label: 'Email & Notifications',
                description: 'System delivery channels, office notice policies, and recent dispatch activity.',
                href: '/setup/notifications',
                desk: 'admin',
                capability: 'notifications',
                status: 'attention',
                in_navigation: true,
                checks: [],
            };
            render(SetupIndex, {
                readiness: {
                    ...sampleReadiness,
                    stages: [{ stage: 1, title: 'Institutional setup', cards: [notificationCard] }],
                },
                capabilities: sampleCapabilities,
                user_desk: desk,
            });

            expect(screen.getByRole('link', { name: 'Open Email & Notifications' })
                .getAttribute('href')).toBe('/setup/notifications');
        },
    );

    it('renders the search toolbar with placeholder and keyboard shortcut prompt', () => {
        render(SetupIndex, {
            readiness: sampleReadiness,
            capabilities: sampleCapabilities,
            user_desk: 'all',
        });

        const searchInput = screen.getByRole('searchbox', {
            name: /search setup modules/i,
        });
        expect(searchInput).toBeTruthy();
        expect(searchInput.getAttribute('placeholder')).toContain(
            "Press '/' to focus",
        );
    });

    it('dynamically filters cards when typing a search term', async () => {
        render(SetupIndex, {
            readiness: sampleReadiness,
            capabilities: sampleCapabilities,
            user_desk: 'all',
        });

        const searchInput = screen.getByRole('searchbox', {
            name: /search setup modules/i,
        });

        expect(screen.getByText('Campus Rooms')).toBeTruthy();
        expect(screen.getByText('Academic Cycles')).toBeTruthy();
        expect(screen.getByText('Answer Sheets')).toBeTruthy();

        // Search for "rooms"
        await fireEvent.input(searchInput, { target: { value: 'rooms' } });
        await tick();

        expect(screen.getByText('Campus Rooms')).toBeTruthy();
        expect(screen.queryByText('Academic Cycles')).toBeNull();
        expect(screen.queryByText('Answer Sheets')).toBeNull();
    });

    it('supports inner words matching and domain aliases', async () => {
        render(SetupIndex, {
            readiness: sampleReadiness,
            capabilities: sampleCapabilities,
            user_desk: 'all',
        });

        const searchInput = screen.getByRole('searchbox', {
            name: /search setup modules/i,
        });

        // Search for "wayfinding" (alias for rooms)
        await fireEvent.input(searchInput, { target: { value: 'wayfinding' } });
        await tick();

        expect(screen.getByText('Campus Rooms')).toBeTruthy();
        expect(screen.queryByText('Academic Cycles')).toBeNull();

        // Search for "omr" (alias for answer sheets)
        await fireEvent.input(searchInput, { target: { value: 'omr' } });
        await tick();

        expect(screen.getByText('Answer Sheets')).toBeTruthy();
        expect(screen.queryByText('Campus Rooms')).toBeNull();
    });

    it('enforces multi-token AND matching across fields', async () => {
        render(SetupIndex, {
            readiness: sampleReadiness,
            capabilities: sampleCapabilities,
            user_desk: 'all',
        });

        const searchInput = screen.getByRole('searchbox', {
            name: /search setup modules/i,
        });

        // Multi-token AND: "room capacity" matches Campus Rooms
        await fireEvent.input(searchInput, {
            target: { value: 'room capacity' },
        });
        await tick();

        expect(screen.getByText('Campus Rooms')).toBeTruthy();

        // Multi-token conflict: "room omr" matches neither
        await fireEvent.input(searchInput, { target: { value: 'room omr' } });
        await tick();

        expect(screen.queryByText('Campus Rooms')).toBeNull();
        expect(screen.queryByText('Answer Sheets')).toBeNull();
        expect(screen.getByText(/no setup modules match/i)).toBeTruthy();
    });

    it('interacts correctly with desk filters and provides "Show all desks" escape hatch', async () => {
        render(SetupIndex, {
            readiness: sampleReadiness,
            capabilities: sampleCapabilities,
            user_desk: 'testing',
        });

        // User starts on Testing Unit desk
        const searchInput = screen.getByRole('searchbox', {
            name: /search setup modules/i,
        });

        // Type "rooms" which belongs to Admin Desk
        await fireEvent.input(searchInput, { target: { value: 'rooms' } });
        await tick();

        // Should show message that rooms matches in another desk
        expect(screen.getByText(/no modules match/i)).toBeTruthy();
        expect(screen.getByText(/1 more in other desks/i)).toBeTruthy();

        // Click "Show all" button
        const showAllBtn = screen.getByRole('button', { name: /show all/i });
        await fireEvent.click(showAllBtn);
        await tick();

        // Now Campus Rooms appears
        expect(screen.getByText('Campus Rooms')).toBeTruthy();
    });

    it('focuses search input when pressing "/" key', async () => {
        render(SetupIndex, {
            readiness: sampleReadiness,
            capabilities: sampleCapabilities,
            user_desk: 'all',
        });

        const searchInput = screen.getByRole('searchbox', {
            name: /search setup modules/i,
        });
        const focusSpy = vi.spyOn(searchInput, 'focus');

        await fireEvent.keyDown(window, { key: '/' });
        await tick();

        expect(focusSpy).toHaveBeenCalled();
    });
});
