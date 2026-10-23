const PRINTED_SECTION_NAMES = {
    VOCABULARY: 'Vocabulary',
    COMPUTATION: 'Computation',
    SPATIAL: 'Spatial Relations',
    WORD_COMP: 'Word Comparison',
};

function tokensFromGuide(guide, block) {
    const explicit = block?.bubble_values ?? block?.bubbleValues;
    if (Array.isArray(explicit) && explicit.length > 1) return [...explicit];

    const source = guide?.customValuesStr;
    if (typeof source === 'string' && source.trim() !== '') {
        return source.split(',').map((token) => token.trim()).filter(Boolean);
    }

    if (Array.isArray(guide?.optionLabels) && guide.optionLabels.length > 1) {
        return [...guide.optionLabels];
    }

    return [];
}

export function createPhysicalSheetInventory(studioSections, mappingInput) {
    const guidesById = new Map(
        (Array.isArray(studioSections) ? studioSections : []).map((guide) => [String(guide.id), guide]),
    );
    const groups = new Map();
    for (const block of Array.isArray(mappingInput?.blocks) ? mappingInput.blocks : []) {
        const guide = guidesById.get(String(block?.name ?? ''));
        const labels = Array.isArray(block?.labels) ? block.labels : [];
        for (const nativeLabel of labels) {
            const match = /^([A-Z][A-Z0-9_]*)_Q_([1-9][0-9]*)$/.exec(String(nativeLabel));
            if (!match) continue;

            const [, nativeCode, printedNumberText] = match;
            const pageNo = Number(block.page_no ?? guide?.page_no ?? 1);
            const groupKey = `${pageNo}:${nativeCode}`;
            let group = groups.get(groupKey);
            if (!group) {
                group = {
                    printed_section_uid: crypto.randomUUID(),
                    native_code: nativeCode,
                    name: PRINTED_SECTION_NAMES[nativeCode] ?? nativeCode.replaceAll('_', ' '),
                    page_no: pageNo,
                    numbering: { start: Number(printedNumberText), step: 1, prefix: '', suffix: '' },
                    expected_item_count: 0,
                    display_order: groups.size + 1,
                    items: [],
                };
                groups.set(groupKey, group);
            }

            const printedNumber = Number(printedNumberText);
            group.items.push({
                physical_item_uid: crypto.randomUUID(),
                native_label: String(nativeLabel),
                page_no: pageNo,
                printed_number: printedNumber,
                option_tokens: tokensFromGuide(guide, block),
                guide_id: String(block?.name ?? ''),
            });
            group.expected_item_count += 1;
        }
    }

    return { revision: groups.size > 0 ? 1 : 0, sections: [...groups.values()] };
}

export function physicalInventoryImpact(previousInventory, nextInventory) {
    const nextSections = new Map(
        (Array.isArray(nextInventory?.sections) ? nextInventory.sections : []).map((section) => [section.printed_section_uid, section]),
    );
    const nextItems = new Map();
    for (const section of nextSections.values()) {
        for (const item of Array.isArray(section.items) ? section.items : []) {
            nextItems.set(item.physical_item_uid, { section, item });
        }
    }

    const impact = [];
    for (const section of Array.isArray(previousInventory?.sections) ? previousInventory.sections : []) {
        for (const item of Array.isArray(section.items) ? section.items : []) {
            const next = nextItems.get(item.physical_item_uid);
            if (!next || next.section.printed_section_uid !== section.printed_section_uid || Number(next.section.page_no) !== Number(section.page_no)) {
                impact.push({
                    physical_item_uid: item.physical_item_uid,
                    printed_section_uid: section.printed_section_uid,
                    section_name: section.name,
                    native_label: item.native_label ?? null,
                    page_no: Number(section.page_no),
                    guide_id: item.guide_id ?? null,
                });
            }
        }
    }

    impact.sort((left, right) => left.physical_item_uid.localeCompare(right.physical_item_uid));
    return impact;
}

export async function physicalInventoryImpactDigest(revision, impact) {
    const value = JSON.stringify({ revision: Number(revision), impact });
    const bytes = new TextEncoder().encode(value);
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return Array.from(new Uint8Array(digest), (byte) => byte.toString(16).padStart(2, '0')).join('');
}
