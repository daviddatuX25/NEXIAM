// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import ApplicantIntakeSuggestionInput from '../../resources/js/Components/ApplicantIntakeSuggestionInput.svelte';
import type { ApplicantIntakeSuggestion } from '../../resources/js/lib/applicant-intake-suggestions';
import IntakeSuggestionFormHarness from './fixtures/IntakeSuggestionFormHarness.svelte';

afterEach(() => {
    cleanup();
});

const suggestions: readonly ApplicantIntakeSuggestion[] = [
    {
        key: 'bsit',
        label: 'Bachelor of Science in Information Technology',
        aliases: ['BSIT'],
    },
    {
        key: 'bsa',
        label: 'Bachelor of Science in Accountancy',
        aliases: ['BSA', 'BS Accountancy'],
    },
];

function renderCourseInput() {
    return render(ApplicantIntakeSuggestionInput, {
        props: {
            id: 'previous-course',
            label: 'Previous course',
            value: '',
            suggestions,
            helperText: 'Suggestions only; enter any course.',
        },
    });
}

describe('ApplicantIntakeSuggestionInput', () => {
    it('binds saved course history and typing/selection back to the correction form', async () => {
        const view = render(IntakeSuggestionFormHarness);
        const input = screen.getByRole('combobox', { name: 'Previous course or program' });
        const parentValue = () => view.getByTestId('form-strand').textContent;

        expect(input).toHaveProperty('value', 'Previously saved course');
        expect(parentValue()).toBe('Previously saved course');

        await fireEvent.input(input, { target: { value: 'BSIT' } });
        expect(parentValue()).toBe('BSIT');
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });
        await waitFor(() => expect(parentValue()).toBe('Bachelor of Science in Information Technology'));
    });

    it('keeps an unaliased school acronym until an explicit Enter choice', async () => {
        const view = render(ApplicantIntakeSuggestionInput, {
            props: {
                id: 'school',
                label: 'Last school',
                value: '',
                suggestions: [
                    {
                        key: 'tagudin',
                        label: 'Tagudin NHS (Tagudin)',
                        aliases: [],
                    },
                ],
            },
        });
        const input = view.getByRole('combobox', { name: 'Last school' });
        await fireEvent.input(input, { target: { value: 'TNHS' } });
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Tab' });
        expect(input).toHaveProperty('value', 'TNHS');
        expect(view.queryByRole('listbox')).toBeNull();
        await fireEvent.focus(input);
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });
        expect(input).toHaveProperty('value', 'Tagudin NHS (Tagudin)');
    });
    it('selects an alias match with ArrowDown and Enter', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, { target: { value: 'BSIT' } });
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });

        expect(input).toHaveProperty(
            'value',
            'Bachelor of Science in Information Technology',
        );
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('selects a suggestion by pointer click', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, { target: { value: 'BSIT' } });
        await fireEvent.click(
            view.getByRole('option', {
                name: /Bachelor of Science in Information Technology/,
            }),
        );

        expect(input).toHaveProperty(
            'value',
            'Bachelor of Science in Information Technology',
        );
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('keeps an unlisted freeform value when the applicant chooses it', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, {
            target: { value: 'BS Psychology, clinical major' },
        });
        await fireEvent.click(
            view.getByRole('option', {
                name: 'Use entered text: BS Psychology, clinical major',
            }),
        );

        expect(input).toHaveProperty('value', 'BS Psychology, clinical major');
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('closes on Tab without changing the typed value', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, { target: { value: 'BS' } });
        await fireEvent.keyDown(input, { key: 'Tab' });

        expect(input).toHaveProperty('value', 'BS');
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('does not replace an acronym on Tab after highlighting its unique match', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, { target: { value: 'BSIT' } });
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Tab' });

        expect(input).toHaveProperty('value', 'BSIT');
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('reveals choices on empty focus and reopens them on click without selecting', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });
        await fireEvent.focus(input);
        expect(input.getAttribute('aria-expanded')).toBe('true');
        expect(
            view
                .getByRole('option', {
                    name: 'Bachelor of Science in Accountancy',
                })
                .getAttribute('aria-selected'),
        ).toBe('false');
        expect(input).toHaveProperty('value', '');
        await fireEvent.keyDown(input, { key: 'Escape' });
        expect(view.queryByRole('listbox')).toBeNull();
        await fireEvent.click(input);
        expect(input.getAttribute('aria-expanded')).toBe('true');
        expect(input).toHaveProperty('value', '');
    });

    it('moves ArrowUp from no active option to the last choice', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, { target: { value: 'BS' } });
        await fireEvent.keyDown(input, { key: 'ArrowUp' });

        expect(input.getAttribute('aria-activedescendant')).toBe(
            'previous-course-suggestions-custom',
        );
        expect(
            view
                .getByRole('option', { name: 'Use entered text: BS' })
                .getAttribute('aria-selected'),
        ).toBe('true');
    });
    it('does not complete a unique alias after Escape dismisses the list', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, { target: { value: 'BSIT' } });
        await fireEvent.keyDown(input, { key: 'Escape' });
        await fireEvent.keyDown(input, { key: 'Tab' });

        expect(input).toHaveProperty('value', 'BSIT');
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('scrolls the active keyboard option into view', async () => {
        const original = Object.getOwnPropertyDescriptor(
            HTMLElement.prototype,
            'scrollIntoView',
        );
        const scrollIntoView = vi.fn();
        Object.defineProperty(HTMLElement.prototype, 'scrollIntoView', {
            configurable: true,
            value: scrollIntoView,
        });

        try {
            const view = renderCourseInput();
            const input = view.getByRole('combobox', {
                name: 'Previous course',
            });

            await fireEvent.input(input, { target: { value: 'BS' } });
            await fireEvent.keyDown(input, { key: 'ArrowDown' });

            await waitFor(() =>
                expect(scrollIntoView).toHaveBeenCalledWith({
                    block: 'nearest',
                }),
            );
        } finally {
            if (original) {
                Object.defineProperty(
                    HTMLElement.prototype,
                    'scrollIntoView',
                    original,
                );
            } else {
                Reflect.deleteProperty(HTMLElement.prototype, 'scrollIntoView');
            }
        }
    });

    it('closes on Escape without discarding the applicant entry', async () => {
        const view = renderCourseInput();
        const input = view.getByRole('combobox', { name: 'Previous course' });

        await fireEvent.input(input, {
            target: { value: 'An unlisted program' },
        });
        await fireEvent.keyDown(input, { key: 'Escape' });

        expect(input).toHaveProperty('value', 'An unlisted program');
        expect(view.queryByRole('listbox')).toBeNull();
    });

    it('keeps an empty catalog usable for unlisted names', async () => {
        const view = render(ApplicantIntakeSuggestionInput, {
            props: {
                id: 'empty-school',
                label: 'Last school',
                suggestions: [],
            },
        });
        const input = view.getByRole('combobox', { name: 'Last school' });
        await fireEvent.focus(input);
        expect(input.getAttribute('aria-expanded')).toBe('false');
        await fireEvent.input(input, {
            target: { value: 'My unlisted school' },
        });
        await fireEvent.click(
            view.getByRole('option', {
                name: 'Use entered text: My unlisted school',
            }),
        );
        expect(input).toHaveProperty('value', 'My unlisted school');
    });
});
