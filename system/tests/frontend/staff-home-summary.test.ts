// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    usePage: () => ({ props: { flash: {}, errors: {} } }),
}));
vi.mock('@/Pages/Staff/StaffLayout.svelte', () => ({
    default: (anchor: unknown, { children }: { children?: (anchor: unknown) => void }) => children?.(anchor),
}));
vi.mock('@/Components/kit/Walkthrough/TourTriggerGlyph.svelte', () => ({ default: () => null }));
vi.mock('@/Components/kit/Walkthrough/XiamNpcDialog.svelte', () => ({ default: () => null }));

import Home from '../../resources/js/Pages/Staff/Home.svelte';

afterEach(cleanup);

const dashboard = {
    academic_scope: null,
    cycle_roadmap: {
        cycle_label: 'Synthetic cycle',
        progress_percent: 40,
        days_elapsed: 4,
        days_total: 10,
        days_remaining: 6,
        milestones: [],
        velocity: { total_applications: 20, today_count: 1 },
    },
};

it('uses authorized aggregate placements without requiring academic program rows', () => {
    render(Home, {
        staff: {},
        dashboard: { ...dashboard, course_placement: { placed: 7, quota: 100, percent: 7 } },
    });

    expect(screen.getByText('Reserved program places').parentElement?.querySelector('dd')?.textContent).toBe('7');
});

it('distinguishes unavailable placements from an authorized zero', async () => {
    const view = render(Home, {
        staff: {},
        dashboard: { ...dashboard, course_placement: null },
    });

    expect(screen.getByText('Reserved program places').parentElement?.querySelector('dd')?.textContent).toBe('—');
    await view.rerender({
        staff: {},
        dashboard: { ...dashboard, course_placement: { placed: 0, quota: 100, percent: 0 } },
    });
    expect(screen.getByText('Reserved program places').parentElement?.querySelector('dd')?.textContent).toBe('0');
});

it('renders a clean header greeting without waving emoji or duplicate office badge', () => {
    render(Home, {
        staff: { name: 'Elena Ramos', scope: { office: 'Guidance Office' } },
        dashboard: { ...dashboard, actor: { name: 'Elena Ramos', office: 'Guidance Office' } },
    });

    const heading = screen.getByRole('heading', { level: 1 });
    expect(heading.textContent).toContain('Welcome back, Elena Ramos');
    expect(heading.textContent).not.toContain('👋');
    expect(screen.queryByText('Admission Lifecycle Pipeline')).toBeNull();
    expect(screen.queryByText('7 Operational Steps')).toBeNull();
});

it('renders role-tailored guidance cockpit with score finalization and without OMR highlight or intake label', () => {
    render(Home, {
        staff: { name: 'Paolo Garcia', scope: { office: 'Guidance Office' } },
        dashboard: {
            ...dashboard,
            actor: { name: 'Paolo Garcia', office: 'Guidance Office' },
            guidance: {
                kpis: {
                    today_release_appointments: 18,
                    pending_scoring: 1,
                    pending_referrals: 5,
                    open_inquiries: 4,
                    pending_advising_qualifiers: 35,
                    candidates_tested: 185,
                    completed_sessions: 10,
                    scheduled_sessions: 12,
                    attendance_percentage: 92.5,
                },
            },
            academic_scope: {
                departments: [{ code: 'CAS', name: 'College of Arts and Sciences' }],
                programs: [
                    {
                        code: 'BSIT',
                        name: 'Bachelor of Science in Information Technology',
                        department_code: 'CAS',
                        planned_quota: 90,
                        course_placed: 72,
                        open_slots: 18,
                        first_choice_demand: 110,
                        utilization_percent: 80,
                        demand_percent: 122.2,
                    },
                ],
            },
            course_placement: { placed: 72, quota: 90, percent: 80 },
        },
    });

    expect(screen.getByText('Release Day Advising')).toBeDefined();
    expect(screen.getByText('Score Finalization')).toBeDefined();
    expect(screen.getByText('Academic Referrals')).toBeDefined();
    expect(screen.getByText('Applicant Inquiries')).toBeDefined();
    expect(screen.getByText('Campus Program Quotas & Advising Radar')).toBeDefined();
    expect(screen.getByText('BSIT')).toBeDefined();
    expect(screen.getByText('Assessment Progression')).toBeDefined();
    // Ensure "OMR" is NOT used as primary title and "intake" is not used in guidance cards
    expect(screen.queryByText('SCORING & OMR INTAKE')).toBeNull();
    expect(screen.queryByText('Intake Funnel')).toBeNull();
});

it('renders role-tailored registrar desk with admissions verification and confirmed clearances', () => {
    render(Home, {
        staff: { name: 'Lina Cruz', scope: { office: 'Registrar Office' } },
        dashboard: {
            ...dashboard,
            actor: { name: 'Lina Cruz', office: 'Registrar Office' },
            registrar: {
                kpis: {
                    total: 240,
                    in_pipeline: 46,
                    completed: 180,
                    dismissed: 14,
                    admission_clearances_confirmed: 180,
                    yoy_growth: '+30 YoY',
                },
            },
            academic_scope: {
                departments: [],
                programs: [],
            },
        },
    });

    expect(screen.getByText('Pending Review')).toBeDefined();
    expect(screen.getByText('Confirmed Clearances')).toBeDefined();
    expect(screen.getByText('Program Quota & Admissions Demand')).toBeDefined();
    expect(screen.getByText('Admissions Intake Pulse')).toBeDefined();
    // Guidance-only tasks are not present
    expect(screen.queryByText('Score Finalization')).toBeNull();
    expect(screen.queryByText('Release Day Advising')).toBeNull();
});
