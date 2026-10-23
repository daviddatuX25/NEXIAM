import { readFileSync } from 'node:fs';
import { describe, expect, expectTypeOf, it } from 'vitest';
import { filterFaqs, matchFaqDeflection, type PublicFaqItem } from '../../resources/js/lib/public-faq';

const faqs: PublicFaqItem[] = [
    {
        id: 'campus-1',
        slug: 'campus-hours',
        category: 'campus',
        question: 'What are the campus office hours?',
        answer: 'Offices are open Monday through Friday.',
        office_code: 'admin',
        office_label: 'Central Admin',
        governing_policy: null,
        updated_at: '2026-10-01T00:00:00Z',
    },
    {
        id: 'admissions-1',
        slug: 'application-form',
        category: 'admissions',
        question: 'How do I complete the application form?',
        answer: 'Submit the online admissions form before the deadline.',
        office_code: 'registrar',
        office_label: 'Registrar',
        governing_policy: null,
        updated_at: '2026-10-02T00:00:00Z',
    },
    {
        id: 'testing-1',
        slug: 'testing-schedule',
        category: 'testing',
        question: 'When is the entrance test?',
        answer: 'Check your applicant portal for the testing schedule.',
        office_code: 'guidance',
        office_label: 'Guidance',
        governing_policy: null,
        updated_at: '2026-10-03T00:00:00Z',
    },
];

describe('public FAQ surface', () => {
    describe('filterFaqs', () => {
        it.each(['campus', 'admissions', 'testing'])('filters the %s category', (category) => {
            expect(filterFaqs(faqs, '', category).map((faq) => faq.category)).toEqual([category]);
        });

        it('returns every category when All is selected', () => {
            expect(filterFaqs(faqs, '', 'all')).toEqual(faqs);
        });

        it('searches questions and answers without regard to case or surrounding whitespace', () => {
            expect(filterFaqs(faqs, '  ONLINE ADMISSIONS FORM  ', 'all')).toEqual([faqs[1]]);
            expect(filterFaqs(faqs, 'portal schedule', 'testing')).toEqual([faqs[2]]);
            expect(filterFaqs(faqs, 'portal schedule', 'campus')).toEqual([]);
        });
    });

    describe('matchFaqDeflection', () => {
        it('returns the FAQ whose question matches the inquiry subject', () => {
            expect(matchFaqDeflection(faqs, '  How do I complete the application form?  ')).toBe(faqs[1]);
        });

        it('returns the highest-scoring FAQ when the subject partially overlaps multiple questions', () => {
            const candidates = [
                {
                    ...faqs[0],
                    question: 'Application documents are required at registration',
                },
                {
                    ...faqs[1],
                    question: 'How do I complete the application form?',
                },
            ];

            expect(matchFaqDeflection(candidates, 'application form deadline')).toBe(candidates[1]);
        });

        it('returns no suggestion when the subject does not match an FAQ question', () => {
            expect(matchFaqDeflection(faqs, 'I need help with an unrelated account issue')).toBeNull();
        });
    });

    it('keeps internal draft and note fields out of the public FAQ DTO type', () => {
        expectTypeOf<PublicFaqItem>().not.toHaveProperty('draft');
        expectTypeOf<PublicFaqItem>().not.toHaveProperty('internal_notes');
    });

    it('keeps public FAQ controls touch-sized and surfaces mobile width constraints', () => {
        const publicPage = readFileSync(new URL('../../resources/js/Pages/Public/Faqs.svelte', import.meta.url), 'utf8');
        const applicantWidget = readFileSync(new URL('../../resources/js/Components/Applicant/ApplicantFaqWidget.svelte', import.meta.url), 'utf8');
        const surface = `${publicPage}\n${applicantWidget}`;
        const buttonClasses = [...surface.matchAll(/<button\b[^>]*\bclass="([^"]*)"[^>]*>/g)].map((match) => match[1]);

        expect(buttonClasses.length).toBeGreaterThan(0);
        expect(buttonClasses.every((classes) => classes.includes('min-h-[44px]'))).toBe(true);
        expect(surface).toMatch(/(?:overflow-x-hidden|max-w-full)/);
    });

    it('surfaces FAQs in public navigation and rebrands footer to institutional services', () => {
        const homeLayout = readFileSync(new URL('../../resources/js/Layouts/HomeLayout.svelte', import.meta.url), 'utf8');
        const homePage = readFileSync(new URL('../../resources/js/Pages/Home.svelte', import.meta.url), 'utf8');

        // Footer rebrand
        expect(homeLayout).toContain('Applicant services');
        expect(homeLayout).not.toContain('NEXIAM services');
        expect(homeLayout).toContain('Admissions and testing powered by NEXIAM.');

        // Public header and footer navigation
        expect(homeLayout).toMatch(/href:\s*['"]\/faqs['"],\s*label:\s*['"]FAQs['"]/);
        expect(homeLayout).toContain('href="/faqs"');
        expect(homeLayout).toContain('Frequently asked questions');
        // Homepage signpost
        expect(homePage).toMatch(/id=['"]faqs['"]/);
        expect(homePage).toContain('href="/faqs"');
        expect(homePage).toContain('Browse FAQs');
    });

    it('surfaces FAQs in applicant sidebar and portal dashboard with terminal state icon safety', () => {
        const applicantLayout = readFileSync(new URL('../../resources/js/Pages/Applicant/ApplicantLayout.svelte', import.meta.url), 'utf8');
        const portal = readFileSync(new URL('../../resources/js/Pages/Applicant/Portal.svelte', import.meta.url), 'utf8');

        // Sidebar FAQ navigation and icon
        expect(applicantLayout).toMatch(/key:\s*['"]faqs['"],\s*label:\s*['"]FAQs['"],\s*href:\s*['"]\/faqs['"]/);
        expect(applicantLayout).toMatch(/case\s*['"]faqs['"]:\s*return\s*HelpCircle;/);

        // Portal signpost and terminal state icon
        expect(portal).toContain('AlertTriangle');
        expect(portal).toContain('href="/faqs"');
        expect(portal).toContain('Browse FAQs');
        expect(portal).toContain('href="/applicant/admission-support"');
    });
});
