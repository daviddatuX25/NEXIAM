// @vitest-environment jsdom
import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { page, router } from '@inertiajs/svelte';
import ResultSheetStudio from '../../resources/js/Components/ResultSheet/ResultSheetStudio.svelte';
import ResultSheetTemplateEditor from '../../resources/js/Components/ResultSheet/ResultSheetTemplateEditor.svelte';
import NoticeFamilyList from '../../resources/js/Components/Notifications/NoticeFamilyList.svelte';

const print = {
    schema_version: '1.0.0',
    layout_paradigm: 'flow',
    page: { paper_size: 'a4', orientation: 'portrait', width_mm: 210, height_mm: 297, margin_mm: 12 },
    components: [{ id: 'print-text', type: 'text', x: 12, y: 12, w: 100, h: 15, locked: false, props: { text: 'Physical report content' } }],
};
const originalShowModal = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'showModal');
const originalClose = Object.getOwnPropertyDescriptor(HTMLDialogElement.prototype, 'close');

beforeEach(() => {
    Object.assign(page, { url: '/setup/notifications/examination', props: { auth: { user: { name: 'Notice Reviewer', email: 'reviewer@nexiam.test' } }, staff_navigation: [{ label: null, items: [{ href: '/staff', label: 'Home', icon: 'LayoutDashboard', match: 'exact' }] }], csrf_token: 'test-token' } });
    vi.stubGlobal('ResizeObserver', class { observe() {} unobserve() {} disconnect() {} });
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', { configurable: true, value: function () { this.setAttribute('open', ''); } });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', { configurable: true, value: function () { this.removeAttribute('open'); } });
});

afterEach(() => {
    cleanup(); vi.unstubAllGlobals(); vi.restoreAllMocks();
    Object.assign(page, { url: '/', props: {} });
    if (originalShowModal) Object.defineProperty(HTMLDialogElement.prototype, 'showModal', originalShowModal);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, 'showModal');
    if (originalClose) Object.defineProperty(HTMLDialogElement.prototype, 'close', originalClose);
    else Reflect.deleteProperty(HTMLDialogElement.prototype, 'close');
});

it('keeps the ordinary report editor on its template and page-settings contract', () => {
    const view = render(ResultSheetTemplateEditor, { props: {
        mode: 'edit', basePath: '/setup/report-templates/passing_report',
        surface: { title: 'Passing Report', layout: 'flow' },
        template: { id: 3, name: 'Report name', mode: 'html', builder_json: print },
        builderDefaults: print,
    } });
    expect(view.container.querySelector('[data-result-sheet-document-bar]')).toBeTruthy();
    expect(view.getByLabelText('Template Name')).toBeTruthy();
    expect(view.getByLabelText('Paper Size')).toBeTruthy();
    expect(view.queryByRole('button', { name: 'Publish' })).toBeNull();
    expect(view.queryByLabelText('Portal caption')).toBeNull();
});

it('keeps the normal print studio in physical page coordinates', () => {
    const view = render(ResultSheetStudio, { props: {
        builderDocument: print,
        builderCatalog: { text: { label: 'Text', category: 'Content' } },
        builderCapabilities: { text: { editable: true } },
    } });
    expect(view.container.querySelector('[data-result-sheet-page]')).not.toBeNull();
    expect(print.page.width_mm).toBe(210);
    expect(print.components[0].x).toBe(12);
});

it('direct family list uses persisted metadata and distinguishes filtered empty without fake previews', async () => {
    const view = render(NoticeFamilyList, { props: {
        domain: 'examination', canEdit: true,
        notices: [{ id: 1, name: 'Countdown reminder', slug: 'reminder', description: 'Upcoming exam reminders', active_version: 4, has_draft: true, channels: ['in_app', 'mail'], supports_pdf: false }],
    } });
    const link = view.getByRole('link', { name: 'Countdown reminder' });
    const destination = new URL(link.getAttribute('href') ?? '', window.location.href);
    expect(destination.origin).toBe(window.location.origin);
    expect(destination.pathname).toBe('/setup/notifications/examination/templates/reminder');
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => {});
    await fireEvent.click(link);
    expect(visit).toHaveBeenCalled();
    const visited = new URL(String(visit.mock.calls[0][0]), window.location.href);
    expect(visited.origin).toBe(destination.origin);
    expect(visited.pathname).toBe(destination.pathname);
    expect(view.getByText('Published version 4')).toBeTruthy();
    expect(view.getByText('Draft available')).toBeTruthy();
    expect(view.container.querySelector('iframe')).toBeNull();
    await fireEvent.input(view.getByRole('searchbox', { name: 'Find a notice' }), { target: { value: 'Absent family' } });
    expect(view.getByText(/No families match this search/)).toBeTruthy();
});
