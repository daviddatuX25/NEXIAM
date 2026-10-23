// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PageHeaderBarHarness from './fixtures/PageHeaderBarHarness.svelte';
import InstitutionIndex from '../../resources/js/Pages/Setup/Institution/Index.svelte';

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal();
    return {
        ...actual,
        usePage: () => ({
            url: '/setup/institution',
            props: {
                auth: {
                    user: {
                        id: 1,
                        name: 'Institutional Administrator',
                        email: 'admin@nexiam.test',
                    },
                },
                institution: {
                    id: 1,
                    code: 'SYN-INST-001',
                    name: 'NEXIAM University',
                },
                staff_nav: [],
            },
        }),
        router: {
            put: vi.fn(),
            post: vi.fn(),
            delete: vi.fn(),
        },
    };
});
describe('PageHeaderBar snippet and string resolution', () => {
    afterEach(() => {
        cleanup();
    });

    it('renders standard string title, description, and actions without malformed function strings', () => {
        render(PageHeaderBarHarness, {
            mode: 'string',
            title: 'Simple Dashboard',
            description: 'Overview of campus activity',
        });

        const heading = screen.getByRole('heading', { level: 1 });
        expect(heading.textContent).toBe('Simple Dashboard');
        expect(screen.getByText('Overview of campus activity')).toBeTruthy();
        expect(screen.getByTestId('default-action-btn')).toBeTruthy();

        // Ensure no JS function serialization leaks into the DOM
        expect(document.body.textContent).not.toContain('m=>');
        expect(document.body.textContent).not.toContain('$$slots');
    });

    it('renders snippet title and snippet subtitle cleanly on institutional profile without function code leakage', () => {
        render(PageHeaderBarHarness, {
            mode: 'snippet',
            headingText: 'Institution Profile & Logo',
            code: 'SYN-INST-001',
            description:
                'Official corporate identity, campus location, admissions contact channels, and visual insignia.',
        });

        // Heading is rendered properly
        const heading = screen.getByRole('heading', { level: 1 });
        expect(heading.textContent?.trim()).toBe('Institution Profile & Logo');

        // Badge is rendered with code
        expect(screen.getByText('SYN-INST-001')).toBeTruthy();

        // Subtitle snippet is rendered
        expect(screen.getByTestId('snippet-subtitle').textContent?.trim()).toBe(
            'Official corporate identity, campus location, admissions contact channels, and visual insignia.',
        );

        // Actions snippet is rendered
        expect(screen.getByTestId('save-btn')).toBeTruthy();

        // Critical regression check: NO function toString leakage
        expect(document.body.textContent).not.toContain('m=>');
        expect(document.body.textContent).not.toContain('as()');
        expect(document.body.textContent).not.toContain('$$slots');
        expect(document.body.textContent).not.toContain(
            'font-mono text-xs font-semibold',
        );
    });

    it('supports subtitle string prop as description fallback', () => {
        render(PageHeaderBarHarness, {
            mode: 'subtitle_string',
            title: 'Punch in applicant',
            subtitle:
                'Register an in-person walk-in applicant directly into the active cycle review queue.',
        });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(
            'Punch in applicant',
        );
        expect(
            screen.getByText(
                'Register an in-person walk-in applicant directly into the active cycle review queue.',
            ),
        ).toBeTruthy();
    });

    it('supports actions_content snippet alias', () => {
        render(PageHeaderBarHarness, {
            mode: 'actions_content',
            title: 'Create Test Kit',
        });

        expect(screen.getByTestId('actions-content-btn')).toBeTruthy();
    });

    it('renders Setup/Institution/Index page without compiled snippet code in heading', () => {
        render(InstitutionIndex, {
            institution: {
                id: 1,
                code: 'SYN-INST-001',
                name: 'NEXIAM University',
                short_name: 'NEXIAM',
                campus_name: 'Main Campus',
                address: '123 University Ave',
                location: 'Metro Manila',
                contact_number: '+63 2 8123 4567',
                email: 'admissions@nexiam.edu',
                website: 'https://nexiam.edu',
                logo_url: null,
                fallback_monogram: 'N',
            },
            env_defaults: {},
            can_manage: true,
        });

        const heading = screen.getByRole('heading', { level: 1 });
        expect(heading.textContent?.trim()).toBe('Institution Profile & Logo');
        expect(screen.getByText('SYN-INST-001')).toBeTruthy();
        expect(
            screen.getByText(/Official corporate identity, campus location/),
        ).toBeTruthy();
        expect(screen.getByText('Reset Defaults')).toBeTruthy();
        expect(screen.getByText('Save Changes')).toBeTruthy();

        // The user's exact reported bug string:
        expect(document.body.textContent).not.toContain('m=>');
        expect(document.body.textContent).not.toContain('$$slots');
        expect(document.body.textContent).not.toContain('ye(x');
    });
});
