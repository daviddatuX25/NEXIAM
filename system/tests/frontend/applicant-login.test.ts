// @vitest-environment jsdom

import { cleanup, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const mocks = vi.hoisted(() => ({
    page: { props: { flash: {} as Record<string, unknown>, errors: {} } },
    post: vi.fn(),
    transform: vi.fn(),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        usePage: () => mocks.page,
        useForm(initial: Record<string, unknown>) {
            const formObj = {
                ...initial,
                errors: {},
                processing: false,
                clearErrors: vi.fn(),
                transform(fn: (data: unknown) => unknown) {
                    mocks.transform(fn(initial));
                    return {
                        post: mocks.post,
                    };
                },
                post: mocks.post,
            };
            return formObj;
        },
        router: {
            post: vi.fn(),
        },
        Link: () => null,
    };
});

vi.mock('@/Layouts/GuestLayout.svelte', () => ({
    default: (
        _anchor: unknown,
        { children }: { children?: (anchor: unknown) => void },
    ) => children?.(_anchor),
}));

import Login from '../../resources/js/Pages/Applicant/Login.svelte';

beforeEach(() => {
    localStorage.clear();
    mocks.post.mockReset();
    mocks.transform.mockReset();
});

afterEach(() => {
    cleanup();
});

describe('Applicant/Login', () => {
    it('does not render the demo persona access section even when demo portals are supplied', () => {
        const demoProp = {
            access_key: 'PORT-DEFL',
            birthdate: '2003-09-25',
            label: 'Portal demo (default)',
            portals: [
                { key: 'PORT-ONLN', label: 'Released online' },
                { key: 'PORT-F2F2', label: 'Released f2f' },
                { key: 'PORT-DEFL', label: 'Guidance default' },
            ],
        };

        render(Login, {
            props: {
                page_state: 'ready',
                demo: demoProp,
                errors: {},
            },
        });

        // The "Demo persona access" heading must not be present
        expect(screen.queryByText(/Demo persona access/i)).toBeNull();

        // The demo shortcut buttons must not be rendered
        expect(screen.queryByRole('button', { name: 'PORT-ONLN' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'PORT-F2F2' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'PORT-DEFL' })).toBeNull();
    });

    it('renders clean blank access key and birthdate input fields by default', () => {
        render(Login, {
            props: {
                page_state: 'ready',
                demo: null,
                errors: {},
            },
        });

        const keyInput = screen.getByLabelText(/Access key/i);
        expect(keyInput).toHaveProperty('value', '');

        const birthdateInput = screen.getByLabelText(/Birthdate/i);
        expect(birthdateInput).toHaveProperty('value', '');
    });
});
