// @vitest-environment jsdom

import { cleanup, render, within } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import TestKitIndex from '../../resources/js/Pages/Setup/TestKits/Index.svelte';

afterEach(() => cleanup());

describe('Test Kit index', () => {
    it('renders each kit row action with its own immutable kit destination', () => {
        const view = render(TestKitIndex, {
            props: {
                test_kits: [
                    { id: 41, code: 'TK-A', name: 'First kit', url: '/setup/exam-materials/test-kits/41', latest_version: { version_number: 2, status: 'candidate' } },
                    { id: 82, code: 'TK-B', name: 'Second kit', url: '/setup/exam-materials/test-kits/82', latest_version: null },
                ],
                actions: { create: '/setup/exam-materials/test-kits/create' },
            },
        });

        const firstRow = within(view.getByRole('row', { name: /TK-A First kit/ }));
        const secondRow = within(view.getByRole('row', { name: /TK-B Second kit/ }));

        const firstHref = firstRow.getByRole('link', { name: 'Open Kit' }).getAttribute('href');
        const secondHref = secondRow.getByRole('link', { name: 'Open Kit' }).getAttribute('href');
        const createHref = view.getByRole('link', { name: /Create Test Kit/ }).getAttribute('href');
        const answerSheetsHref = view.getByRole('link', { name: /Manage Answer Sheets/ }).getAttribute('href');
        const bookletsHref = view.getByRole('link', { name: /Manage Question Booklets/ }).getAttribute('href');
        const testSetupHref = view.getByRole('link', { name: /Test Setup & Scoring Rules/ }).getAttribute('href');

        expect(new URL(firstHref ?? '', 'http://localhost:3000').pathname).toBe('/setup/exam-materials/test-kits/41');
        expect(new URL(secondHref ?? '', 'http://localhost:3000').pathname).toBe('/setup/exam-materials/test-kits/82');
        expect(new URL(createHref ?? '', 'http://localhost:3000').pathname).toBe('/setup/exam-materials/test-kits/create');
        expect(new URL(answerSheetsHref ?? '', 'http://localhost:3000').pathname).toBe('/setup/exam-materials/answer-sheets');
        expect(new URL(bookletsHref ?? '', 'http://localhost:3000').pathname).toBe('/setup/exam-materials/booklets');
        expect(new URL(testSetupHref ?? '', 'http://localhost:3000').pathname).toBe('/setup/test-instruments');
    });
});
