<script>
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { Textarea } from '@/Components/ui/textarea';
    import * as Dialog from '@/Components/ui/dialog';
    import DocumentRequirementCard from '@/Components/Admission/DocumentRequirementCard.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import EntityRowActions from '@/Components/kit/EntityRowActions.svelte';
    import ReasonSuggestionChips from '@/Components/kit/ReasonSuggestionChips.svelte';
    import { router, usePage } from '@inertiajs/svelte';
    import { formatDateTime } from '@/lib/date-utils.js';
    import {
        DOCUMENT_INTERNAL_NOTE_SUGGESTIONS,
        DOCUMENT_OVERALL_REQUEST_NOTE_SUGGESTIONS,
    } from '@/lib/reason-suggestions.js';
    import { newIdempotencyKey } from '@/lib/utils';
    import {
        AlertCircle,
        CheckCircle2,
        FileText,
        HelpCircle,
        RotateCcw,
        Send,
        XCircle,
    } from 'lucide-svelte';

    let {
        attemptId,
        checklist = null,
        requestHistory = [],
        expectedVersion = 0,
        flags = null,
        mutationBlockReason = null,
        isClearanceConfirmed = false,
        verifyUrl = null,
        bulkVerifyUrl = null,
        requestUrl = null,
        internalNoteUrl = null,
        loading = false,
        readOnly = false,
        columns = 1,
    } = $props();
    const page = usePage();

    const permissions = $derived(
        flags ??
            checklist?.flags ?? {
                documents_view: checklist?.documents_view,
                documents_verify: checklist?.documents_verify,
                documents_request: checklist?.documents_request,
            },
    );
    const blockReason = $derived(
        mutationBlockReason ?? checklist?.mutation_block_reason,
    );
    const verifyEndpoint = $derived(
        verifyUrl ??
            (attemptId
                ? `/registrar/enrollment-eligibility/${attemptId}/documents/verify`
                : null),
    );
    const bulkVerifyEndpoint = $derived(
        bulkVerifyUrl ??
            (verifyEndpoint
                ? verifyEndpoint.replace(/\/verify$/, '/bulk-verify')
                : null) ??
            (attemptId
                ? `/registrar/enrollment-eligibility/${attemptId}/documents/bulk-verify`
                : null),
    );
    const requestEndpoint = $derived(
        requestUrl ??
            (attemptId
                ? `/admissions/${attemptId}/document-correction-requests`
                : null),
    );
    function internalNoteUrlFor(item) {
        const requirementItemId =
            item.requirement_item_id ?? (setInfo ? item.id : null);
        if (typeof internalNoteUrl === 'function') {
            return internalNoteUrl(item);
        }
        if (internalNoteUrl) {
            return requirementItemId
                ? internalNoteUrl.replace(
                      '{requirementItem}',
                      requirementItemId,
                  )
                : `/registrar/enrollment-eligibility/${attemptId}/requirements/internal-notes`;
        }
        if (!attemptId) return null;
        return requirementItemId
            ? `/registrar/enrollment-eligibility/${attemptId}/requirements/${requirementItemId}/internal-notes`
            : `/registrar/enrollment-eligibility/${attemptId}/requirements/internal-notes`;
    }
    let isSubmitting = $state(false);
    let composeOpen = $state(false);
    let statusFilter = $state('all');
    let activeEditorMode = $state('deficient');
    let activeItemId = $state(null);
    let draft = $state({});
    let draftBaseEvidence = $state({});
    let draftDirty = $state({});
    let selected = $state({});
    let selectedEvidence = $state({});
    let overallNote = $state('');
    let reviewOpen = $state(false);
    let fieldErrors = $state({});
    let conflictMessage = $state('');
    let conflictVersion = null;
    let retryOperation = $state(null);
    let uncertainOperation = $state(null);
    let deliveryNotice = $state('');
    let reviewOperation = $state(null);
    let reviewedItems = $state([]);
    let reviewedNote = $state(null);
    let draftIdentity = $state(attemptId);
    let cardSelected = $state({});
    let bulkVerifyDialogOpen = $state(false);
    let selectAllCheckbox = $state(null);

    const cardSelectedItems = $derived(
        items.filter((item) => cardSelected[item.id]),
    );
    const verifiableSelected = $derived(
        cardSelectedItems.filter((item) => item.latest_status !== 'verified'),
    );
    const reissuableSelected = $derived(
        cardSelectedItems.filter((item) => eligible(item)),
    );

    const allFilteredSelected = $derived(
        filteredViewItems.length > 0 &&
            filteredViewItems.every((item) => cardSelected[item.id]),
    );
    const someFilteredSelected = $derived(
        filteredViewItems.some((item) => cardSelected[item.id]),
    );
    const allPendingSelected = $derived(
        pendingViewItems.length > 0 &&
            pendingViewItems.every((item) => cardSelected[item.id]),
    );

    $effect(() => {
        if (selectAllCheckbox) {
            selectAllCheckbox.indeterminate =
                someFilteredSelected && !allFilteredSelected;
        }
    });

    function toggleCardSelection(itemId, checked) {
        cardSelected = { ...cardSelected, [itemId]: checked };
    }

    function toggleSelectAllFiltered(event) {
        const checked = event.currentTarget.checked;
        const next = { ...cardSelected };
        for (const item of filteredViewItems) {
            next[item.id] = checked;
        }
        cardSelected = next;
    }

    function selectAllPending() {
        const next = { ...cardSelected };
        for (const item of pendingViewItems) {
            next[item.id] = true;
        }
        cardSelected = next;
    }

    function openBulkReissueRequest() {
        for (const item of reissuableSelected) {
            toggleRequest(item, true);
        }
        openComposer();
    }

    function submitBulkVerify() {
        if (
            !bulkVerifyEndpoint ||
            !canVerify ||
            isSubmitting ||
            verifiableSelected.length === 0
        )
            return;
        const payloadItems = verifiableSelected.map((item) => ({
            requirement_item_id:
                item.requirement_item_id ?? (setInfo ? item.id : null),
            intake_requirement_id:
                item.intake_requirement_id ?? (!setInfo ? item.id : null),
            expected_latest_verification_id: currentId(item),
            internal_remarks: item.internal_remarks ?? null,
        }));
        const content = {
            expected_version: expectedVersion,
            requirement_set_id: setInfo?.id ?? null,
            items: payloadItems,
        };
        const signature = `bulk-verify:${JSON.stringify(content)}`;
        post(
            bulkVerifyEndpoint,
            {
                ...content,
                idempotency_key: idempotencyKeyFor(signature),
            },
            () => {
                bulkVerifyDialogOpen = false;
                cardSelected = {};
            },
        );
    }

    const items = $derived(checklist?.items ?? []);
    const setInfo = $derived(checklist?.set ?? null);
    const viewItems = $derived(
        items.map((item) => ({
            ...item,
            internal_remarks: readOnly ? null : item.internal_remarks,
            history: readOnly ? undefined : item.history,
        })),
    );
    const pendingViewItems = $derived(
        viewItems.filter((item) => item.latest_status !== 'verified'),
    );
    const completeViewItems = $derived(
        viewItems.filter((item) => item.latest_status === 'verified'),
    );
    const filteredViewItems = $derived(
        statusFilter === 'pending'
            ? pendingViewItems
            : statusFilter === 'complete'
              ? completeViewItems
              : viewItems,
    );
    const normalizedRequests = $derived(
        Array.isArray(requestHistory)
            ? requestHistory
            : Array.isArray(requestHistory?.requests)
              ? requestHistory.requests
              : [],
    );
    const requestStateByItemId = $derived.by(() => {
        const map = {};
        for (const req of normalizedRequests) {
            for (const reqItem of req.items ?? []) {
                const nextStatus = reqItem.status ?? 'current';
                const existing = map[reqItem.requirement_item_id];
                if (
                    existing?.itemStatus === 'current' &&
                    nextStatus !== 'current'
                ) {
                    continue;
                }
                map[reqItem.requirement_item_id] = {
                    requestId: req.id,
                    recordedAt: req.recorded_at,
                    requestStatus: req.status,
                    itemStatus: nextStatus,
                    sentInstruction: reqItem.applicant_instruction,
                    observedStatus: reqItem.observed_status ?? reqItem.status,
                };
            }
        }
        return map;
    });
    const canView = $derived(readOnly || permissions.documents_view !== false);
    const canVerify = $derived(
        !readOnly &&
            permissions.documents_verify === true &&
            !isClearanceConfirmed,
    );
    const canRequest = $derived(
        !readOnly &&
            permissions.documents_request === true &&
            !isClearanceConfirmed,
    );
    const pendingCount = $derived(checklist?.mandatory_pending_count ?? 0);
    const eligibleItems = $derived(items.filter((item) => eligible(item)));
    const requestedEligibleCount = $derived(
        eligibleItems.filter(
            (item) => requestStateByItemId[item.id]?.itemStatus === 'current',
        ).length,
    );
    const unsentEligibleCount = $derived(
        eligibleItems.length - requestedEligibleCount,
    );
    const selectedItems = $derived(
        items.filter(
            (item) =>
                selected[item.id] &&
                eligible(item) &&
                selectedEvidence[item.id] === evidenceKey(item),
        ),
    );

    function evidenceKey(item) {
        return `${expectedVersion}:${setInfo?.id ?? 'catalog'}:${currentId(item)}:${item.latest_status}:${item.latest_note_id ?? 'none'}`;
    }
    function badge(status) {
        if (status === 'verified')
            return {
                label: 'Verified',
                icon: CheckCircle2,
                style: 'text-emerald-700 border-emerald-300 bg-emerald-50 dark:text-emerald-300 dark:border-emerald-800 dark:bg-emerald-950/40',
            };
        if (status === 'deficient')
            return {
                label: 'Deficient',
                icon: AlertCircle,
                style: 'text-amber-700 border-amber-300 bg-amber-50 dark:text-amber-300 dark:border-amber-800 dark:bg-amber-950/40',
            };
        if (status === 'missing')
            return {
                label: 'Missing',
                icon: XCircle,
                style: 'text-rose-700 border-rose-300 bg-rose-50 dark:text-rose-300 dark:border-rose-800 dark:bg-rose-950/40',
            };
        return {
            label: 'Not yet checked',
            icon: HelpCircle,
            style: 'text-muted-foreground border-border bg-muted/30',
        };
    }
    const availableSuggestions = $derived(
        (checklist?.suggestions ?? []).map((suggestion) => ({
            code: suggestion.code,
            label: suggestion.label,
            template: suggestion.template ?? suggestion.text ?? '',
        })),
    );

    let operationKeys = $state({});

    function idempotencyKeyFor(signature) {
        operationKeys[signature] ??= newIdempotencyKey();
        return operationKeys[signature];
    }
    function currentId(item) {
        return (
            item.latest_verification_id ?? item.latest_verification?.id ?? null
        );
    }
    const reviewIsStale = $derived(
        Boolean(
            reviewOperation &&
            uncertainOperation?.payload.idempotency_key !==
                reviewOperation.payload.idempotency_key &&
            (expectedVersion !== reviewOperation.payload.expected_version ||
                setInfo?.id !== reviewOperation.payload.requirement_set_id ||
                reviewedItems.some((reviewed) => {
                    const current = items.find(
                        (item) => item.id === reviewed.requirement_item_id,
                    );
                    return (
                        !current ||
                        currentId(current) !==
                            reviewed.expected_latest_verification_id ||
                        current.latest_status !== reviewed.observed_status
                    );
                })),
        ),
    );

    $effect(() => {
        const identity = attemptId;
        const currentItems = items;
        const currentVersion = expectedVersion;
        const currentSetId = setInfo?.id ?? 'catalog';

        if (draftIdentity !== identity) {
            draftIdentity = identity;
            draft = {};
            draftBaseEvidence = {};
            draftDirty = {};
            selected = {};
            selectedEvidence = {};
            cardSelected = {};
            bulkVerifyDialogOpen = false;
            operationKeys = {};
            overallNote = '';
            fieldErrors = {};
            conflictMessage = '';
            retryOperation = null;
            uncertainOperation = null;
            reviewedItems = [];
            reviewedNote = null;
            reviewOpen = false;
            activeItemId = null;
            composeOpen = false;
            statusFilter = 'all';
            activeEditorMode = 'deficient';
            deliveryNotice = '';
        }

        const nextDraft = { ...draft };
        const nextBase = { ...draftBaseEvidence };
        let changed = false;
        for (const item of currentItems) {
            const evidence = `${currentVersion}:${currentSetId}:${currentId(item)}:${item.latest_status}:${item.latest_note_id ?? 'none'}`;
            if (nextDraft[item.id] === undefined || !draftDirty[item.id]) {
                const refreshed = {
                    applicant_instruction: item.applicant_instruction ?? '',
                    internal_remarks:
                        item.internal_note?.body ?? item.internal_remarks ?? '',
                };
                if (
                    JSON.stringify(nextDraft[item.id]) !==
                        JSON.stringify(refreshed) ||
                    nextBase[item.id] !== evidence
                ) {
                    nextDraft[item.id] = refreshed;
                    nextBase[item.id] = evidence;
                    changed = true;
                }
            }
        }
        if (changed) {
            draft = nextDraft;
            draftBaseEvidence = nextBase;
        }
    });

    function itemDraft(item) {
        return (
            draft[item.id] ?? {
                applicant_instruction: item.applicant_instruction ?? '',
                internal_remarks:
                    item.internal_note?.body ?? item.internal_remarks ?? '',
            }
        );
    }
    function ensureItemDraft(item) {
        if (draft[item.id] === undefined) {
            draft = {
                ...draft,
                [item.id]: {
                    applicant_instruction: item.applicant_instruction ?? '',
                    internal_remarks:
                        item.internal_note?.body ?? item.internal_remarks ?? '',
                },
            };
            draftBaseEvidence = {
                ...draftBaseEvidence,
                [item.id]: evidenceKey(item),
            };
            draftDirty = { ...draftDirty, [item.id]: false };
        }
        return draft[item.id];
    }
    function updateDraftField(item, field, value) {
        const values = ensureItemDraft(item);
        draft = {
            ...draft,
            [item.id]: { ...values, [field]: value },
        };
        draftDirty = { ...draftDirty, [item.id]: true };
    }
    function draftNeedsReview(item) {
        return Boolean(
            draftDirty[item.id] &&
            draftBaseEvidence[item.id] !== evidenceKey(item),
        );
    }
    function openItemEditor(item, mode) {
        activeItemId = item.id;
        activeEditorMode = mode;
        const values = ensureItemDraft(item);
        if (mode === 'note' && !draftDirty[item.id]) {
            draft = {
                ...draft,
                [item.id]: {
                    ...values,
                    internal_remarks:
                        item.internal_note?.body ??
                        item.internal_remarks ??
                        '',
                },
            };
        }
    }

    function closeItemEditor() {
        activeItemId = null;
        activeEditorMode = 'deficient';
    }

    function markComplete(item) {
        if (!verifyEndpoint || !canVerify || isSubmitting) return;
        const content = {
            expected_version: expectedVersion,
            requirement_set_id: setInfo?.id ?? null,
            requirement_item_id:
                item.requirement_item_id ?? (setInfo ? item.id : null),
            intake_requirement_id:
                item.intake_requirement_id ?? (!setInfo ? item.id : null),
            expected_latest_verification_id: currentId(item),
            status: 'verified',
            applicant_instruction: null,
            internal_remarks: item.internal_remarks ?? null,
        };
        post(
            verifyEndpoint,
            {
                ...content,
                idempotency_key: idempotencyKeyFor(
                    `verify:${JSON.stringify(content)}`,
                ),
            },
            () => {
                if (activeItemId === item.id) closeItemEditor();
                draftDirty = { ...draftDirty, [item.id]: false };
            },
        );
    }

    function saveInternalNote(item) {
        if (!canVerify || isSubmitting) return;
        if (draftNeedsReview(item)) {
            conflictMessage =
                'This draft is based on older checklist evidence. Review the updated evidence before saving.';
            return;
        }
        const values = ensureItemDraft(item);
        const requirementItemId =
            item.requirement_item_id ?? (setInfo ? item.id : null);
        const content = {
            expected_version: expectedVersion,
            requirement_set_id: setInfo?.id ?? null,
            requirement_item_id: requirementItemId,
            intake_requirement_id:
                item.intake_requirement_id ?? (!setInfo ? item.id : null),
            expected_latest_verification_id: currentId(item),
            expected_latest_note_id:
                item.latest_note_id ?? item.internal_note?.id ?? null,
            internal_remarks: values.internal_remarks.trim() || null,
        };
        const url = requirementItemId
            ? `/registrar/enrollment-eligibility/${attemptId}/requirements/${item.requirement_item_id ?? item.id}/internal-notes`
            : `/registrar/enrollment-eligibility/${attemptId}/requirements/internal-notes`;
        post(
            internalNoteUrlFor(item) ?? url,
            {
                ...content,
                idempotency_key: idempotencyKeyFor(
                    `note:${JSON.stringify(content)}`,
                ),
            },
            () => {
                closeItemEditor();
                retryOperation = null;
                draftDirty = { ...draftDirty, [item.id]: false };
            },
        );
    }
    function reviewDraftEvidence(item) {
        draftBaseEvidence = {
            ...draftBaseEvidence,
            [item.id]: evidenceKey(item),
        };
        selectedEvidence = selected[item.id]
            ? { ...selectedEvidence, [item.id]: evidenceKey(item) }
            : selectedEvidence;
        conflictMessage = '';
    }
    function instructionError(item) {
        const itemError = fieldErrors[`items.${item.id}.applicant_instruction`];
        if (itemError) return itemError;
        const orderedSelected = [...selectedItems].sort(
            (left, right) => left.id - right.id,
        );
        const index = orderedSelected.findIndex(
            (selectedItem) => selectedItem.id === item.id,
        );
        return index >= 0
            ? fieldErrors[`items.${index}.applicant_instruction`]
            : fieldErrors.applicant_instruction;
    }
    function otherItemErrors(item) {
        const prefix = `items.${item.id}.`;
        return Object.entries(fieldErrors)
            .filter(
                ([key]) =>
                    key.startsWith(prefix) &&
                    !/\.(applicant_instruction|internal_remarks)$/.test(key),
            )
            .map(([, message]) => message);
    }
    const adapterErrorKeys = ['document_request', 'checklist', 'verification'];
    function adapterErrors() {
        return adapterErrorKeys.flatMap((key) => {
            const message = fieldErrors[key];
            return message
                ? [
                      {
                          key,
                          message: Array.isArray(message)
                              ? message.join(' ')
                              : message,
                      },
                  ]
                : [];
        });
    }
    function requestWasDefinitelyNotRecorded(errors) {
        const keys = Object.keys(errors ?? {});
        const fieldErrors = keys.some(
            (key) =>
                [
                    'expected_version',
                    'requirement_set_id',
                    'items',
                    'overall_note',
                    'idempotency_key',
                ].includes(key) || /^items\.\d+(\.|$)/.test(key),
        );
        const rejectionOutcome =
            page.props.flash?.document_correction_request_outcome;
        return (
            fieldErrors ||
            (Boolean(errors?.document_request) &&
                rejectionOutcome === 'definite_rejection')
        );
    }

    function post(
        url,
        payload,
        onSuccess,
        releaseOnDefinitiveRequestRejection = false,
    ) {
        if (!url || isSubmitting) return;
        isSubmitting = true;
        fieldErrors = {};
        conflictMessage = '';
        router.post(url, payload, {
            preserveScroll: true,
            onError: (errors) => {
                fieldErrors = errors ?? {};
                if (
                    releaseOnDefinitiveRequestRejection &&
                    requestWasDefinitelyNotRecorded(fieldErrors)
                ) {
                    uncertainOperation = null;
                    retryOperation = null;
                    reviewOperation = null;
                    reviewOpen = false;
                }
                if (
                    Object.keys(fieldErrors).some(
                        (key) =>
                            /version|evidence|conflict|stale/i.test(key) ||
                            adapterErrorKeys.includes(key),
                    )
                ) {
                    conflictMessage =
                        'The checklist changed or the request could not be recorded. Your entries are kept; refresh and review the current evidence before retrying.';
                    conflictVersion = expectedVersion;
                }
                queueMicrotask(() => {
                    const modalError = reviewOpen
                        ? document.querySelector(
                              '[role="dialog"] [role="alert"][tabindex="-1"]',
                          )
                        : null;
                    const invalidField = document.querySelector(
                        '[aria-invalid="true"]',
                    );
                    (modalError ?? invalidField)?.focus();
                });
            },
            onSuccess,
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }


    function saveFinding(item, status) {
        if (!verifyEndpoint || !canVerify || isSubmitting) return;
        if (draftNeedsReview(item)) {
            conflictMessage =
                'This draft is based on older checklist evidence. Review the updated evidence before saving.';
            return;
        }
        const values = ensureItemDraft(item);
        if (status === 'deficient' && !values.applicant_instruction.trim()) {
            fieldErrors = {
                [`items.${item.id}.applicant_instruction`]:
                    'Add an applicant instruction before saving a deficient finding.',
            };
            activeItemId = item.id;
            queueMicrotask(() =>
                document.getElementById(`instruction-${item.id}`)?.focus(),
            );
            return;
        }
        const content = {
            expected_version: expectedVersion,
            requirement_set_id: setInfo?.id ?? null,
            requirement_item_id:
                item.requirement_item_id ?? (setInfo ? item.id : null),
            intake_requirement_id:
                item.intake_requirement_id ?? (!setInfo ? item.id : null),
            expected_latest_verification_id: currentId(item),
            status,
            applicant_instruction:
                status === 'deficient'
                    ? values.applicant_instruction.trim()
                    : values.applicant_instruction.trim() || null,
            internal_remarks: values.internal_remarks.trim() || null,
        };
        const signature = `verify:${JSON.stringify(content)}`;
        const payload = {
            ...content,
            idempotency_key: idempotencyKeyFor(signature),
        };
        post(verifyEndpoint, payload, () => {
            closeItemEditor();
            retryOperation = null;
            draftDirty = { ...draftDirty, [item.id]: false };
        });
    }

    function eligible(item) {
        return ['missing', 'deficient'].includes(item.latest_status);
    }

    function toggleRequest(item, checked) {
        if (!eligible(item)) return;
        const values = ensureItemDraft(item);
        if (draftNeedsReview(item)) {
            conflictMessage =
                'This draft is based on older checklist evidence. Review the updated evidence before including it in a request.';
            return;
        }
        if (
            checked &&
            item.latest_status === 'missing' &&
            !values.applicant_instruction.trim()
        ) {
            updateDraftField(
                item,
                'applicant_instruction',
                `Please present ${item.label} to the Registrar for inspection.`,
            );
        }
        selected = { ...selected, [item.id]: checked };
        selectedEvidence = {
            ...selectedEvidence,
            [item.id]: checked ? evidenceKey(item) : null,
        };
    }

    function freezeRequestPayload() {
        if (!setInfo || selectedItems.length === 0) return null;
        const itemsPayload = selectedItems
            .map((item) => ({
                requirement_item_id: item.id,
                expected_latest_verification_id: currentId(item),
                applicant_instruction:
                    itemDraft(item).applicant_instruction.trim() ||
                    (item.latest_status === 'missing'
                        ? `Please present ${item.label} to the Registrar for inspection.`
                        : ''),
            }))
            .sort((a, b) => a.requirement_item_id - b.requirement_item_id);
        if (itemsPayload.some((item) => !item.applicant_instruction)) {
            fieldErrors = {
                request:
                    'Add an applicant instruction for every selected item.',
            };
            return null;
        }
        const content = {
            expected_version: expectedVersion,
            requirement_set_id: setInfo.id,
            items: itemsPayload,
            overall_note: overallNote.trim() || null,
        };
        const intentSignature = JSON.stringify({
            requirement_set_id: content.requirement_set_id,
            items: content.items,
            overall_note: content.overall_note,
        });
        if (uncertainOperation?.intentSignature === intentSignature)
            return uncertainOperation;
        retryOperation = {
            intentSignature,
            submitted: false,
            payload: { ...content, idempotency_key: newIdempotencyKey() },
        };
        return retryOperation;
    }

    function refreshChecklist() {
        router.reload({ preserveScroll: true });
        conflictMessage = '';
        conflictVersion = null;
        fieldErrors = {};
    }
    function openReview() {
        if (conflictMessage && conflictVersion !== expectedVersion) {
            conflictMessage = '';
            conflictVersion = null;
        }
        fieldErrors = {};
        const frozen = freezeRequestPayload();
        if (!frozen) return;
        reviewOperation = frozen;
        reviewedItems = frozen.payload.items.map((requestItem) => {
            const current = items.find(
                (item) => item.id === requestItem.requirement_item_id,
            );
            return {
                ...requestItem,
                label: current?.label ?? 'Document',
                observed_status: current?.latest_status ?? 'unknown',
            };
        });
        reviewedNote = frozen.payload.overall_note;
        reviewOpen = true;
    }

    function openComposer() {
        fieldErrors = {};
        composeOpen = true;
        reviewOpen = false;
    }

    function reopenUncertainReview() {
        if (!uncertainOperation) return;
        reviewOperation = uncertainOperation;
        reviewedItems = uncertainOperation.reviewedItems ?? [];
        reviewedNote = uncertainOperation.payload.overall_note;
        composeOpen = true;
        reviewOpen = true;
    }

    function sendRequest() {
        if (!reviewOperation || reviewIsStale) {
            conflictMessage =
                'The reviewed checklist evidence is stale. Cancel and review the current items again before sending.';
            return;
        }
        if (
            uncertainOperation &&
            reviewOperation.payload.idempotency_key !==
                uncertainOperation.payload.idempotency_key
        ) {
            conflictMessage =
                'Retry or resolve the original uncertain request before sending a different request.';
            return;
        }
        const sentOperation = {
            ...reviewOperation,
            reviewedItems: reviewedItems.map((item) => ({ ...item })),
        };
        uncertainOperation = { ...sentOperation, submitted: true };
        reviewOperation = uncertainOperation;
        retryOperation = uncertainOperation;
        post(
            requestEndpoint,
            uncertainOperation.payload,
            () => {
                reviewOpen = false;
                composeOpen = false;
                selected = {};
                retryOperation = null;
                uncertainOperation = null;
                reviewOperation = null;
                deliveryNotice =
                    page.props.flash?.document_correction_request_delivery ===
                    'failed'
                        ? 'Request recorded, but notification delivery could not be queued.'
                        : 'Request recorded. This does not confirm notification delivery.';
            },
            true,
        );
    }
</script>

{#if canView}
    <Card.Root class="border-border/80 min-w-0 overflow-hidden shadow-sm">
        <Card.Header class="bg-muted/20 border-b pb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <FileText class="text-primary size-4" /><Card.Title
                        class="text-base"
                        >Document checklist</Card.Title
                    >
                </div>
                {#if setInfo}<Badge
                        variant={checklist?.all_mandatory_verified
                            ? 'default'
                            : 'secondary'}
                        >{checklist?.all_mandatory_verified
                            ? 'All mandatory verified'
                            : `${pendingCount} mandatory pending`}</Badge
                    >{/if}
            </div>
            {#if setInfo}<Card.Description class="text-xs"
                    >Track: <span class="text-foreground font-medium capitalize"
                        >{setInfo.applicant_track}</span
                    >
                    · Catalog v{setInfo.catalog_version}</Card.Description
                >{/if}
            {#if canRequest && eligibleItems.length > 0}
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" class="min-h-11" disabled={isSubmitting} onclick={openComposer}>
                        Request document correction
                    </Button>
                    {#if unsentEligibleCount > 0}
                        <Badge variant="outline" class="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                            {unsentEligibleCount} unsent
                        </Badge>
                    {/if}
                    {#if requestedEligibleCount > 0}
                        <Badge variant="outline" class="border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300">
                            {requestedEligibleCount} correction requested
                        </Badge>
                    {/if}
                </div>
            {/if}
        </Card.Header>
        <Card.Content class="min-w-0 space-y-4 pt-4">
            {#if loading}
                <p
                    role="status"
                    class="bg-muted/50 text-muted-foreground animate-pulse rounded-md p-4 text-sm"
                >
                    Loading document checklist…
                </p>
            {:else}
                {#if conflictMessage}<div class="space-y-2">
                        <p
                            role="alert"
                            class="rounded-md border border-amber-400 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                        >
                            {conflictMessage}
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11"
                            disabled={isSubmitting}
                            onclick={refreshChecklist}>Refresh checklist</Button
                        >
                    </div>{/if}
                {#if uncertainOperation}<Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        onclick={reopenUncertainReview}
                        >Review exact retry of original request</Button
                    >{/if}
                {#each adapterErrors() as error (`error-${error.key}`)}<p
                        role="alert"
                        class="border-destructive/40 bg-destructive/5 text-destructive rounded-md border p-3 text-sm"
                    >
                        {error.message}
                    </p>{/each}
                {#if blockReason}<p
                        role="note"
                        class="border-border bg-muted/40 text-muted-foreground rounded-md border p-3 text-sm"
                    >
                        {blockReason}
                    </p>{/if}
                {#if checklist?.status === 'unknown_track'}<p
                        role="status"
                        class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        The applicant classification has no mapped requirement
                        track. Checklist readiness cannot be confirmed.
                    </p>{/if}
                {#if checklist?.status === 'stale'}<p
                        role="status"
                        class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        This requirement snapshot no longer matches the current
                        applicant classification. Refresh and review the current
                        checklist.
                    </p>{/if}
                {#if items.length === 0 && checklist?.status !== 'unknown_track'}
                    <div class="rounded-md border border-dashed p-5 text-center">
                        <p class="text-sm font-medium">
                            No active document requirements configured for this applicant track.
                        </p>
                    </div>
                {:else if items.length > 0}
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div
                            role="group"
                            aria-label="Filter checklist items by status"
                            data-testid="checklist-status-filter"
                            class="bg-muted/50 inline-flex flex-wrap items-center gap-1 rounded-lg border p-1"
                        >
                            <button
                                type="button"
                                aria-pressed={statusFilter === 'all'}
                                onclick={() => (statusFilter = 'all')}
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors {statusFilter === 'all' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                            >
                                All ({viewItems.length})
                            </button>
                            <button
                                type="button"
                                aria-pressed={statusFilter === 'pending'}
                                onclick={() => (statusFilter = 'pending')}
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors {statusFilter === 'pending' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                            >
                                Pending ({pendingViewItems.length})
                            </button>
                            <button
                                type="button"
                                aria-pressed={statusFilter === 'complete'}
                                onclick={() => (statusFilter = 'complete')}
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors {statusFilter === 'complete' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                            >
                                Complete ({completeViewItems.length})
                            </button>
                        </div>
                        {#if (canVerify || canRequest) && !isClearanceConfirmed && items.length > 0}
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-1.5 text-xs font-medium cursor-pointer text-muted-foreground hover:text-foreground select-none">
                                    <input
                                        type="checkbox"
                                        bind:this={selectAllCheckbox}
                                        checked={allFilteredSelected}
                                        onchange={toggleSelectAllFiltered}
                                        class="accent-primary size-4 rounded"
                                        aria-label="Select all displayed requirements"
                                    />
                                    <span>Select all</span>
                                </label>
                                {#if pendingViewItems.length > 0 && !allPendingSelected}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                        onclick={selectAllPending}
                                    >
                                        Select pending ({pendingViewItems.length})
                                    </Button>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    {#if cardSelectedItems.length > 0}
                        <BulkActionBar
                            count={cardSelectedItems.length}
                            hint={cardSelectedItems.length === 1 ? '1 requirement selected' : `${cardSelectedItems.length} requirements selected`}
                            onClear={() => (cardSelected = {})}
                        >
                            {#snippet actions()}
                                {#if canVerify && verifiableSelected.length > 0}
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="min-h-9 gap-1.5 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                                        disabled={isSubmitting}
                                        onclick={() => (bulkVerifyDialogOpen = true)}
                                    >
                                        <CheckCircle2 class="size-3.5" />
                                        Accept / Verify ({verifiableSelected.length})
                                    </Button>
                                {/if}
                                {#if canRequest && reissuableSelected.length > 0}
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="min-h-9 gap-1.5 text-xs font-semibold border-amber-500/30 text-amber-900 hover:bg-amber-50 dark:text-amber-200 dark:hover:bg-amber-950/40"
                                        disabled={isSubmitting}
                                        onclick={openBulkReissueRequest}
                                    >
                                        <RotateCcw class="size-3.5 text-amber-600 dark:text-amber-400" />
                                        Request Reissue ({reissuableSelected.length})
                                    </Button>
                                {/if}
                            {/snippet}
                        </BulkActionBar>
                    {/if}
                    {#if filteredViewItems.length === 0}
                        <div class="rounded-md border border-dashed p-4 text-center">
                            <p class="text-muted-foreground text-sm">
                                {statusFilter === 'pending'
                                    ? 'No pending checklist items.'
                                    : 'No completed checklist items yet.'}
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="mt-2"
                                onclick={() => (statusFilter = 'all')}
                            >
                                Show all items
                            </Button>
                        </div>
                    {:else}
                        <ul class={columns === 2 ? 'grid grid-cols-1 gap-3.5 md:grid-cols-2' : 'space-y-2.5'}>
                            {#each filteredViewItems as item (item.id)}
                                {@const state = badge(item.latest_status)}
                                {@const Icon = state.icon}
                                {@const values = itemDraft(item)}
                                {@const committedNote = item.internal_note?.body ?? item.internal_remarks}
                                {@const itemErrors = otherItemErrors(item)}
                                {@const reqState = requestStateByItemId[item.id]}
                                {@const hasCardContent = Boolean(
                                    item.applicant_instruction ||
                                        (!readOnly && committedNote) ||
                                        (reqState &&
                                            (reqState.itemStatus === 'current' ||
                                                (reqState.itemStatus === 'stale' &&
                                                    eligible(item)))) ||
                                        (canVerify && activeItemId === item.id) ||
                                        (canVerify &&
                                            activeItemId !== item.id &&
                                            draftNeedsReview(item)) ||
                                        itemErrors.length > 0,
                                )}
                                <li class={columns === 2 ? 'min-w-0 flex flex-col' : 'min-w-0'}>
                                    <DocumentRequirementCard
                                        id={`checklist-item-${item.id}`}
                                        label={item.label}
                                        itemKind={item.item_kind ?? 'document'}
                                        isMandatory={item.is_mandatory}
                                        density="compact"
                                        class={columns === 2 ? 'h-full flex-1' : ''}
                                    >
                                        {#snippet selectable()}
                                            {#if (canVerify || canRequest) && !isClearanceConfirmed}
                                                <input
                                                    type="checkbox"
                                                    checked={Boolean(cardSelected[item.id])}
                                                    onchange={(event) => toggleCardSelection(item.id, event.currentTarget.checked)}
                                                    aria-label={`Select ${item.label}`}
                                                    class="accent-primary size-4 rounded cursor-pointer"
                                                />
                                            {/if}
                                        {/snippet}
                                        {#snippet status()}
                                            <div class="flex flex-wrap items-center justify-end gap-1.5">
                                                {#if eligible(item) && reqState?.itemStatus === 'current'}
                                                    <Badge
                                                        variant="outline"
                                                        class="border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300"
                                                    >
                                                        Correction requested
                                                    </Badge>
                                                {:else if eligible(item) && reqState?.itemStatus === 'stale'}
                                                    <Badge
                                                        variant="outline"
                                                        class="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300"
                                                    >
                                                        Updated since last request
                                                    </Badge>
                                                {/if}
                                                <Badge
                                                    variant="outline"
                                                    class={`min-h-7 w-fit shrink-0 gap-1 ${state.style}`}
                                                >
                                                    <Icon class="size-3.5" />{state.label}
                                                </Badge>
                                            </div>
                                        {/snippet}
                                        {#snippet content()}
                                            {#if hasCardContent}
                                                <div class="grid min-w-0 gap-2.5 empty:hidden">
                                                    {#if item.applicant_instruction}
                                                        <p class="rounded border border-blue-200 bg-blue-50 p-2 text-sm text-blue-950 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
                                                            <span class="font-medium">Applicant instruction:</span>
                                                            {item.applicant_instruction}
                                                        </p>
                                                    {/if}
                                                    {#if eligible(item) && reqState?.itemStatus === 'current'}
                                                        <p class="rounded border border-blue-200/80 bg-blue-50/60 px-2.5 py-1.5 text-xs text-blue-900 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300">
                                                            {#if reqState.sentInstruction && reqState.sentInstruction !== item.applicant_instruction}
                                                                Sent in correction request: {reqState.sentInstruction}
                                                            {:else}
                                                                {reqState.recordedAt ? `Correction request sent ${formatDateTime(reqState.recordedAt)}` : 'Correction request sent'}
                                                            {/if}
                                                        </p>
                                                    {/if}
                                                    {#if !readOnly && committedNote}
                                                        <p class="border-primary/50 bg-muted/40 text-muted-foreground rounded border-l-2 p-2 text-sm">
                                                            <span class="text-foreground font-medium">Internal note:</span>
                                                            {committedNote}
                                                        </p>
                                                    {/if}
                                                    {#if canVerify && activeItemId === item.id}
                                                        {#if activeEditorMode === 'deficient'}
                                                            <label class="grid gap-1 text-sm font-medium" for={`instruction-${item.id}`}>
                                                                <span class="flex flex-wrap items-center gap-1.5">
                                                                    <span>Applicant instruction</span>
                                                                    <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">(required)</span>
                                                                </span>
                                                                <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">
                                                                    Shown to the applicant in their portal and correction notice.
                                                                </span>
                                                                <Textarea
                                                                    id={`instruction-${item.id}`}
                                                                    bind:value={values.applicant_instruction}
                                                                    oninput={(event) => updateDraftField(item, 'applicant_instruction', event.currentTarget.value)}
                                                                    maxlength="2000"
                                                                    class="min-h-20 w-full"
                                                                    aria-describedby={`instruction-error-${item.id}`}
                                                                    aria-invalid={Boolean(instructionError(item))}
                                                                />
                                                            </label>
                                                            {#if instructionError(item)}
                                                                <p id={`instruction-error-${item.id}`} role="alert" class="text-destructive text-sm">
                                                                    {instructionError(item)}
                                                                </p>
                                                            {/if}
                                                            <ReasonSuggestionChips
                                                                ariaLabel="Instruction suggestions"
                                                                suggestions={availableSuggestions.map((s) => ({
                                                                    code: s.code,
                                                                    label: s.label,
                                                                    text: s.template.replaceAll('{document_label}', item.label).replaceAll('{{document}}', item.label),
                                                                }))}
                                                                value={values.applicant_instruction}
                                                                mode="replace"
                                                                maxlength={2000}
                                                                disabled={isSubmitting}
                                                                onSelect={(nextValue) => updateDraftField(item, 'applicant_instruction', nextValue)}
                                                            />
                                                        {/if}
                                                        {#if activeEditorMode === 'deficient' || activeEditorMode === 'missing' || activeEditorMode === 'note'}
                                                            <label class="grid gap-1 text-sm font-medium" for={`internal-${item.id}`}>
                                                                <span class="flex flex-wrap items-center gap-1.5">
                                                                    <span>Internal note</span>
                                                                    <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">(optional)</span>
                                                                </span>
                                                                <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">
                                                                    Staff reference only; not visible to the applicant.
                                                                </span>
                                                                <Textarea
                                                                    id={`internal-${item.id}`}
                                                                    bind:value={values.internal_remarks}
                                                                    oninput={(event) => updateDraftField(item, 'internal_remarks', event.currentTarget.value)}
                                                                    maxlength="2000"
                                                                    class="min-h-20 w-full"
                                                                    aria-invalid={Boolean(fieldErrors[`items.${item.id}.internal_remarks`] || fieldErrors.internal_remarks)}
                                                                />
                                                            </label>
                                                            <ReasonSuggestionChips
                                                                ariaLabel="Staff remark suggestions"
                                                                suggestions={DOCUMENT_INTERNAL_NOTE_SUGGESTIONS}
                                                                value={values.internal_remarks}
                                                                mode="append"
                                                                separator=" — "
                                                                maxlength={2000}
                                                                disabled={isSubmitting}
                                                                onSelect={(nextValue) => updateDraftField(item, 'internal_remarks', nextValue)}
                                                            />
                                                            {#if fieldErrors[`items.${item.id}.internal_remarks`] || fieldErrors.internal_remarks}
                                                                <p role="alert" class="text-destructive text-sm">
                                                                    {fieldErrors[`items.${item.id}.internal_remarks`] ?? fieldErrors.internal_remarks}
                                                                </p>
                                                            {/if}
                                                        {/if}
                                                        {#if draftNeedsReview(item)}
                                                            <div class="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/40">
                                                                <p role="status" class="text-sm text-amber-900 dark:text-amber-200">
                                                                    This draft was written against older checklist evidence. Your text is preserved; review the current evidence before using it.
                                                                </p>
                                                                <Button type="button" variant="outline" class="min-h-11" onclick={() => reviewDraftEvidence(item)}>
                                                                    Review current evidence and keep draft
                                                                </Button>
                                                            </div>
                                                        {/if}
                                                        <div class="flex flex-wrap gap-2">
                                                            {#if activeEditorMode === 'deficient'}
                                                                <Button type="button" class="min-h-11" disabled={isSubmitting} onclick={() => saveFinding(item, 'deficient')}>Save deficient</Button>
                                                            {:else if activeEditorMode === 'missing'}
                                                                <Button type="button" class="min-h-11" disabled={isSubmitting} onclick={() => saveFinding(item, 'missing')}>Save missing</Button>
                                                            {:else}
                                                                <Button type="button" class="min-h-11" disabled={isSubmitting} onclick={() => saveInternalNote(item)}>Save internal note</Button>
                                                            {/if}
                                                            <Button type="button" variant="outline" class="min-h-11" disabled={isSubmitting} onclick={closeItemEditor}>Cancel</Button>
                                                        </div>
                                                    {/if}
                                                    {#if canVerify && activeItemId !== item.id && draftNeedsReview(item)}
                                                        <div class="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/40">
                                                            <p role="status" class="text-sm text-amber-900 dark:text-amber-200">
                                                                This draft was written against older checklist evidence. Your text is preserved; review the current evidence before using it.
                                                            </p>
                                                            <Button type="button" variant="outline" class="min-h-11" onclick={() => reviewDraftEvidence(item)}>
                                                                Review current evidence and keep draft
                                                            </Button>
                                                        </div>
                                                    {/if}
                                                    {#if itemErrors.length}
                                                        <p role="alert" class="text-destructive text-sm">{itemErrors.join(' ')}</p>
                                                    {/if}
                                                </div>
                                            {/if}
                                        {/snippet}
                                        {#snippet actions()}
                                            {#if canVerify}
                                                <EntityRowActions
                                                    comfortable={true}
                                                    triggerLabel={`More actions for ${item.label}`}
                                                    primaryAction={{
                                                        label: 'Mark complete',
                                                        icon: CheckCircle2,
                                                        variant: item.latest_status === 'verified' ? 'secondary' : 'outline',
                                                        disabled: isSubmitting || item.latest_status === 'verified',
                                                        onclick: () => markComplete(item),
                                                    }}
                                                    overflowItems={[
                                                        {
                                                            label: 'Flag as deficient',
                                                            icon: AlertCircle,
                                                            disabled: isSubmitting,
                                                            onclick: () => openItemEditor(item, 'deficient'),
                                                        },
                                                        {
                                                            label: 'Mark missing',
                                                            icon: XCircle,
                                                            disabled: isSubmitting,
                                                            onclick: () => openItemEditor(item, 'missing'),
                                                        },
                                                        {
                                                            label: 'Add/edit internal note',
                                                            icon: FileText,
                                                            disabled: isSubmitting,
                                                            onclick: () => openItemEditor(item, 'note'),
                                                        },
                                                    ]}
                                                />
                                            {/if}
                                        {/snippet}
                                    </DocumentRequirementCard>
                                </li>
                            {/each}
                        </ul>
                    {/if}
                {/if}
                {#if items.some((item) => item.history?.length > 1)}
                    <section
                        class="space-y-2 border-t pt-4"
                        aria-labelledby="inspection-history-heading"
                    >
                        <h3
                            id="inspection-history-heading"
                            class="text-sm font-semibold"
                        >
                            Inspection history
                        </h3>
                        {#each items.filter((item) => item.history?.length > 1) as item (item.id)}
                            <details class="rounded-md border p-3">
                                <summary
                                    class="min-h-11 cursor-pointer content-center text-sm font-medium"
                                    >{item.label} · {item.history.length} inspections</summary
                                >
                                <ol class="space-y-2 pt-2">
                                    {#each [...item.history].reverse() as observation (observation.id)}
                                        <li
                                            class="bg-muted/30 rounded p-2 text-sm"
                                        >
                                            <div
                                                class="flex flex-wrap justify-between gap-2"
                                            >
                                                <span
                                                    class="font-medium capitalize"
                                                    >{observation.status}</span
                                                ><time
                                                    datetime={observation.verified_at}
                                                    class="text-muted-foreground text-xs"
                                                    >{formatDateTime(observation.verified_at)}</time
                                                >
                                            </div>
                                            {#if observation.applicant_instruction}<p
                                                    class="mt-1"
                                                >
                                                    <span class="font-medium"
                                                        >Applicant instruction:</span
                                                    >
                                                    {observation.applicant_instruction}
                                                </p>{/if}
                                            {#if !readOnly && observation.internal_remarks}<p
                                                    class="text-muted-foreground mt-1"
                                                >
                                                    <span
                                                        class="text-foreground font-medium"
                                                        >Internal note:</span
                                                    >
                                                    {observation.internal_remarks}
                                                </p>{/if}
                                            <p
                                                class="text-muted-foreground mt-1 text-xs"
                                            >
                                                Recorded by {observation.verified_by_name ??
                                                    'Registrar'}
                                            </p>
                                        </li>
                                    {/each}
                                </ol>
                            </details>
                        {/each}
                    </section>
                {/if}

                {#if normalizedRequests.length > 0}
                    <section
                        class="space-y-3 border-t pt-4"
                        aria-labelledby="document-request-history-heading"
                    >
                        <h3
                            id="document-request-history-heading"
                            class="text-sm font-semibold"
                        >
                            Document request history
                        </h3>
                        {#each normalizedRequests as request (request.id)}
                            <article class="rounded-md border p-3">
                                <div
                                    class="flex flex-wrap justify-between gap-2"
                                >
                                    <p class="text-sm font-medium">
                                        {request.status === 'current'
                                            ? 'Current request'
                                            : 'Historical request'}
                                    </p>
                                    <time
                                        datetime={request.recorded_at}
                                        class="text-muted-foreground text-xs"
                                        >{formatDateTime(request.recorded_at)}</time
                                    >
                                </div>
                                {#if request.overall_note}<p
                                        class="mt-2 text-sm"
                                    >
                                        {request.overall_note}
                                    </p>{/if}
                                <ul class="mt-2 space-y-2">
                                    {#each request.items ?? [] as requestedItem (`${request.id}-${requestedItem.requirement_item_id}`)}<li
                                            class="bg-muted/30 rounded p-2 text-sm"
                                        >
                                            <div
                                                class="flex flex-wrap justify-between gap-2"
                                            >
                                                <span class="font-medium"
                                                    >{requestedItem.label}</span
                                                ><span
                                                    class="text-muted-foreground text-xs capitalize"
                                                    >{requestedItem.status}</span
                                                >
                                            </div>
                                            <p class="mt-1">
                                                {requestedItem.applicant_instruction}
                                            </p>
                                        </li>{/each}
                                </ul>
                                {#if request.delivery_status}<p
                                        class="text-muted-foreground mt-2 text-xs"
                                    >
                                        Notification delivery: {request.delivery_status}
                                    </p>{/if}
                            </article>
                        {/each}
                    </section>
                {/if}
                {#if isClearanceConfirmed}<p
                        class="border-border bg-muted/30 text-muted-foreground rounded-md border p-3 text-sm"
                    >
                        Checklist changes and new document requests are locked
                        while admission clearance is confirmed.
                    </p>{/if}
                {#if deliveryNotice}<p
                        role="status"
                        class="text-muted-foreground text-sm"
                    >
                        {deliveryNotice}
                    </p>{/if}
            {/if}
        </Card.Content>
    </Card.Root>
{:else}
    <Card.Root
        ><Card.Content class="text-muted-foreground p-4 text-sm"
            >Document checklist is not available for this record.</Card.Content
        ></Card.Root
    >
{/if}
<Dialog.Root bind:open={bulkVerifyDialogOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Verify document requirements</Dialog.Title>
            <Dialog.Description>
                Confirm verification for {verifiableSelected.length} document {verifiableSelected.length === 1 ? 'requirement' : 'requirements'}:
            </Dialog.Description>
        </Dialog.Header>
        <div class="space-y-3">
            <ul class="max-h-60 overflow-y-auto divide-y rounded-md border text-sm">
                {#each verifiableSelected as item (item.id)}
                    <li class="flex items-center justify-between p-2.5">
                        <span class="font-medium text-foreground">{item.label}</span>
                    </li>
                {/each}
            </ul>
        </div>
        <Dialog.Footer class="flex-col-reverse gap-2 sm:flex-row">
            <Button
                type="button"
                variant="outline"
                disabled={isSubmitting}
                onclick={() => (bulkVerifyDialogOpen = false)}
            >
                Cancel
            </Button>
            <Button
                type="button"
                class="bg-emerald-600 text-white hover:bg-emerald-700"
                disabled={isSubmitting || verifiableSelected.length === 0}
                onclick={submitBulkVerify}
            >
                <CheckCircle2 class="mr-1.5 size-4" />
                {isSubmitting ? 'Verifying…' : `Confirm Verify (${verifiableSelected.length})`}
            </Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>


<Dialog.Root bind:open={composeOpen}>
    <Dialog.Content class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
        {#if !reviewOpen}
            <Dialog.Header>
                <Dialog.Title>Request document correction</Dialog.Title>
                <Dialog.Description>Select eligible checklist items and prepare applicant-facing instructions.</Dialog.Description>
            </Dialog.Header>
            <div class="space-y-4">
                {#each eligibleItems as item (item.id)}
                    {@const values = itemDraft(item)}
                    {@const reqState = requestStateByItemId[item.id]}
                    <section class="space-y-3 rounded-md border p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="flex min-h-11 items-center gap-3 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    aria-label="Include in document request"
                                    checked={Boolean(selected[item.id] && selectedEvidence[item.id] === evidenceKey(item))}
                                    onchange={(event) => toggleRequest(item, event.currentTarget.checked)}
                                    class="accent-primary size-5"
                                />
                                Include {item.label}
                            </label>
                            {#if reqState?.itemStatus === 'current'}
                                <Badge variant="outline" class="border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-300">
                                    Already requested
                                </Badge>
                            {:else if reqState?.itemStatus === 'stale'}
                                <Badge variant="outline" class="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                    Updated since last request
                                </Badge>
                            {:else}
                                <Badge variant="outline" class="border-border bg-muted/30 text-muted-foreground">
                                    Not yet requested
                                </Badge>
                            {/if}
                        </div>
                        {#if selectedItems.some((selectedItem) => selectedItem.id === item.id)}
                            <div class="grid min-w-0 gap-2">
                                <label class="grid gap-1 text-sm font-medium" for={`request-instruction-${item.id}`}>
                                    <span class="flex flex-wrap items-center gap-1.5">
                                        <span>Applicant instruction for this request</span>
                                        <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">(required)</span>
                                    </span>
                                    <Textarea
                                        id={`request-instruction-${item.id}`}
                                        aria-label="Applicant instruction for this request"
                                        bind:value={values.applicant_instruction}
                                        oninput={(event) => updateDraftField(item, 'applicant_instruction', event.currentTarget.value)}
                                        maxlength="2000"
                                        class="min-h-20 w-full"
                                        aria-invalid={Boolean(instructionError(item))}
                                    />
                                </label>
                                {#if instructionError(item)}
                                    <p role="alert" class="text-destructive text-sm">{instructionError(item)}</p>
                                {/if}
                                <ReasonSuggestionChips
                                    ariaLabel={`Safe instruction suggestions for ${item.label}`}
                                    suggestions={availableSuggestions.map((s) => ({
                                        code: s.code,
                                        label: s.label,
                                        text: s.template.replaceAll('{document_label}', item.label).replaceAll('{{document}}', item.label),
                                    }))}
                                    value={values.applicant_instruction}
                                    mode="replace"
                                    maxlength={2000}
                                    disabled={isSubmitting}
                                    onSelect={(nextValue) => updateDraftField(item, 'applicant_instruction', nextValue)}
                                />
                            </div>
                        {/if}
                    </section>
                {/each}
                <label class="grid gap-1 text-sm font-medium" for="document-overall-note">
                    <span class="flex flex-wrap items-center gap-1.5">
                        <span>Note for applicant</span>
                        <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">(optional)</span>
                    </span>
                    <span class="text-muted-foreground text-xs font-normal" aria-hidden="true">
                        Included at the top of the correction request notice.
                    </span>
                    <Textarea
                        id="document-overall-note"
                        bind:value={overallNote}
                        maxlength="2000"
                        class="min-h-20 w-full"
                        aria-invalid={Boolean(fieldErrors.overall_note)}
                    />
                    <ReasonSuggestionChips
                        ariaLabel="Overall note suggestions"
                        suggestions={DOCUMENT_OVERALL_REQUEST_NOTE_SUGGESTIONS}
                        bind:value={overallNote}
                        mode="append"
                        separator=" "
                        maxlength={2000}
                        disabled={isSubmitting}
                    />
                </label>
                {#if fieldErrors.overall_note || fieldErrors.request}
                    <p role="alert" class="text-destructive text-sm">{fieldErrors.overall_note ?? fieldErrors.request}</p>
                {/if}
            </div>
            <Dialog.Footer class="flex-col-reverse gap-2 sm:flex-row">
                <Button type="button" variant="outline" class="min-h-11" onclick={() => (composeOpen = false)}>Cancel</Button>
                <Button type="button" class="min-h-11" disabled={isSubmitting || selectedItems.length === 0} onclick={openReview}>
                    Review {selectedItems.length} selected {selectedItems.length === 1 ? 'item' : 'items'}
                </Button>
            </Dialog.Footer>
        {:else}
            <Dialog.Header>
                <Dialog.Title>Review document request</Dialog.Title>
                <Dialog.Description>
                    Check each selected item and instruction. Sending records a request; delivery is reported separately.
                </Dialog.Description>
            </Dialog.Header>
        <div class="space-y-3">
            {#each reviewedItems as item, index (`${item.requirement_item_id}`)}
                <article class="rounded-md border p-3">
                    <h3 class="text-sm font-semibold">
                        {item.label}
                        <span
                            class="text-muted-foreground font-normal capitalize"
                            >· {item.observed_status}</span
                        >
                    </h3>
                    <p class="mt-2 text-sm whitespace-pre-wrap">
                        {item.applicant_instruction}
                    </p>
                    {#if fieldErrors[`items.${index}.applicant_instruction`]}<p
                            tabindex="-1"
                            role="alert"
                            class="text-destructive mt-2 text-sm"
                        >
                            {fieldErrors[
                                `items.${index}.applicant_instruction`
                            ]}
                        </p>{/if}
                </article>
            {/each}
            {#if reviewedNote}<p class="bg-muted/40 rounded-md p-3 text-sm">
                    <span class="font-medium">Note:</span>
                    {reviewedNote}
                </p>{/if}
            {#if reviewIsStale}<p
                    tabindex="-1"
                    role="alert"
                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    Evidence changed after review. Cancel and review the current
                    checklist before sending.
                </p>{/if}
            {#if fieldErrors.overall_note || fieldErrors.request}<p
                    tabindex="-1"
                    role="alert"
                    class="text-destructive text-sm"
                >
                    {fieldErrors.overall_note ?? fieldErrors.request}
                </p>{/if}
        </div>
        {#if conflictMessage}
            <p
                tabindex="-1"
                role="alert"
                class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                {conflictMessage}
            </p>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                disabled={isSubmitting}
                onclick={refreshChecklist}>Refresh checklist</Button
            >
        {/if}
        <Dialog.Footer class="flex-col-reverse gap-2 sm:flex-row">
            <Button type="button" variant="outline" class="min-h-11" onclick={() => (reviewOpen = false)}>Back to edit</Button>
            <Button type="button" variant="outline" class="min-h-11" onclick={() => (composeOpen = false)}>Cancel</Button>
            <Button
                type="button"
                class="min-h-11"
                disabled={isSubmitting || reviewIsStale || Boolean(conflictMessage)}
                onclick={sendRequest}
            ><Send class="mr-2 size-4" />{isSubmitting ? 'Sending…' : 'Send document request'}</Button>
        </Dialog.Footer>
        {/if}
    </Dialog.Content>
</Dialog.Root>
