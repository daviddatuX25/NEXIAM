// @vitest-environment jsdom

import { describe, expect, it } from 'vitest';
import {
    buildCardCorpus,
    cardMatchesTokens,
    normalizeText,
    tokenizeQuery,
} from '../../resources/js/lib/setup-search';

describe('Setup Hub dynamic search and inner words/terms matching', () => {
    describe('normalizeText', () => {
        it('normalizes null, undefined and trims/lowercases text', () => {
            expect(normalizeText(null)).toBe('');
            expect(normalizeText(undefined)).toBe('');
            expect(normalizeText('  CAMPUS Rooms  ')).toBe('campus rooms');
            expect(normalizeText('Cafe\u0301')).toBe('café');
        });
    });

    describe('tokenizeQuery', () => {
        it('returns empty array for blank or whitespace queries', () => {
            expect(tokenizeQuery('')).toEqual([]);
            expect(tokenizeQuery('   ')).toEqual([]);
            expect(tokenizeQuery(null)).toEqual([]);
        });

        it('splits on whitespace and deduplicates tokens', () => {
            expect(tokenizeQuery('room capacity room')).toEqual([
                'room',
                'capacity',
            ]);
        });

        it('strips peripheral punctuation while preserving valid alphanumeric tokens', () => {
            expect(tokenizeQuery('room, [omr] "scoring"')).toEqual([
                'room',
                'omr',
                'scoring',
            ]);
            expect(tokenizeQuery('100%')).toEqual(['100%']);
        });
    });

    describe('buildCardCorpus', () => {
        it('compiles all card fields, checks, and aliases into a unified searchable text', () => {
            const card = {
                key: 'rooms',
                label: 'Campus Rooms',
                description:
                    'Configure physical rooms, capacities, and entrance photos.',
                summary_metric: '12 rooms ready',
                desk: 'admin',
                capability: 'setup.rooms.manage',
                checks: [
                    {
                        label: 'GPS coordinates stamped',
                        detail: 'ITBR-106 doorway verified',
                    },
                ],
            };

            const corpus = buildCardCorpus(
                card,
                'Stage 3: Examination Operations',
                'Venue & Room Setup',
            );

            expect(corpus).toContain('campus rooms');
            expect(corpus).toContain('itbr-106 doorway verified');
            expect(corpus).toContain('admin desk');
            expect(corpus).toContain('setup rooms manage');
            expect(corpus).toContain('venue & room setup');
            expect(corpus).toContain('wayfinding'); // from DOMAIN_ALIASES
            expect(corpus).toContain('latitude'); // from DOMAIN_ALIASES
        });

        it('handles null, undefined, or empty card fields gracefully', () => {
            const card = {
                key: 'unknown_key',
                label: null,
                description: undefined,
                summary_metric: null,
                checks: null,
            };

            const corpus = buildCardCorpus(card);
            expect(corpus).toBe('unknown_key');
        });
    });

    describe('cardMatchesTokens (inner words / terms matching)', () => {
        const roomsCorpus = buildCardCorpus({
            key: 'rooms',
            label: 'Campus Rooms',
            description:
                'Configure physical rooms, building locations, GPS coordinates, and entrance photos.',
            summary_metric: '12 rooms ready',
            desk: 'admin',
            capability: 'setup.rooms.manage',
            checks: [
                {
                    label: 'Room capacity set',
                    detail: 'Minimum 30 seats per room',
                },
            ],
        });

        const answerSheetsCorpus = buildCardCorpus({
            key: 'answer_sheets',
            label: 'Answer Sheets',
            description:
                'Upload, calibrate, and print physical answer sheets for optical mark recognition.',
            summary_metric: 'Draft 2 active',
            desk: 'testing',
            capability: 'guidance.scoring.omr',
        });

        it('matches on exact words and inner words (substrings)', () => {
            // "room" matches "rooms"
            expect(cardMatchesTokens(roomsCorpus, ['room'])).toBe(true);
            // "way" matches "wayfinding" (via alias)
            expect(cardMatchesTokens(roomsCorpus, ['way'])).toBe(true);
            // "coord" matches "coordinates"
            expect(cardMatchesTokens(roomsCorpus, ['coord'])).toBe(true);
            // "photo" matches "photos"
            expect(cardMatchesTokens(roomsCorpus, ['photo'])).toBe(true);
            // "seat" matches "seats" in checks
            expect(cardMatchesTokens(roomsCorpus, ['seat'])).toBe(true);
        });

        it('matches domain aliases such as OMR and scanner for Answer Sheets', () => {
            expect(cardMatchesTokens(answerSheetsCorpus, ['omr'])).toBe(true);
            expect(cardMatchesTokens(answerSheetsCorpus, ['bubble'])).toBe(
                true,
            );
            expect(cardMatchesTokens(answerSheetsCorpus, ['optical'])).toBe(
                true,
            );
            expect(cardMatchesTokens(answerSheetsCorpus, ['scanner'])).toBe(
                true,
            );
        });

        it('satisfies multi-term AND conjunction (all terms must be in the corpus)', () => {
            // Both terms are present in rooms corpus
            expect(cardMatchesTokens(roomsCorpus, ['room', 'capacity'])).toBe(
                true,
            );
            expect(cardMatchesTokens(roomsCorpus, ['capacity', 'room'])).toBe(
                true,
            ); // order independent
            expect(
                cardMatchesTokens(roomsCorpus, ['campus', 'gps', 'entrance']),
            ).toBe(true);

            // Cross-field match: token 1 in label ("campus"), token 2 in desk ("admin"), token 3 in check ("capacity")
            expect(
                cardMatchesTokens(roomsCorpus, ['campus', 'admin', 'capacity']),
            ).toBe(true);

            // One term matches, other does not -> FALSE
            expect(cardMatchesTokens(roomsCorpus, ['room', 'omr'])).toBe(false);
            expect(cardMatchesTokens(roomsCorpus, ['campus', 'norm'])).toBe(
                false,
            );
        });

        it('handles special characters and punctuation safely without regex crashes', () => {
            expect(cardMatchesTokens(roomsCorpus, ['[', '+', '*'])).toBe(false);
            expect(cardMatchesTokens(answerSheetsCorpus, ['omr'])).toBe(true);
        });

        it('returns true when query tokens array is empty', () => {
            expect(cardMatchesTokens(roomsCorpus, [])).toBe(true);
            expect(cardMatchesTokens(answerSheetsCorpus, [])).toBe(true);
        });
    });
});
