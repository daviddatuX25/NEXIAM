// @vitest-environment jsdom

import { cleanup, render, waitFor } from '@testing-library/svelte';
import { page } from '@inertiajs/svelte';
import { tick } from 'svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import Create from '../../resources/js/Pages/Setup/ReportTemplates/Create.svelte';
import Edit from '../../resources/js/Pages/Setup/ReportTemplates/Edit.svelte';

const surface = { title: 'Passing Report', surface_key: 'passing_report' };
const staffPage = {
    url: '/setup/report-templates/passing_report',
    props: {
        auth: {
            user: { name: 'Template Reviewer', email: 'reviewer@nexiam.test' },
        },
        staff_navigation: [
            {
                label: null,
                items: [
                    {
                        href: '/staff',
                        label: 'Home',
                        icon: 'LayoutDashboard',
                        match: 'exact',
                    },
                ],
            },
        ],
        csrf_token: 'test-token',
    },
};

beforeEach(() => {
    Object.assign(page, staffPage);
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        },
    );
    vi.stubGlobal(
        'fetch',
        vi.fn(async () => ({ ok: true, json: async () => ({}) })),
    );
});

afterEach(() => {
    cleanup();
    Object.assign(page, { url: '/', props: {} });
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

async function expectBreadcrumbDocumentTitle(view: { container: HTMLElement }) {
    await tick();
    await waitFor(() => {
        const currentBreadcrumb = view.container.querySelector(
            '[aria-label="Breadcrumb"] [aria-current="page"]',
        );
        expect(currentBreadcrumb).not.toBeNull();
        expect(document.title).toBe(
            `${currentBreadcrumb?.textContent} - NEXIAM`,
        );
    });
}

describe('report template page titles', () => {
    it('mounts Create with the passing-report studio title and layout-owned browser title', async () => {
        const view = render(Create, {
            props: {
                surface,
                placeholderGroups: {},
                domainPlaceholders: [],
                builderCatalog: {},
                builderCapabilities: {},
                builderDefaults: {},
                sampleContext: {},
            },
        });

        expect(view.getByText('Passing Report Studio')).toBeTruthy();
        expect(view.queryByText('Result Sheet Studio')).toBeNull();
        await expectBreadcrumbDocumentTitle(view);
        expect(view.getByRole('link', { name: 'Passing Report' })).toBeTruthy();
    });

    it('mounts Edit with the passing-report studio title and preserves the source title in the breadcrumb', async () => {
        const view = render(Edit, {
            props: {
                surface,
                template: {
                    id: 17,
                    name: 'Existing Passing Report',
                    mode: 'html',
                    source_kind: 'builder',
                },
                placeholderGroups: {},
                domainPlaceholders: [],
                builderCatalog: {},
                builderCapabilities: {},
                builderDefaults: {},
                sampleContext: {},
            },
        });

        expect(view.getByText('Passing Report Studio')).toBeTruthy();
        expect(view.queryByText('Result Sheet Studio')).toBeNull();
        await expectBreadcrumbDocumentTitle(view);
        expect(
            view.getByRole('heading', {
                level: 1,
                name: 'Existing Passing Report',
            }),
        ).toBeTruthy();
    });
});
