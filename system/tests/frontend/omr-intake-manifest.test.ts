import { describe, expect, it } from 'vitest';
import {
    enqueueOmrManifestFiles,
    updateOmrManifestEntry,
} from '../../resources/js/lib/omrIntakeManifest.js';

describe('OMR mixed intake manifest', () => {
    it('groups ordered files into immutable multi-page sheet identities', () => {
        let sequence = 0;
        const files = [
            new File(['a'], 'sheet-1-page-1.png', {
                type: 'image/png',
                lastModified: 1,
            }),
            new File(['b'], 'sheet-1-page-2.png', {
                type: 'image/png',
                lastModified: 2,
            }),
            new File(['c'], 'sheet-2-page-1.png', {
                type: 'image/png',
                lastModified: 3,
            }),
        ];
        const result = enqueueOmrManifestFiles(
            [],
            files,
            2,
            'manual',
            () => `id-${++sequence}`,
        );

        expect(result.added.map((entry) => entry.pageNo)).toEqual([1, 2, 1]);
        expect(result.added[0]?.clientCaptureId).toBe(
            result.added[1]?.clientCaptureId,
        );
        expect(result.added[2]?.clientCaptureId).not.toBe(
            result.added[0]?.clientCaptureId,
        );
        expect(result.added.every((entry) => entry.captureMode === 'manual')).toBe(
            true,
        );
    });

    it('deduplicates against queued files and preserves retryable state', () => {
        let sequence = 0;
        const file = new File(['same'], 'sheet.png', {
            type: 'image/png',
            lastModified: 7,
        });
        const initial = enqueueOmrManifestFiles(
            [],
            [file],
            1,
            'auto',
            () => `id-${++sequence}`,
        );
        const repeated = enqueueOmrManifestFiles(
            initial.entries,
            [file],
            1,
            'auto',
            () => `id-${++sequence}`,
        );
        const failed = updateOmrManifestEntry(
            repeated.entries,
            initial.added[0]!.id,
            { state: 'failed', error: 'Upload interrupted', progress: 0 },
        );

        expect(repeated.added).toHaveLength(0);
        expect(repeated.duplicateCount).toBe(1);
        expect(failed[0]).toMatchObject({
            state: 'failed',
            error: 'Upload interrupted',
            progress: 0,
        });
    });
});
