// @vitest-environment jsdom
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import OpeningAlertSignup from '../../resources/js/Components/public/OpeningAlertSignup.svelte';
import {
    loadRememberedOpeningAlertEmail,
    OPENING_ALERT_EMAIL_TTL_MS,
    rememberOpeningAlertEmail,
} from '../../resources/js/lib/opening-alert-email';
import {
    isPublicIntakeOpen,
    publicApplyCtaLabel,
} from '../../resources/js/lib/public-application-cta';
import { postOpeningAlertTokenAction } from '../../resources/js/lib/opening-alert-token';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

beforeEach(() => {
    window.localStorage.clear();
});

describe('public opening-alert signup', () => {
    it('starts with both consent and device remembering unchecked and stores no email', () => {
        render(OpeningAlertSignup, { props: { available: true } });

        expect((screen.getByRole('checkbox', { name: /Email me once/ }) as HTMLInputElement).checked).toBe(false);
        expect((screen.getByRole('checkbox', { name: /Remember this email/ }) as HTMLInputElement).checked).toBe(false);
        expect(window.localStorage.length).toBe(0);
    });

    it('submits only email and explicit consent, then remembers email only after opt-in', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({
                status: 'confirmation_sent',
                message: 'If this request can be accepted, a confirmation email will arrive shortly.',
            }),
        });
        vi.stubGlobal('fetch', fetchMock);
        const view = render(OpeningAlertSignup, { props: { available: true } });
        await fireEvent.input(screen.getByRole('textbox', { name: 'Email address' }), {
            target: { value: '  visitor@example.edu  ' },
        });
        await fireEvent.click(screen.getByRole('checkbox', { name: /Email me once/ }));
        await fireEvent.click(screen.getByRole('checkbox', { name: /Remember this email/ }));
        await fireEvent.submit(view.container.querySelector('form')!);

        await screen.findByText(/If this request can be accepted/);
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            email: 'visitor@example.edu',
            consent: true,
        });
        expect(loadRememberedOpeningAlertEmail()).toBe('visitor@example.edu');
        const remembered = JSON.parse(window.localStorage.getItem('nexiam:opening-alert-email:v1')!);
        expect(remembered.expiresAt).toBeGreaterThan(Date.now());
        expect(remembered.expiresAt).toBeLessThanOrEqual(Date.now() + OPENING_ALERT_EMAIL_TTL_MS);
        expect(screen.getByText(/does not submit an application or reserve a place/i)).toBeTruthy();
    });

    it('shows a direct application path when intake opens during signup', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            json: async () => ({ status: 'open', message: 'Applications are open.' }),
        }));
        const view = render(OpeningAlertSignup, { props: { available: true, prefilledEmail: 'handoff@example.edu' } });
        await fireEvent.input(screen.getByRole('textbox', { name: 'Email address' }), {
            target: { value: 'handoff@example.edu' },
        });
        await fireEvent.click(screen.getByRole('checkbox', { name: /Email me once/ }));
        await fireEvent.submit(view.container.querySelector('form')!);

        expect(await screen.findByRole('link', { name: /Continue to the application/ })).toBeTruthy();
    });

    it('forgets remembered email and ignores expired local values', async () => {
        rememberOpeningAlertEmail('saved@example.edu', 1_000);
        expect(loadRememberedOpeningAlertEmail(1_001)).toBe('saved@example.edu');
        expect(loadRememberedOpeningAlertEmail(1_000 + OPENING_ALERT_EMAIL_TTL_MS + 1)).toBe('');
        expect(window.localStorage.length).toBe(0);

        rememberOpeningAlertEmail('saved@example.edu');
        render(OpeningAlertSignup, { props: { available: true } });
        await fireEvent.click(screen.getByRole('button', { name: 'Forget remembered email' }));
        expect(loadRememberedOpeningAlertEmail()).toBe('');
    });
});

describe('public intake calls to action', () => {
    it('uses effective intake state and only offers alerts when the feature is available', () => {
        expect(isPublicIntakeOpen({ is_open: true }, false)).toBe(true);
        expect(isPublicIntakeOpen({ is_open: false }, true)).toBe(false);
        expect(isPublicIntakeOpen(null, true)).toBe(true);
        expect(publicApplyCtaLabel(true, true)).toBe('Start your application');
        expect(publicApplyCtaLabel(false, true)).toBe('Notify me when applications open');
        expect(publicApplyCtaLabel(false, false)).toBe('Admissions information');
    });
    it('uses the shared intake state for public navigation and retains the applicant portal path', () => {
        const homeLayout = readFileSync(
            resolve(process.cwd(), 'resources/js/Layouts/HomeLayout.svelte'),
            'utf8',
        );
        const homepage = readFileSync(
            resolve(process.cwd(), 'resources/js/Pages/Home.svelte'),
            'utf8',
        );
        const gateway = readFileSync(
            resolve(process.cwd(), 'resources/js/Components/public/AdmissionsGateway.svelte'),
            'utf8',
        );

        expect(homeLayout.split('href="/apply"').length - 1).toBe(6);
        expect(homeLayout.split('{applyLabel}').length - 1).toBe(5);
        expect(homeLayout).toContain('publicApplyCtaLabel(publicIntakeOpen, openingAlertsAvailable)');
        expect(homepage).toContain('href="/applicant/login"');
        expect(homepage).toContain('{publicApplyLabel}');
        expect(gateway).toContain('intakeOpen');
        expect(gateway).toContain('{#if publicIntakeOpen}');
        expect(gateway).toContain('href="/applicant/login"');
    });

    it('POSTs only a valid, explicit token-purpose confirmation action', () => {
        const router = { post: vi.fn() };
        const finish = vi.fn();
        expect(postOpeningAlertTokenAction(router, 'verify', 'verify', 'opaque_token-123', finish)).toBe(true);
        expect(router.post).toHaveBeenCalledWith(
            '/apply/opening-alerts/opaque_token-123',
            { purpose: 'verify' },
            { preserveScroll: true, onFinish: finish },
        );
        expect(postOpeningAlertTokenAction(router, 'unsubscribe', 'verify', 'token', finish)).toBe(false);
        expect(postOpeningAlertTokenAction(router, 'confirmed', 'verify', 'token', finish)).toBe(false);
        expect(router.post).toHaveBeenCalledOnce();
    });
});
