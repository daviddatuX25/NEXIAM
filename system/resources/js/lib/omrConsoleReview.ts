export type OmrConsoleDecision =
    'confirm_machine' | 'set_option' | 'blank' | 'unresolved';

export type OmrConsoleReviewPayload = {
    decision: OmrConsoleDecision;
    selected_option: string | null;
    reason_code: string | null;
    note: string | null;
    expected_parent_version: number | string;
    expected_item_revision: number;
    reviewed_machine_evidence_hash: string;
    idempotency_key: string;
};

export type OmrConsoleAcknowledgedReview = {
    revision: number;
    decision: OmrConsoleDecision;
    selected_option: string | null;
    reason_code: string | null;
    note: string | null;
    reviewer: {
        id: string | number | null;
        display_name: string | null;
    } | null;
    reviewed_at: string | null;
    evidence_hash: string;
    parent_version?: number;
    stale: false;
};

type UnknownRecord = Record<string, unknown>;

function record(value: unknown): UnknownRecord {
    return value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as UnknownRecord)
        : {};
}

function requiredInteger(value: unknown, label: string): number {
    const parsed = Number(value);
    if (!Number.isSafeInteger(parsed) || parsed < 0) {
        throw new Error(`The ${label} is unavailable or invalid.`);
    }
    return parsed;
}

export function buildOmrConsoleReviewPayload(
    payload: unknown,
    item: unknown,
    idempotencyKey: string,
): OmrConsoleReviewPayload {
    const draft = record(payload);
    const source = record(item);
    const decision = String(draft.decision ?? '') as OmrConsoleDecision;
    if (
        !['confirm_machine', 'set_option', 'blank', 'unresolved'].includes(
            decision,
        )
    ) {
        throw new Error('Choose a supported item review decision.');
    }
    const option =
        draft.selected_option ??
        draft.selectable_option ??
        draft.final_option ??
        (decision === 'confirm_machine'
            ? (record(source.machine).selected_option ??
              record(source.detected).selected_option ??
              source.option_code ??
              null)
            : null);
    const selectedOption =
        option === null || option === '' ? null : String(option).trim();
    if (
        (decision === 'confirm_machine' || decision === 'set_option') &&
        !selectedOption
    ) {
        throw new Error(
            'Select a pinned answer option before saving this item.',
        );
    }
    const offeredOptions = Array.isArray(source.options)
        ? source.options
              .map((entry) => {
                  const optionRecord = record(entry);
                  return String(
                      typeof entry === 'string' || typeof entry === 'number'
                          ? entry
                          : (optionRecord.code ??
                                optionRecord.option_code ??
                                ''),
                  ).trim();
              })
              .filter(Boolean)
        : [];
    if (
        (decision === 'confirm_machine' || decision === 'set_option') &&
        (offeredOptions.length === 0 ||
            !offeredOptions.includes(selectedOption as string))
    ) {
        throw new Error('Select an answer option pinned to this item.');
    }
    if (
        (decision === 'blank' || decision === 'unresolved') &&
        selectedOption !== null
    ) {
        throw new Error('This item decision cannot include a selected option.');
    }
    const machine = record(source.machine);
    const machineOption =
        machine.selected_option == null
            ? null
            : String(machine.selected_option);
    const machineMarked = machine.state === 'marked' && machineOption !== null;
    const reasonCode =
        draft.reason_code == null
            ? null
            : String(draft.reason_code).trim() || null;
    const note = draft.note == null ? null : String(draft.note).trim() || null;
    if (
        ((decision === 'set_option' ||
            decision === 'unresolved' ||
            (decision === 'blank' && machineMarked)) &&
            reasonCode === null) ||
        (decision === 'set_option' &&
            machineMarked &&
            selectedOption !== machineOption &&
            note === null)
    ) {
        throw new Error(
            decision === 'set_option' && selectedOption !== machineOption
                ? 'Choose a review reason and add a note for a changed answer.'
                : 'Choose a review reason before saving this item.',
        );
    }
    const evidenceHash = String(
        draft.reviewed_machine_evidence_hash ??
            draft.reviewed_evidence_hash ??
            record(source.machine).evidence_hash ??
            record(source.detected).evidence_hash ??
            source.evidence_hash ??
            '',
    )
        .trim()
        .toLowerCase();
    if (!/^[a-f0-9]{64}$/.test(evidenceHash)) {
        throw new Error('The reviewed machine evidence hash is unavailable.');
    }
    const key = String(draft.idempotency_key ?? idempotencyKey ?? '').trim();
    if (!key)
        throw new Error('The item review idempotency key is unavailable.');
    const parentVersion =
        draft.expected_parent_version ??
        source.parent_version ??
        source.version;
    if (
        parentVersion === null ||
        parentVersion === undefined ||
        String(parentVersion).trim() === ''
    ) {
        throw new Error('The item review parent version is unavailable.');
    }
    const itemRevision = requiredInteger(
        draft.expected_item_revision ??
            source.item_revision ??
            record(source.review).revision ??
            0,
        'item review revision',
    );
    return {
        decision,
        selected_option: selectedOption,
        reason_code: reasonCode,
        note,
        expected_parent_version: parentVersion as number | string,
        expected_item_revision: itemRevision,
        reviewed_machine_evidence_hash: evidenceHash,
        idempotency_key: key,
    };
}

export function normalizeAcknowledgedItemReview(
    value: unknown,
): OmrConsoleAcknowledgedReview {
    const root = record(value);
    const review = record(root.review);
    if (root.outcome !== 'succeeded' || Object.keys(review).length === 0) {
        throw new Error('The server did not acknowledge the item review.');
    }
    const revision = requiredInteger(
        review.revision,
        'acknowledged item review revision',
    );
    const decision = String(review.decision ?? '') as OmrConsoleDecision;
    if (
        !['confirm_machine', 'set_option', 'blank', 'unresolved'].includes(
            decision,
        )
    ) {
        throw new Error(
            'The server returned an unsupported item review decision.',
        );
    }
    const evidenceHash = String(review.evidence_hash ?? '').toLowerCase();
    if (!/^[a-f0-9]{64}$/.test(evidenceHash)) {
        throw new Error(
            'The acknowledged item review has no valid evidence hash.',
        );
    }
    const reviewer = review.reviewer == null ? null : record(review.reviewer);
    return {
        revision,
        decision,
        selected_option:
            review.selected_option == null
                ? null
                : String(review.selected_option),
        reason_code:
            review.reason_code == null ? null : String(review.reason_code),
        note: review.note == null ? null : String(review.note),
        reviewer:
            reviewer === null
                ? null
                : {
                      id: (reviewer.id as string | number | null) ?? null,
                      display_name:
                          reviewer.display_name == null
                              ? null
                              : String(reviewer.display_name),
                  },
        reviewed_at:
            review.reviewed_at == null ? null : String(review.reviewed_at),
        evidence_hash: evidenceHash,
        stale: false,
    };
}
export function assertProductionItemReviewAcknowledged(
    value: unknown,
    payload: OmrConsoleReviewPayload,
    parentId: number,
    itemKey: string,
): OmrConsoleAcknowledgedReview {
    const root = record(value);
    const receipt = record(root.receipt);
    const review = normalizeAcknowledgedItemReview(value);
    if (
        Number(receipt.parent_id) !== parentId ||
        String(receipt.item_key ?? '') !== itemKey ||
        String(receipt.evidence_hash ?? '').toLowerCase() !==
            payload.reviewed_machine_evidence_hash ||
        receipt.decision !== payload.decision ||
        (receipt.selected_option ?? null) !== payload.selected_option ||
        Number(receipt.revision) !== review.revision ||
        !Number.isSafeInteger(Number(receipt.parent_version)) ||
        Number(receipt.parent_version) <
            Number(payload.expected_parent_version) ||
        review.decision !== payload.decision ||
        review.selected_option !== payload.selected_option ||
        review.reason_code !== payload.reason_code ||
        (review.note ?? null) !== payload.note ||
        review.evidence_hash !== payload.reviewed_machine_evidence_hash ||
        review.revision <= payload.expected_item_revision
    ) {
        throw new Error(
            'The server acknowledgment is stale or does not match this item decision.',
        );
    }
    return {
        ...review,
        parent_version: Number(receipt.parent_version),
    } as OmrConsoleAcknowledgedReview;
}

export function assertDiagnosticItemReviewAcknowledged(
    previous: unknown,
    next: unknown,
    captureId: number,
    itemId: number,
    payload: OmrConsoleReviewPayload,
): UnknownRecord {
    const before = record(previous);
    const after = record(next);
    if (
        after.execution_kind !== 'diagnostic' ||
        before.execution_kind !== 'diagnostic' ||
        typeof before.workbench_id !== 'string' ||
        after.workbench_id !== before.workbench_id ||
        !before.workbench_id.startsWith('diagnostic:') ||
        record(after.context).run_id !== record(before.context).run_id
    ) {
        throw new Error(
            'The diagnostic review acknowledgment belongs to another run.',
        );
    }
    const outcome = record(record(after.recovery).outcome);
    if (
        Object.keys(outcome).length > 0 &&
        (outcome.kind !== 'success' ||
            outcome.blocked === true ||
            outcome.stale === true ||
            outcome.invalid === true)
    ) {
        throw new Error(
            `The diagnostic review was not acknowledged (${String(outcome.code ?? outcome.kind ?? 'failed')}).`,
        );
    }
    const evidenceItems = record(after.evidence).items;
    const rows = Array.isArray(evidenceItems) ? evidenceItems : [];
    const updated = rows
        .map(record)
        .find(
            (row) =>
                Number(row.id) === itemId &&
                Number(row.capture_id) === captureId,
        );
    const review = record(updated?.review);
    if (
        !updated ||
        Number(review.revision) <= payload.expected_item_revision ||
        review.decision !== payload.decision ||
        (review.selected_option ?? null) !== payload.selected_option ||
        String(review.evidence_hash ?? '').toLowerCase() !==
            payload.reviewed_machine_evidence_hash ||
        review.reason_code !== payload.reason_code ||
        (review.note ?? null) !== payload.note
    ) {
        throw new Error(
            'The diagnostic projection did not acknowledge this item decision.',
        );
    }
    return review;
}

export function productionItemReviewUrl(
    sessionId: number,
    sheetId: number,
    itemId: string | number,
): string {
    if (
        ![sessionId, sheetId].every(
            (value) => Number.isSafeInteger(value) && value > 0,
        ) ||
        !String(itemId).trim()
    ) {
        throw new Error('The production item review context is unavailable.');
    }
    return `/scoring/sessions/${encodeURIComponent(String(sessionId))}/omr/sheets/${encodeURIComponent(String(sheetId))}/items/${encodeURIComponent(String(itemId))}/review`;
}
export function productionItemReviewKey(item: unknown): string {
    const source = record(item);
    const areaCode = String(source.area_code ?? '').trim();
    const questionNo = Number(source.question_no);
    if (areaCode !== '' && Number.isSafeInteger(questionNo) && questionNo > 0) {
        return `${areaCode.toUpperCase()}_Q_${questionNo}`;
    }
    const key = String(
        source.item_key ??
            source.item_id ??
            source.native_label ??
            source.label ??
            '',
    ).trim();
    if (!key) throw new Error('The production item key is unavailable.');
    return key;
}
