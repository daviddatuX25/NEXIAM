import { describe, expect, it } from 'vitest';
import type { ApplicantIntakeSuggestion } from '../../resources/js/lib/applicant-intake-suggestions';
import { filterApplicantIntakeSuggestions } from '../../resources/js/lib/applicant-intake-suggestions';

const suggestions: readonly ApplicantIntakeSuggestion[] = [
    {
        key: 'bsit',
        label: 'Bachelor of Science in Information Technology',
        aliases: ['BSIT', 'Information Technology'],
    },
    {
        key: 'bsis',
        label: 'Bachelor of Science in Information Systems',
        aliases: ['BSIS'],
    },
    {
        key: 'accountancy',
        label: 'Bachelor of Science in Accountancy',
        aliases: ['BSA'],
    },
];

describe('applicant intake suggestion matching', () => {
    it('matches aliases case-insensitively and returns the canonical suggestion', () => {
        expect(
            filterApplicantIntakeSuggestions(suggestions, 'bsit')[0]?.key,
        ).toBe('bsit');
    });

    it('ranks a prefix match in an alias before a match within a title', () => {
        expect(
            filterApplicantIntakeSuggestions(suggestions, 'Information').map(
                (suggestion) => suggestion.key,
            ),
        ).toEqual(['bsit', 'bsis']);
    });

    it('returns a bounded alphabetic list for empty query and none for unknown query', () => {
        expect(
            filterApplicantIntakeSuggestions(suggestions, '', 2).map(
                (suggestion) => suggestion.key,
            ),
        ).toEqual(['accountancy', 'bsis']);
        expect(
            filterApplicantIntakeSuggestions(suggestions, 'no-match'),
        ).toEqual([]);
    });

    it('retains distinct records with same-named labels', () => {
        const campuses: readonly ApplicantIntakeSuggestion[] = [
            {
                key: 'hei-region-one',
                label: 'Regional University Main Campus',
                aliases: ['RUMC Region I'],
            },
            {
                key: 'hei-region-three',
                label: 'Regional University Main Campus',
                aliases: ['RUMC Region III'],
            },
        ];

        expect(
            filterApplicantIntakeSuggestions(campuses, 'RUMC Region').map(
                (suggestion) => suggestion.key,
            ),
        ).toEqual(['hei-region-one', 'hei-region-three']);
    });

    it('finds source-style school initials without requiring stored acronym aliases', () => {
        const schools: ApplicantIntakeSuggestion[] = [
            {
                key: 'tagudin-main',
                label: 'Tagudin NHS (Tagudin)',
                aliases: [],
            },
            {
                key: 'tagudin-other',
                label: 'Tagudin NHS (Mabini)',
                aliases: [],
            },
        ];
        expect(
            filterApplicantIntakeSuggestions(schools, 'TNHS').map(
                (item) => item.key,
            ),
        ).toEqual(['tagudin-other', 'tagudin-main']);
    });

    it('keeps literal aliases and names ahead of exact and prefix initials', () => {
        const schools: ApplicantIntakeSuggestion[] = [
            {
                key: 'longer-initials',
                label: 'South Aviation School Institute',
                aliases: [],
            },
            {
                key: 'saint-augustine',
                label: "St. Augustine's School Inc.",
                aliases: [],
            },
            {
                key: 'literal-name',
                label: 'SAS Training Institute',
                aliases: [],
            },
            {
                key: 'official-alias',
                label: 'Example School',
                aliases: ['SAS'],
            },
        ];
        expect(
            filterApplicantIntakeSuggestions(schools, 'SAS').map(
                (item) => item.key,
            ),
        ).toEqual([
            'official-alias',
            'literal-name',
            'saint-augustine',
            'longer-initials',
        ]);
    });

    it('matches the main college initials before its longer campus wording', () => {
        const college: ApplicantIntakeSuggestion = {
            key: 'ispsc',
            label: 'Ilocos Sur Polytechnic State College-College of Arts and Sciences-Tagudin',
            aliases: [],
        };
        expect(
            filterApplicantIntakeSuggestions([college], 'ispsc').map(
                (item) => item.key,
            ),
        ).toEqual(['ispsc']);
    });

    it('matches punctuated course initials without manually supplied aliases', () => {
        const course: ApplicantIntakeSuggestion = {
            key: 'information-management',
            label: 'Bachelor of Science in Information Management',
            aliases: [],
        };
        expect(
            filterApplicantIntakeSuggestions([course], 'b–s/i.m.').map(
                (item) => item.key,
            ),
        ).toEqual(['information-management']);
    });
});
