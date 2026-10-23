const isPositiveInteger = (value) => Number.isInteger(value) && value > 0;

/** Build display-only shading from an exact Kit physical-location UID and literal answer token. */
export function buildBookletAnswerPreviewModel({ bubbles = [], answers = [] } = {}) {
    const expected = bubbles.filter((bubble) =>
        bubble &&
        typeof bubble.physical_item_uid === 'string' &&
        bubble.physical_item_uid !== '' &&
        isPositiveInteger(Number(bubble.page_no)) &&
        bubble.options &&
        typeof bubble.options === 'object',
    );
    const expectedCounts = new Map();
    for (const bubble of expected) {
        expectedCounts.set(
            bubble.physical_item_uid,
            (expectedCounts.get(bubble.physical_item_uid) ?? 0) + 1,
        );
    }

    const answersByIdentity = new Map();
    const duplicateAnswers = new Set();
    for (const answer of answers) {
        if (
            !answer ||
            typeof answer.physical_item_uid !== 'string' ||
            expectedCounts.get(answer.physical_item_uid) !== 1
        ) {
            continue;
        }
        if (answersByIdentity.has(answer.physical_item_uid)) {
            duplicateAnswers.add(answer.physical_item_uid);
            continue;
        }
        answersByIdentity.set(answer.physical_item_uid, answer.correct_option_code);
    }

    const overlays = [];
    const missingPositions = [];
    for (const bubble of expected) {
        const uid = bubble.physical_item_uid;
        const option = duplicateAnswers.has(uid) ? null : answersByIdentity.get(uid);
        const geometry = typeof option === 'string' ? bubble.options[option] : null;
        if (
            expectedCounts.get(uid) === 1 &&
            typeof option === 'string' &&
            Object.hasOwn(bubble.options, option) &&
            Number.isFinite(geometry?.cx) &&
            Number.isFinite(geometry?.cy) &&
            Number.isFinite(geometry?.r) &&
            geometry.r > 0
        ) {
            overlays.push({ physical_item_uid: uid, option, geometry });
            continue;
        }
        missingPositions.push({
            physical_item_uid: uid,
            page_no: bubble.page_no,
            question_no: bubble.question_no,
            global_position: bubble.global_position,
        });
    }

    return { overlays, missingPositions };
}
