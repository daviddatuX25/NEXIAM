import { describe, expect, it } from 'vitest';
import {
    emitNativeMapping,
    syncDeclaredInventoryFromSections,
} from '../../resources/js/lib/omrStudioGeometry';

const pairedComparisonLabels = ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D'];

function questionBlock(overrides: Record<string, unknown> = {}) {
    return {
        id: 'questions',
        type: 'mcq',
        x_mm: 20,
        y_mm: 30,
        w_mm: 100,
        h_mm: 20,
        questions: 1,
        options: 4,
        start_q: 1,
        ...overrides,
    };
}

describe('OMR option variance', () => {
    it('syncs paired-comparison labels into declared inventory', () => {
        const inventory = syncDeclaredInventoryFromSections([
            {
                id: 'paired-comparison',
                type: 'mcq',
                area_code: 'LOGIC',
                questionsCount: 1,
                optionsCount: 6,
                optionLabels: pairedComparisonLabels,
            },
        ]);

        expect(inventory[0].option_labels).toEqual(pairedComparisonLabels);
    });

    it('syncs true/false labels into declared inventory', () => {
        const inventory = syncDeclaredInventoryFromSections([
            {
                id: 'true-false',
                type: 'tf',
                area_code: 'LOGIC',
                questionsCount: 1,
                optionsCount: 2,
                optionLabels: ['TRUE', 'FALSE'],
            },
        ]);

        expect(inventory[0].option_labels).toEqual(['TRUE', 'FALSE']);
    });

    it('emits paired-comparison values and option count in the native mapping', () => {
        const mapping = emitNativeMapping(210, 297, [
            questionBlock({
                options: 6,
                optionLabels: pairedComparisonLabels,
            }),
        ]);

        expect(mapping.blocks[0].bubble_values).toEqual(pairedComparisonLabels);
        expect(mapping.blocks[0].options).toBe(6);
    });

    it('omits custom bubble values for standard four-choice mapping', () => {
        const mapping = emitNativeMapping(210, 297, [
            questionBlock({ optionLabels: ['A', 'B', 'C', 'D'] }),
        ]);

        expect(mapping.blocks[0].bubble_values).toBeUndefined();
        expect(mapping.blocks[0].options).toBe(4);
    });
});
