// @vitest-environment jsdom

import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it } from 'vitest';
import MaterialDocumentPreview from '../../resources/js/Components/kit/MaterialDocumentPreview.svelte';

afterEach(() => cleanup());

function getWireframe(container: HTMLElement): Element | null {
    return container.querySelector('[class*="absolute"][class*="inset-1.5"]');
}

describe('MaterialDocumentPreview', () => {
    it.each([null, ''])('renders the wireframe when src is %s', (src) => {
        const { container } = render(MaterialDocumentPreview, { src });

        expect(container.querySelector('img')).toBeNull();
        expect(getWireframe(container)).not.toBeNull();
    });

    it('renders the image when src is provided', () => {
        const { container } = render(MaterialDocumentPreview, {
            src: '/thumbnails/report.png',
        });

        expect(container.querySelector('img')?.getAttribute('src')).toBe(
            '/thumbnails/report.png',
        );
        expect(getWireframe(container)).toBeNull();
    });

    it('shows the wireframe after the image fails to load', async () => {
        const { container } = render(MaterialDocumentPreview, {
            src: '/thumbnails/missing.png',
        });

        await fireEvent.error(container.querySelector('img')!);

        expect(container.querySelector('img')).toBeNull();
        expect(getWireframe(container)).not.toBeNull();
    });

    it('resets the failure state when src changes to a new URL', async () => {
        const view = render(MaterialDocumentPreview, {
            src: '/thumbnails/missing.png',
        });
        await fireEvent.error(view.container.querySelector('img')!);
        expect(getWireframe(view.container)).not.toBeNull();

        await view.rerender({ src: '/thumbnails/replacement.png' });

        expect(view.container.querySelector('img')?.getAttribute('src')).toBe(
            '/thumbnails/replacement.png',
        );
        expect(getWireframe(view.container)).toBeNull();
    });

    it('renders the booklet overlay badge when overlay is booklet with src', () => {
        const { container } = render(MaterialDocumentPreview, {
            src: '/thumbnails/sheet.png',
            overlay: 'booklet',
        });

        expect(container.querySelector('[aria-label="Question Booklet"]')).not.toBeNull();
        expect(container.textContent).toContain('Booklet');
    });

    it('renders the booklet overlay badge for stored_questions and answer_keys_only with src', () => {
        const { container } = render(MaterialDocumentPreview, {
            src: '/thumbnails/sheet.png',
            kind: 'answer_keys_only',
        });

        expect(container.querySelector('[aria-label="Question Booklet"]')).not.toBeNull();
    });
});
