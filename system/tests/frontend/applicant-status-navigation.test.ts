// @vitest-environment jsdom
import { beforeEach, describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import {
    APPLICANT_ACCESS_KEY_STORAGE,
    APPLICANT_SUBMITTED_COOKIE,
    APPLICANT_SUBMITTED_STORAGE,
    isApplicantSubmitted,
    markApplicantSubmitted,
} from '../../resources/js/lib/applicant-status.js';

describe('applicant-status helper', () => {
    beforeEach(() => {
        localStorage.clear();
        document.cookie = `${APPLICANT_SUBMITTED_COOKIE}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
    });

    it('returns false when no server prop, cookie, or storage exists', () => {
        expect(isApplicantSubmitted(null)).toBe(false);
        expect(isApplicantSubmitted({})).toBe(false);
        expect(isApplicantSubmitted({ has_submitted_application: false })).toBe(false);
    });

    it('returns true when page prop has_submitted_application is true', () => {
        expect(isApplicantSubmitted({ has_submitted_application: true })).toBe(true);
    });

    it('returns true when nexiam_applicant_submitted cookie is set', () => {
        document.cookie = `${APPLICANT_SUBMITTED_COOKIE}=1; path=/`;
        expect(isApplicantSubmitted(null)).toBe(true);
    });

    it('returns true when localStorage has applicant submitted flag', () => {
        localStorage.setItem(APPLICANT_SUBMITTED_STORAGE, '1');
        expect(isApplicantSubmitted(null)).toBe(true);
    });

    it('returns true when localStorage has an applicant access key', () => {
        localStorage.setItem(APPLICANT_ACCESS_KEY_STORAGE, 'ABCD-EFGH');
        expect(isApplicantSubmitted(null)).toBe(true);
    });

    it('markApplicantSubmitted sets both cookie and localStorage', () => {
        markApplicantSubmitted();
        expect(document.cookie).toContain(`${APPLICANT_SUBMITTED_COOKIE}=1`);
        expect(localStorage.getItem(APPLICANT_SUBMITTED_STORAGE)).toBe('1');
        expect(isApplicantSubmitted(null)).toBe(true);
    });
});

describe('Homepage and HomeLayout source contracts for applicant submission CTA', () => {
    const homeSource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Pages/Home.svelte'),
        'utf8',
    );
    const homeLayoutSource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Layouts/HomeLayout.svelte'),
        'utf8',
    );
    const gatewaySource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Components/public/AdmissionsGateway.svelte'),
        'utf8',
    );
    const applySource = readFileSync(
        path.resolve(process.cwd(), 'resources/js/Pages/Admission/Apply.svelte'),
        'utf8',
    );

    it('Home.svelte renders Proceed to Portal when submitted and Start your application when not', () => {
        expect(homeSource).toContain('hasSubmittedApplication');
        expect(homeSource).toContain('Proceed to Portal');
        expect(homeSource).toContain('Start your application');
        // Uncluttered homepage: no redundant "Already applied? Applicant portal" link when not submitted
        expect(homeSource).not.toContain('Already applied?');
        expect(homeSource).toContain('Need to submit another application?');
    });

    it('AdmissionsGateway.svelte switches CTA when hasSubmittedApplication is true', () => {
        expect(gatewaySource).toContain('hasSubmittedApplication');
        expect(gatewaySource).toContain('Proceed to Portal');
        expect(gatewaySource).toContain('Start your application');
    });

    it('HomeLayout mobile menu switches places: Proceed to Portal CTA on top, Apply on bottom when submitted', () => {
        expect(homeLayoutSource).toContain('hasSubmittedApplication');
        // Mobile navigation block has conditional branch for submitted applicant
        const mobileNavSection = homeLayoutSource.slice(
            homeLayoutSource.indexOf('id="public-mobile-navigation"'),
        );
        expect(mobileNavSection).toContain('Proceed to Portal');
        expect(mobileNavSection).toContain('Apply');
    });

    it('HomeLayout desktop header shows Proceed to Portal CTA when submitted', () => {
        expect(homeLayoutSource).toContain('Proceed to Portal');
    });

    it('Apply.svelte header omits the FAQs & Ask an Office button', () => {
        expect(applySource).not.toContain('FAQs & Ask an Office');
        expect(applySource).not.toContain('data-apply-faq-inquiry-trigger');
    });
});
