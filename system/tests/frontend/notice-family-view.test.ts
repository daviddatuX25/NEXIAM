// @vitest-environment jsdom
if (typeof Element !== 'undefined' && !Element.prototype.animate) {
    Element.prototype.animate = () => ({ onfinish: null, cancel: () => {}, finish: () => {}, addEventListener: () => {}, removeEventListener: () => {} } as unknown as Animation);
}
import { cleanup, fireEvent, render, within } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it } from 'vitest';
import { page } from '@inertiajs/svelte';
import FamilyStudio from '../../resources/js/Pages/Setup/Notifications/Templates/Studio.svelte';
import NoticeFamilyList from '../../resources/js/Components/Notifications/NoticeFamilyList.svelte';
import ExaminationDesk from '../../resources/js/Pages/Setup/Notifications/Examination.svelte';

function expectSameOriginPath(link: HTMLElement, expectedPath: string): void {
    const href = link.getAttribute('href');
    expect(href).not.toBeNull();

    const url = new URL(href!, window.location.origin);
    expect(url.origin).toBe(window.location.origin);
    expect(url.pathname).toBe(expectedPath);
}

beforeEach(() => {
    Object.assign(page, {
        url: '/setup/notifications/examination/templates/slot-reserved',
        props: {
            auth: { user: { name: 'Notice Reviewer', email: 'reviewer@nexiam.test' } },
            staff_navigation: [{ label: null, items: [{ href: '/staff', label: 'Home', icon: 'LayoutDashboard', match: 'exact' }] }],
            csrf_token: 'test-token',
        },
    });
});
afterEach(() => {
    cleanup();
    Object.assign(page, { url: '/', props: {} });
});

it('shows configured built-in copy and named variants without exposing authoring controls', () => {
    const view = render(FamilyStudio, { props: {
        family: { id: 1, name: 'Exam Slot Reserved', slug: 'slot-reserved', active_version: 1, purpose: 'Confirm a reserved examination slot', message_type: 'Portal inbox notice', supports_pdf: false, authoring_mode: 'builtin' },
        authoring_mode: 'builtin', domain: 'examination', can_edit: true, back_url: '/setup/notifications/examination',
        endpoints: { edit: '/should-not-open' }, draft: { updated_at: '2026-10-01T10:00:00Z', inspect_url: '/setup/notifications/examination/templates/slot-reserved?inspect=draft' },
        versions: [{ id: 10, version: 1, created_at: '2026-09-01T10:00:00Z', inspect_url: '/setup/notifications/examination/templates/slot-reserved?version=1', copy_state_url: '/should-not-copy' }],
        builtin_message: {
            purpose: 'Confirm a reserved examination slot', title: 'Exam Slot Reserved', body: 'You have reserved session {{session_code}} on {{exam_date}}.',
            variants: [{ key: 'scheduled', label: 'Scheduled invitation', title: 'Scheduled', body: 'Scheduled copy.' }, { key: 'immediate', label: 'Immediate invitation', title: 'Immediate', body: 'Immediate copy.' }],
        },
    } });

    const emailPreview = view.getByRole('region', { name: 'Email preview' });
    expect(within(emailPreview).getByRole('heading', { name: 'Subject: Exam Slot Reserved' })).toBeTruthy();
    expect(view.getByText('System Template (Automated)')).toBeTruthy();
    expect(view.getByRole('link', { name: 'Inspect published notice' }).getAttribute('href')).toContain('?version=1');
    for (const name of ['Begin draft', 'Resume draft', 'Begin from version 1', 'Publish', 'Test mail', 'Upload']) {
        expect(view.queryByRole('button', { name: new RegExp(name, 'i') })).toBeNull();
    }
});

it('keeps metadata-only built-in readers to purpose and type, without archive copy or inspection links', () => {
    const view = render(FamilyStudio, { props: {
        versions: [{ id: 20, version: 1 }],
        authoring_mode: 'builtin', domain: 'results', can_edit: false, back_url: '/setup/notifications/results',
        family: { id: 2, name: 'Consultation Reserved', slug: 'consultation-reserved', active_version: 1, purpose: 'PRIVATE_PURPOSE', type: 'builtin', authoring_mode: 'builtin' },
        builtin_message: null,
    } });

    expect(view.getByRole('heading', { name: 'Purpose' })).toBeTruthy();
    expect(view.getByRole('heading', { name: 'Type' })).toBeTruthy();
    expect(view.queryByText('PRIVATE_COPY')).toBeNull();
    expect(view.queryByRole('link', { name: 'Inspect version 1' })).toBeNull();
    expect(view.queryByRole('button', { name: /draft|publish|test mail/i })).toBeNull();
});

it('renders retained built-in history as inert text and keeps email authoring independent of channel suppression', () => {
    const archive = render(FamilyStudio, { props: {
        family: { id: 3, name: 'Session Completed', slug: 'session-completed', active_version: 1, authoring_mode: 'builtin', supports_pdf: false },
        authoring_mode: 'builtin', domain: 'examination', can_edit: true, back_url: '/setup/notifications/examination',
        read_only: true, builtin_inspection: { kind: 'version', version: 1, title: 'Historical title', body: '<img src=x onerror=alert(1)> archived copy' },
        endpoints: {}, versions: [],
    } });
    const archiveSection = archive.getByRole('region', { name: 'Protected historical inspection' });
    expect(archiveSection.textContent).toContain('Historical title');
    expect(archiveSection.textContent).toContain('<img src=x onerror=alert(1)> archived copy');
    expect(archiveSection.querySelector('img')).toBeNull();
    expect(archive.queryByRole('button', { name: /draft|publish|test mail/i })).toBeNull();
    archive.unmount();

    const emailFamily = render(FamilyStudio, { props: {
        family: { id: 4, name: 'Exam Reminder', slug: 'reminder', active_version: 2, supports_pdf: false, authoring_mode: 'email' },
        authoring_mode: 'email', domain: 'examination', can_edit: true, back_url: '/setup/notifications/examination',
        endpoints: { edit: '/setup/notifications/examination/templates/reminder/edit' },
        draft: { stage: 2, updated_at: '2026-10-01T10:00:00Z' }, versions: [],
    } });
    expect(emailFamily.getByRole('button', { name: 'Resume draft' })).toBeTruthy();
});

it('opens actionable families directly, keeps built-ins read-only, and filters by authoritative class', async () => {
    const view = render(NoticeFamilyList, { props: {
        domain: 'examination', canEdit: true,
        notices: [
            { id: 1, name: 'Reserved slot', description: 'Built in confirmation', slug: 'slot-reserved', authoring_mode: 'builtin', channels: ['in_app'], active_version: 1, has_draft: false, supports_pdf: false },
            { id: 2, name: 'Schedule published', description: 'Optional official permit', slug: 'session-published', authoring_mode: 'email_pdf', channels: ['in_app'], active_version: 2, has_draft: true, supports_pdf: true },
            { id: 3, name: 'Reminder', description: 'No attached document', slug: 'reminder', authoring_mode: 'email', channels: ['in_app'], active_version: 1, has_draft: false, supports_pdf: false },
        ],
    } });

    expect(view.getAllByRole('link', { name: 'Edit Message →' })).toHaveLength(2);
    expect(view.getAllByRole('link', { name: 'Edit Message →' }).map((link) => new URL(link.getAttribute('href')!, window.location.origin).pathname)).toEqual([
        '/setup/notifications/examination/templates/session-published/edit',
        '/setup/notifications/examination/templates/reminder/edit',
    ]);

    expect(view.container.querySelectorAll('tbody tr')).toHaveLength(3);
    for (const label of ['All (3)', 'Active (3)', 'Draft (1)', 'System (1)']) {
        expect(view.getByRole('button', { name: label })).toBeTruthy();
    }
    await fireEvent.click(view.getByRole('button', { name: 'System (1)' }));
    expect(view.container.querySelectorAll('tbody tr')).toHaveLength(1);
    expect(view.getByRole('link', { name: 'Reserved slot' })).toBeTruthy();
    expect(view.getByRole('status').textContent).toContain('1 matching notice templates');

    await fireEvent.click(view.getByRole('button', { name: 'All (3)' }));
    await fireEvent.input(view.getByRole('searchbox', { name: 'Search notice templates' }), { target: { value: 'reminder' } });
    expectSameOriginPath(view.getByRole('link', { name: 'Reminder' }), '/setup/notifications/examination/templates/reminder/edit');
    expect(view.queryByRole('link', { name: 'Schedule published' })).toBeNull();
});


it('does not offer authoring or private document routes to metadata-only staff', () => {
    const view = render(NoticeFamilyList, { props: {
        domain: 'results', canEdit: false,
        notices: [
            { id: 1, name: 'Online release', slug: 'released-online', authoring_mode: 'email_pdf', channels: ['in_app'], active_version: 1, has_draft: false, supports_pdf: true },
            { id: 2, name: 'Consultation reserved', slug: 'consultation-reserved', authoring_mode: 'builtin', channels: ['in_app'], active_version: 1, has_draft: false, supports_pdf: false },
        ],
    } });

    expectSameOriginPath(view.getByRole('link', { name: 'Online release' }), '/setup/notifications/results/templates/released-online');
    expectSameOriginPath(view.getByRole('link', { name: 'Consultation reserved' }), '/setup/notifications/results/templates/consultation-reserved');
    expect(view.getAllByRole('link', { name: 'View Template →' })).toHaveLength(2);
    expect(view.queryByRole('link', { name: /edit message|continue message|document/i })).toBeNull();
});

it('shows the authorable document-correction notice in its no-PDF class and search results', async () => {
    const view = render(NoticeFamilyList, { props: {
        domain: 'admissions',
        canEdit: true,
        notices: [
            {
                id: 1,
                name: 'Application Correction Notice',
                description: 'Sent to applicants when an evaluator requests updates or document re-uploads.',
                slug: 'correction-requested',
                authoring_mode: 'email',
                channels: ['in_app', 'email'],
                active_version: 2,
                has_draft: false,
                supports_pdf: false,
            },
            {
                id: 2,
                name: 'Document Correction Request',
                description: 'Sent when Registrar explicitly requests action on inspected intake documents.',
                slug: 'document-correction-requested',
                authoring_mode: 'email',
                channels: ['in_app', 'email'],
                active_version: 3,
                has_draft: false,
                supports_pdf: false,
            },
            {
                id: 3,
                name: 'Application dismissed',
                description: 'Application review is complete.',
                slug: 'dismissed',
                authoring_mode: 'email',
                channels: ['in_app', 'email'],
                active_version: 1,
                has_draft: true,
                supports_pdf: false,
            },
            {
                id: 4,
                name: 'Application accepted',
                description: 'Application accepted with an optional notice.',
                slug: 'accepted',
                authoring_mode: 'email_pdf',
                channels: ['in_app', 'email'],
                active_version: 1,
                has_draft: false,
                supports_pdf: true,
            },
        ],
    } });

    expectSameOriginPath(view.getByRole('link', { name: 'Application Correction Notice' }), '/setup/notifications/admissions/templates/correction-requested/edit');
    expectSameOriginPath(view.getByRole('link', { name: 'Document Correction Request' }), '/setup/notifications/admissions/templates/document-correction-requested/edit');
    expect(view.getAllByText('● Active').length).toBeGreaterThan(0);
    expect(view.getByRole('status').textContent).toContain('4 matching notice templates');

    const search = view.getByRole('searchbox', { name: 'Search notice templates' });
    await fireEvent.input(search, { target: { value: 'correction' } });
    expect(view.getByRole('link', { name: 'Application Correction Notice' })).toBeTruthy();
    expect(view.getByRole('link', { name: 'Document Correction Request' })).toBeTruthy();
    expect(view.getByRole('status').textContent).toContain('2 matching notice templates');

    await fireEvent.input(search, { target: { value: '' } });
    expect(view.getByRole('link', { name: 'Application Correction Notice' })).toBeTruthy();
    expect(view.getByRole('link', { name: 'Document Correction Request' })).toBeTruthy();
    expect(view.getByRole('link', { name: 'Application dismissed' })).toBeTruthy();
    expect(view.getByRole('link', { name: 'Application accepted' })).toBeTruthy();
    expect(view.getByRole('status').textContent).toContain('4 matching notice templates');
});

it('keeps notice navigation, academic-year controls, and session overrides as separate consumers', async () => {
    const desk = render(ExaminationDesk, { props: {
        active_cycle: { id: 2, label: 'Academic Year 2026' },
        policy: { version: 7, publication_email_enabled: true, reminder_email_enabled: true, reminder_offsets_minutes: [4320, 1440, 120] },
        custom_sessions: [{ id: 91, code: 'EXAM-91', status: 'draft', starts_at: 'Oct 12, 2026 09:00 AM', room_name: 'Hall A', reminder_offsets_minutes: [120] }],
        notices: [{ id: 10, name: 'Reminder', slug: 'reminder', authoring_mode: 'email', channels: ['in_app'], active_version: 1, has_draft: false, supports_pdf: false }],
        capabilities: { can_manage: true },
    } });

    expectSameOriginPath(desk.getByRole('link', { name: 'Reminder' }), '/setup/notifications/examination/templates/reminder/edit');
    await fireEvent.click(desk.getByRole('tab', { name: 'Rules & Overrides' }));
    const rulesPanel = desk.getByRole('tabpanel', { name: 'Rules & Overrides' });
    const publicationEmail = within(rulesPanel).getByRole('switch', { name: 'Email examination schedule publication' });
    const countdownEmail = within(rulesPanel).getByRole('switch', { name: 'Send countdown reminder emails' });
    expect(publicationEmail.getAttribute('aria-checked')).toBe('true');
    expect(countdownEmail.getAttribute('aria-checked')).toBe('true');
    await fireEvent.click(publicationEmail);
    expect(publicationEmail.getAttribute('aria-checked')).toBe('false');

    const oneHour = within(rulesPanel).getByRole('button', { name: '1h' });
    expect(oneHour.getAttribute('aria-pressed')).toBe('false');
    await fireEvent.click(oneHour);
    expect(oneHour.getAttribute('aria-pressed')).toBe('true');
    expect((within(rulesPanel).getByRole('button', { name: 'Save Examination Rules' }) as HTMLButtonElement).disabled).toBe(false);
    expectSameOriginPath(within(rulesPanel).getByRole('link', { name: 'Edit session' }), '/exams/sessions/91/edit');

    desk.unmount();

    const denied = render(ExaminationDesk, { props: {
        active_cycle: { id: 2, label: 'Academic Year 2026' },
        policy: { version: 7, publication_email_enabled: true, reminder_email_enabled: true, reminder_offsets_minutes: [4320, 1440, 120] },
        custom_sessions: [],
        notices: [],
        capabilities: { can_manage: false },
    } });
    await fireEvent.click(denied.getByRole('tab', { name: 'Rules & Overrides' }));
    const deniedPanel = denied.getByRole('tabpanel', { name: 'Rules & Overrides' });
    expect((within(deniedPanel).getByRole('switch', { name: 'Email examination schedule publication' })).matches(':disabled')).toBe(true);
    expect((within(deniedPanel).getByRole('button', { name: '72h' })).matches(':disabled')).toBe(true);
    expect((within(deniedPanel).getByRole('button', { name: 'Save Examination Rules' }) as HTMLButtonElement).disabled).toBe(true);

    denied.unmount();
    const noCycle = render(ExaminationDesk, { props: {
        active_cycle: null,
        policy: { version: 7, publication_email_enabled: true, reminder_email_enabled: true, reminder_offsets_minutes: [4320, 1440, 120] },
        custom_sessions: [],
        notices: [],
        capabilities: { can_manage: true },
    } });
    await fireEvent.click(noCycle.getByRole('tab', { name: 'Rules & Overrides' }));
    const noCyclePanel = noCycle.getByRole('tabpanel', { name: 'Rules & Overrides' });
    expect((within(noCyclePanel).getByRole('switch', { name: 'Email examination schedule publication' })).matches(':disabled')).toBe(true);
    expect((within(noCyclePanel).getByRole('button', { name: 'Save Examination Rules' }) as HTMLButtonElement).disabled).toBe(true);
});
