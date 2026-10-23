import { describe, expect, it } from 'vitest';
import {
    createPhysicalSheetInventory,
    physicalInventoryImpact,
    physicalInventoryImpactDigest,
} from '../../resources/js/lib/physicalSheetInventory';

describe('physical sheet inventory', () => {
    it('groups native labels without using aptitude metadata and preserves literal tokens', () => {
        const guides = [
            { id: 'guide-vocab-1', page_no: 1, customValuesStr: 'A-B, A-C, A-D, B-C, B-D, C-D' },
            { id: 'guide-comp-1', page_no: 2, customValuesStr: 'A, B, C, D, E' },
        ];
        const inventory = createPhysicalSheetInventory(guides, {
            blocks: [
                { name: 'guide-vocab-1', page_no: 1, labels: ['VOCABULARY_Q_1'] },
                { name: 'guide-comp-1', page_no: 2, labels: ['COMPUTATION_Q_1'] },
            ],
        });

        expect(inventory.sections.map((section) => [section.name, section.expected_item_count])).toEqual([
            ['Vocabulary', 1],
            ['Computation', 1],
        ]);
        expect(inventory.sections[0].items[0].option_tokens).toEqual(['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D']);
        expect(inventory.sections[1].items[0].option_tokens).toEqual(['A', 'B', 'C', 'D', 'E']);
        expect(inventory.sections[0].items[0].guide_id).toBe('guide-vocab-1');
    });

    it('reports exact deleted physical locations and gives a revision-bound digest', async () => {
        const previous = {
            revision: 8,
            sections: [{
                printed_section_uid: 'section-v',
                name: 'Vocabulary',
                page_no: 1,
                items: [
                    { physical_item_uid: 'physical-1', native_label: 'VOCABULARY_Q_1', guide_id: 'guide-1' },
                    { physical_item_uid: 'physical-2', native_label: 'VOCABULARY_Q_2', guide_id: 'guide-2' },
                ],
            }],
        };
        const next = {
            revision: 9,
            sections: [{ ...previous.sections[0], items: [previous.sections[0].items[0]] }],
        };
        const impact = physicalInventoryImpact(previous, next);

        expect(impact).toEqual([{
            physical_item_uid: 'physical-2',
            printed_section_uid: 'section-v',
            section_name: 'Vocabulary',
            native_label: 'VOCABULARY_Q_2',
            page_no: 1,
            guide_id: 'guide-2',
        }]);
        expect(await physicalInventoryImpactDigest(8, impact)).toMatch(/^[a-f0-9]{64}$/);
    });
});
