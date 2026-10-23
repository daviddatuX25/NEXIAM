<script>
    import { tick, untrack } from 'svelte';
    import {
        Check,
        CircleAlert,
        CircleDotDashed,
        ChevronDown,
        ChevronUp,
        EyeOff,
        Image as ImageIcon,
        Layers3,
        Loader2,
        LockKeyhole,
        RefreshCw,
        Save,
        ScanLine,
        X,
        XCircle,
    } from 'lucide-svelte';
    import OmrRegionCrop from './OmrRegionCrop.svelte';
    import OmrSheetEvidenceCanvas from './OmrSheetEvidenceCanvas.svelte';
    import { presentOmrEvidenceReason } from './omrEvidenceReasons';
    import {
        detectedText,
        itemProblem,
        mappingUrl,
        normalizeEvidence,
        normalizeEvidenceFilter,
        normalizeEvidencePage,
        reviewIsStale,
        reviewPayloadForItem,
        returnLabel,
        stateLabel,
    } from '../../../lib/omrDiagnosticEvidence.js';

    let {
        /** Bounded OmrEvidencePage response. */
        page = null,
        labels = {},
        returnUrl = null,
        loading = false,
        error = null,
        onRetry = null,
        onPageChange = null,
        onGroupChange = null,
        onFilterChange = null,
        onRowOpen = null,
        onTabChange = null,
        onViewChange = null,
        initialTab = 'aligned',
        mobileRailExpanded = $bindable(false),
        contextSummary = null,
        idPrefix = 'omr-evidence',
        reviewable = false,
        blind = false,
        reviewReasons = [],
        onDirtyChange = null,
        onDirtyNavigation = null,
        onSaveDecision = null,
        onSaveAndNext = null,
    } = $props();

    const normalizedPage = $derived(normalizeEvidencePage(page));
    const identityWriteIn = $derived(
        normalizedPage?.sheet_map?.identity_write_in ?? null,
    );
    const visibleItems = $derived(normalizedPage?.items ?? []);
    const sheetItems = $derived(
        (normalizedPage?.sheet_map?.items ?? [])
            .map((item) => itemView(item))
            .filter(Boolean),
    );
    const identityItems = $derived(
        visibleItems
            .filter((item) => item.group_type === 'identity')
            .map((item) => itemView(item))
            .filter(Boolean)
            .toSorted(
                (left, right) =>
                    Number(left.question_no ?? left.position ?? 0) -
                    Number(right.question_no ?? right.position ?? 0),
            ),
    );
    const identityItemsReviewable = $derived(
        identityItems.length > 0 &&
            identityItems.every((item) => item.reviewable === true),
    );
    const identitySummaryItems = $derived.by(() => {
        const mapped = sheetItems
            .filter((item) => item.group_type === 'identity')
            .toSorted(
                (left, right) =>
                    Number(left.question_no ?? left.position ?? 0) -
                    Number(right.question_no ?? right.position ?? 0),
            );
        return mapped.length > 0 ? mapped : identityItems;
    });
    const identityDetailsComplete = $derived(
        normalizedPage?.area === 'identity' &&
            identitySummaryItems.length > 0 &&
            identityItems.length === identitySummaryItems.length &&
            identitySummaryItems.every((summaryItem) =>
                identityItems.some(
                    (item) => rowId(item) === rowId(summaryItem),
                ),
            ),
    );
    const sourceView = $derived.by(() => {
        if (!normalizedPage) return null;
        const raw = page && typeof page === 'object' ? page : {};
        const lazy = normalizedPage.lazy_asset_urls;
        return normalizeEvidence(
            {
                ...(raw && typeof raw === 'object' ? raw : {}),
                outcome: 'succeeded',
                page_no: normalizedPage.capture_page_no ?? 1,
                assets: {
                    captured: lazy.input ? { url: lazy.input } : null,
                    aligned: lazy.aligned ? { url: lazy.aligned } : null,
                    overlay: lazy.overlay ? { url: lazy.overlay } : null,
                },
                items: visibleItems.filter(
                    (item) =>
                        item.group_type !== 'identity' &&
                        item.group_type !== 'calibration',
                ),
            },
            { cropImageUrl: lazy.aligned ?? null },
        );
    });
    const blindReview = $derived(
        Boolean(blind || normalizedPage?.blind_review),
    );
    const documentFields = $derived(
        (normalizedPage?.sheet_map?.document_fields ?? []).map((field) => ({
            ...field,
            crop_rect: field.region,
            label: field.label ?? field.type ?? 'Recorded field',
        })),
    );
    const candidateNameField = $derived(
        documentFields.find((field) => field.type === 'candidate_name') ?? null,
    );
    const isReviewable = $derived(
        Boolean(
            reviewable &&
            normalizedPage?.reviewable === true &&
            (onSaveDecision || onSaveAndNext),
        ),
    );
    const reasons = $derived(
        (reviewReasons?.length
            ? reviewReasons
            : (normalizedPage?.review_reasons ?? [])) ?? [],
    );
    const noteMax = $derived(normalizedPage?.review_note_max ?? 500);
    const identityConfidence = $derived.by(() => {
        const values = identitySummaryItems
            .map((item) => itemMachine(item)?.confidence)
            .filter(
                (value) => typeof value === 'number' && Number.isFinite(value),
            );
        return values.length > 0
            ? values.reduce((sum, value) => sum + value, 0) / values.length
            : null;
    });
    const detectedIdentity = $derived(
        identitySummaryItems
            .map(
                (item) =>
                    itemMachine(item)?.selected_option ??
                    item.detected?.selected_option ??
                    '',
            )
            .join(''),
    );
    const identityDraftCount = $derived(
        identityItems.filter((item) => hasDraft(item)).length,
    );
    const selectedEditorItem = $derived(
        visibleItems.find((item) => rowId(item) === String(openRowId ?? '')) ??
            null,
    );
    const selectedEditorView = $derived(
        selectedEditorItem ? itemView(selectedEditorItem) : null,
    );
    const sheetAsset = $derived(
        normalizedPage
            ? {
                  url: normalizedPage.lazy_asset_urls.aligned,
                  width: normalizedPage.sheet_map.width,
                  height: normalizedPage.sheet_map.height,
                  sha256: normalizedPage.sheet_map.sha256,
                  page_no: normalizedPage.sheet_map.page_no,
                  kind: 'aligned_page',
              }
            : null,
    );
    const activeImageUrl = $derived(
        activeMode === 'raw'
            ? (normalizedPage?.lazy_asset_urls?.input ?? null)
            : (normalizedPage?.lazy_asset_urls?.aligned ?? null),
    );
    const stencilTruthAvailable = $derived(
        !blindReview &&
            sheetItems.some((item) =>
                item.options.some((option) => {
                    const region = option.region;
                    return (
                        (item.expected === null ||
                            option.code === item.expected) &&
                        region !== null &&
                        Number.isFinite(region.x) &&
                        Number.isFinite(region.y) &&
                        Number.isFinite(region.w) &&
                        Number.isFinite(region.h) &&
                        region.w > 0 &&
                        region.h > 0 &&
                        region.x >= 0 &&
                        region.y >= 0 &&
                        region.x + region.w <= 1 &&
                        region.y + region.h <= 1
                    );
                }),
            ),
    );
    const needsReviewCount = $derived(
        normalizedPage?.needs_review_total ??
            normalizedPage?.mismatch_total ??
            0,
    );
    const activeFilter = $derived(
        normalizeEvidenceFilter(normalizedPage?.filter),
    );
    const queueFilter = $derived(
        activeFilter === 'all' ? 'all' : 'needs_review',
    );

    let drafts = $state({});
    let saveErrors = $state({});
    let savingItemId = $state(null);
    let savedReviews = $state({});
    let revealedItems = $state(new Set());
    let identityCorrection = $state('');
    let identitySource = $state('');
    let identityCorrectionError = $state(null);
    let savingIdentity = $state(false);
    let pendingNavigation = $state(null);
    let selectedSheetItemId = $state(null);
    let openRowId = $state(null);
    let activeMode = $state(untrack(() => normalizeMode(initialTab)));
    let moreOpen = $state(false);
    let lastDirty = false;

    const dirty = $derived(Object.keys(drafts).length > 0);

    $effect(() => {
        if (dirty === lastDirty) return;
        lastDirty = dirty;
        onDirtyChange?.(dirty);
    });

    $effect(() => {
        if (identitySource === detectedIdentity) return;
        identitySource = detectedIdentity;
        identityCorrection = detectedIdentity;
        identityCorrectionError = null;
    });

    $effect(() => {
        if (sheetItems.length === 0) {
            if (!pendingSheetReviewId) selectedSheetItemId = null;
            return;
        }
        if (pendingSheetReviewId) {
            selectedSheetItemId = pendingSheetReviewId;
            return;
        }
        const area = normalizedPage?.area ?? 'all';
        const areaItems =
            area === 'all'
                ? sheetItems
                : sheetItems.filter((item) => sheetArea(item) === area);
        const currentInArea = areaItems.some(
            (item) => rowId(item) === String(selectedSheetItemId ?? ''),
        );
        if (currentInArea) return;
        selectedSheetItemId = rowId(
            areaItems.find((item) => itemProblem(item) !== null) ??
                areaItems[0] ??
                sheetProblemItems[0] ??
                sheetItems[0],
        );
    });

    $effect(() => {
        if (!pendingSheetReviewId || !normalizedPage) return;
        const target = visibleItems.find(
            (item) => rowId(item) === pendingSheetReviewId,
        );
        if (!target) return;
        const id = pendingSheetReviewId;
        pendingSheetReviewId = null;
        openRowId = id;
        mobileRailExpanded = true;
        tick().then(() =>
            document
                .getElementById(`${idPrefix}-row-${id}`)
                ?.scrollIntoView?.({ block: 'center' }),
        );
    });

    let pendingSheetReviewId = $state(null);
    const sheetProblemItems = $derived(
        sheetItems.filter((item) => itemProblem(item) !== null),
    );

    function normalizeMode(value) {
        if (value === 'captured' || value === 'raw') return 'raw';
        if (value === 'recognition' || value === 'markings') return 'markings';
        return 'stencil';
    }

    function idempotencyKey() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID)
            return crypto.randomUUID();
        return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    }

    function itemView(item) {
        return normalizeEvidence({ items: [item] })?.items?.[0] ?? null;
    }

    function rowId(item) {
        return String(item?.item_id ?? item?.id ?? item?.label ?? 'item');
    }

    function currentReview(item, view = itemView(item)) {
        const id = rowId(item);
        return savedReviews[id] ?? view?.review ?? null;
    }

    function itemMachine(item, view = itemView(item)) {
        return view?.machine ?? view?.detected ?? null;
    }

    function itemIsStale(item, view = itemView(item)) {
        return view
            ? reviewIsStale({ ...view, review: currentReview(item, view) })
            : false;
    }

    function itemUnavailableReason(item, view = itemView(item)) {
        if (!view) return 'missing-view';
        if (item.unavailable_reason) return String(item.unavailable_reason);
        if (item.unavailable === true) return 'evidence-unavailable';
        if (item.geometry_stale === true) return 'stale-geometry';
        return null;
    }
    function unavailableReasonPresentation(reason) {
        return presentOmrEvidenceReason(reason ?? 'unknown-evidence-reason');
    }

    function hasDraft(item) {
        return Boolean(drafts[rowId(item)]);
    }

    function setDraft(item, draft) {
        const id = rowId(item);
        drafts = { ...drafts, [id]: draft };
        saveErrors = { ...saveErrors, [id]: null };
    }

    function clearDraft(item) {
        const id = rowId(item);
        const next = { ...drafts };
        delete next[id];
        drafts = next;
    }

    function discardDrafts() {
        drafts = {};
        saveErrors = {};
        pendingNavigation = null;
    }

    function guardedNavigation(intent, action) {
        if (savingItemId || savingIdentity) return;
        if (!dirty) {
            action();
            return;
        }
        const callbackResult = onDirtyNavigation?.(intent);
        if (callbackResult === true) {
            discardDrafts();
            action();
            return;
        }
        pendingNavigation = { label: intent.label ?? 'this change', action };
    }

    function confirmDiscard() {
        const action = pendingNavigation?.action;
        discardDrafts();
        action?.();
    }

    function reviewForDisplay(item, view) {
        const review = currentReview(item, view);
        return review && !itemIsStale(item, view) ? review : null;
    }

    function reviewLabel(decision) {
        return (
            {
                confirm_machine: 'Use detected answer',
                set_option: 'Choose answer',
                blank: 'Mark blank',
                unresolved: 'Leave unresolved',
            }[decision] ??
            decision ??
            'Not reviewed'
        );
    }

    function selectedMachineOption(item, view) {
        return itemMachine(item, view)?.selected_option ?? null;
    }

    function setDecision(item, decision, selectedOption = null) {
        if (!isReviewable || !item.reviewable) return;
        const view = itemView(item);
        const machine = itemMachine(item, view);
        if (decision === 'confirm_machine' && machine?.state !== 'marked')
            return;
        const options = Array.isArray(item.options) ? item.options : [];
        const fallback =
            selectedOption ??
            drafts[rowId(item)]?.selected_option ??
            options[0]?.code ??
            null;
        setDraft(item, {
            decision,
            selected_option:
                decision === 'set_option'
                    ? fallback
                    : decision === 'confirm_machine'
                      ? (machine?.selected_option ?? null)
                      : null,
            reason_code: drafts[rowId(item)]?.reason_code ?? null,
            note: drafts[rowId(item)]?.note ?? null,
        });
        openRowId = rowId(item);
        mobileRailExpanded = true;
    }

    function chooseOption(item, code) {
        setDecision(item, 'set_option', code);
    }

    function stageIdentityCorrection() {
        if (!identityDetailsComplete || !identityItemsReviewable) return;
        const value = String(identityCorrection ?? '').trim();
        if (!new RegExp(`^\\d{${identityItems.length}}$`).test(value)) {
            identityCorrectionError = `Enter exactly ${identityItems.length} digits.`;
            return;
        }
        const reason =
            reasons.find(
                (candidate) => candidate.code === 'incorrect_detection',
            )?.code ??
            reasons[0]?.code ??
            null;
        const note = `Corrected examinee ID from ${detectedIdentity || 'unreadable'} to ${value}.`;
        let changed = 0;
        identityItems.forEach((item, index) => {
            const machine = String(itemMachine(item)?.selected_option ?? '');
            if (machine === value[index]) {
                clearDraft(item);
                return;
            }
            setDraft(item, {
                decision: 'set_option',
                selected_option: value[index],
                reason_code: reason,
                note,
            });
            changed += 1;
        });
        identityCorrectionError =
            changed === 0
                ? 'The entered ID already matches the detected ID.'
                : null;
    }

    async function saveIdentityCorrection() {
        if (!identityDetailsComplete || !identityItemsReviewable) return;
        stageIdentityCorrection();
        const changedItems = identityItems.filter((item, index) => {
            const machine = String(itemMachine(item)?.selected_option ?? '');
            return machine !== String(identityCorrection ?? '').trim()[index];
        });
        if (identityCorrectionError || changedItems.length === 0) return;
        savingIdentity = true;
        for (const item of changedItems) {
            const saved = await saveItem(item);
            if (!saved) {
                identityCorrectionError =
                    'Some digits were not saved. Resolve the highlighted digit and retry.';
                savingIdentity = false;
                return;
            }
        }
        identityCorrectionError = null;
        savingIdentity = false;
    }

    function draftNeedsReason(item, draft, view = itemView(item)) {
        if (!draft) return false;
        const machineOption = selectedMachineOption(item, view);
        if (draft.decision === 'unresolved') return true;
        if (draft.decision === 'set_option')
            return draft.selected_option !== machineOption;
        return draft.decision === 'blank' && machineOption !== null;
    }

    function draftNeedsNote(item, draft, view = itemView(item)) {
        return (
            draft?.decision === 'unresolved' ||
            draftNeedsReason(item, draft, view)
        );
    }

    function validationMessage(item, draft, view) {
        if (!draft) return null;
        if (
            draft.decision === 'set_option' &&
            !Array.isArray(item.options)?.length &&
            !draft.selected_option
        )
            return 'Choose one of the pinned options.';
        if (draftNeedsReason(item, draft, view) && !draft.reason_code)
            return 'Choose a structured reason before saving.';
        if (
            draftNeedsNote(item, draft, view) &&
            !String(draft.note ?? '').trim()
        )
            return 'Add a short note explaining this review.';
        if (String(draft.note ?? '').length > noteMax)
            return `Note must be ${noteMax} characters or fewer.`;
        return null;
    }

    function extractSavedReview(result) {
        if (!result || typeof result !== 'object') return null;
        const source = result;
        const candidate =
            source.review ??
            source.current_review ??
            source.item?.review ??
            source.item?.current_review ??
            source.data?.review ??
            source.data?.item?.review ??
            null;
        return candidate && typeof candidate === 'object' ? candidate : null;
    }

    function errorDetail(error) {
        if (typeof error === 'string') return error;
        return (
            error?.detail ??
            error?.message ??
            error?.errors?.review ??
            'The review could not be saved. Your draft is still here.'
        );
    }

    function errorKind(error) {
        const code = String(error?.code ?? error?.status ?? '').toLowerCase();
        if (code.includes('conflict') || code === '409') return 'conflict';
        if (code.includes('validation') || code === '422') return 'validation';
        if (code.includes('closed') || code.includes('forbidden'))
            return 'closed';
        if (code.includes('network') || code === 'fetch') return 'network';
        return 'error';
    }

    function nextReviewItem(item) {
        const currentIndex = visibleItems.findIndex(
            (candidate) => rowId(candidate) === rowId(item),
        );
        const candidates = [
            ...visibleItems.slice(currentIndex + 1),
            ...visibleItems.slice(0, currentIndex),
        ];
        return (
            candidates.find((candidate) => {
                const view = itemView(candidate);
                return (
                    !reviewForDisplay(candidate, view) &&
                    (itemProblem(view) !== null ||
                        view?.machine?.state === 'ambiguous' ||
                        view?.machine?.state === 'unreadable' ||
                        view?.issues?.length)
                );
            }) ?? null
        );
    }

    async function saveItem(item, saveNext = false) {
        const id = rowId(item);
        const draft = drafts[id];
        const view = itemView(item);
        if (
            !draft ||
            !view ||
            savingItemId ||
            !isReviewable ||
            view.reviewable !== true
        )
            return false;
        const unavailable = itemUnavailableReason(item, view);
        if (unavailable) {
            saveErrors = {
                ...saveErrors,
                [id]: {
                    kind: 'unavailable',
                    detail: `${unavailableReasonPresentation(unavailable).message} (${unavailableReasonPresentation(unavailable).technicalDetail}). Review save is blocked.`,
                },
            };
            return false;
        }
        if (itemIsStale(item, view)) {
            saveErrors = {
                ...saveErrors,
                [id]: {
                    kind: 'conflict',
                    detail: 'This review is stale against a newer machine evidence version. Reload before saving.',
                },
            };
            return false;
        }
        const invalid = validationMessage(item, draft, view);
        if (invalid) {
            saveErrors = {
                ...saveErrors,
                [id]: { kind: 'validation', detail: invalid },
            };
            queueMicrotask(() =>
                document.getElementById(`${idPrefix}-reason-${id}`)?.focus(),
            );
            return false;
        }
        const callback = saveNext ? onSaveAndNext : onSaveDecision;
        if (!callback) return false;
        savingItemId = id;
        saveErrors = { ...saveErrors, [id]: null };
        const payload = reviewPayloadForItem(
            view,
            draft,
            idempotencyKey(),
            view.parent_version ?? normalizedPage?.parent_version ?? null,
        );
        try {
            const result = await callback(payload, item);
            const review = extractSavedReview(result);
            if (review) {
                savedReviews = { ...savedReviews, [id]: review };
                revealedItems = new Set([...revealedItems, id]);
            }
            clearDraft(item);
            saveErrors = { ...saveErrors, [id]: null };
            savingItemId = null;
            if (saveNext) {
                const next = nextReviewItem(item);
                if (next) {
                    openRowId = rowId(next);
                    selectedSheetItemId = rowId(next);
                    onRowOpen?.(next);
                } else if (
                    normalizedPage &&
                    normalizedPage.page < normalizedPage.page_count
                ) {
                    guardedNavigation(
                        { kind: 'pagination', label: 'the next evidence page' },
                        () => selectPage(normalizedPage.page + 1, true),
                    );
                }
            }
            return true;
        } catch (error) {
            savingItemId = null;
            saveErrors = {
                ...saveErrors,
                [id]: { kind: errorKind(error), detail: errorDetail(error) },
            };
            return false;
        }
    }

    function reviewHistory(item, view) {
        const current = currentReview(item, view);
        const history = Array.isArray(view?.review_history)
            ? view.review_history
            : [];
        const entries = current ? [current, ...history] : history;
        return entries.filter(
            (entry, index, list) =>
                index ===
                list.findIndex(
                    (candidate) =>
                        candidate.revision != null &&
                        candidate.revision === entry.revision,
                ),
        );
    }

    function selectMode(key) {
        if (!['raw', 'stencil', 'markings'].includes(key)) return;
        if (key === activeMode) return;
        guardedNavigation(
            { kind: 'mode', label: `the ${key} canvas mode`, target: key },
            () => {
                activeMode = key;
                onTabChange?.(key);
            },
        );
    }

    function selectGroup(key) {
        if (!key || key === normalizedPage?.area || !normalizedPage) return;
        guardedNavigation(
            { kind: 'area', label: 'a different evidence area', target: key },
            () => {
                openRowId = null;
                onGroupChange?.(key);
            },
        );
    }
    function selectArea(areaKey) {
        if (areaKey === (normalizedPage?.area ?? 'all')) return;
        guardedNavigation(
            { kind: 'area', label: `the ${areaKey} area`, target: areaKey },
            () => {
                if (onViewChange) {
                    onViewChange({ area: areaKey, filter: queueFilter, page: 1 });
                } else {
                    onGroupChange?.(areaKey);
                    onPageChange?.(1);
                }
            },
        );
    }

    function selectFilter(value) {
        if (!normalizedPage) return;
        const filter = normalizeEvidenceFilter(value);
        if (
            !['all', 'needs_review'].includes(filter) ||
            filter === queueFilter ||
            (filter === 'needs_review' && needsReviewCount === 0)
        )
            return;
        guardedNavigation(
            {
                kind: 'filter',
                label: 'a different evidence filter',
                target: filter,
            },
            () => {
                openRowId = null;
                onFilterChange?.(filter);
            },
        );
    }

    function selectPage(nextPage, bypassGuard = false) {
        if (
            !normalizedPage ||
            nextPage < 1 ||
            nextPage > normalizedPage.page_count ||
            nextPage === normalizedPage.page
        )
            return;
        const action = () => {
            openRowId = null;
            onPageChange?.(nextPage);
        };
        if (bypassGuard) action();
        else
            guardedNavigation(
                {
                    kind: 'pagination',
                    label: 'a different evidence page',
                    target: nextPage,
                },
                action,
            );
    }

    function focusRow(item) {
        selectedSheetItemId = rowId(item);
        onRowOpen?.(item);
    }

    function editRow(item) {
        guardedNavigation(
            {
                kind: 'row',
                label: 'a different evidence item',
                target: rowId(item),
            },
            () => {
                selectedSheetItemId = rowId(item);
                openRowId = rowId(item);
                mobileRailExpanded = true;
                onRowOpen?.(item);
            },
        );
    }

    function closeEditor() {
        guardedNavigation(
            { kind: 'row', label: 'closing the inline editor' },
            () => {
                openRowId = null;
                onRowOpen?.(null);
            },
        );
    }

    function selectSheetItem(item) {
        openSheetItemReview(item);
    }

    function sheetArea(item) {
        if (item?.area_code) return item.area_code;
        if (
            item?.group_type === 'identity' ||
            item?.group_type === 'calibration'
        )
            return item.group_type;
        return item?.area_key ?? 'all';
    }

    function openSheetItemReview(item) {
        if (!normalizedPage) return;
        const area = sheetArea(item);
        const groupItems = sheetItems.filter(
            (candidate) => sheetArea(candidate) === area,
        );
        const index = Math.max(
            0,
            groupItems.findIndex(
                (candidate) => rowId(candidate) === rowId(item),
            ),
        );
        const pageNumber = Math.floor(index / normalizedPage.per_page) + 1;
        guardedNavigation(
            {
                kind: 'sheet-item',
                label: `${item.label ?? item.item_id ?? 'the selected item'} review`,
                target: rowId(item),
            },
            () => {
                pendingSheetReviewId = rowId(item);
                selectedSheetItemId = rowId(item);
                if (onViewChange) {
                    onViewChange({ area, filter: 'all', page: pageNumber });
                    return;
                }
                onGroupChange?.(area);
                onFilterChange?.('all');
                onPageChange?.(pageNumber);
            },
        );
    }

    function focusIdentity() {
        if (!normalizedPage) return;
        const identityGroup = normalizedPage.groups.find(
            (group) =>
                group.key === 'identity' || group.group_type === 'identity',
        );
        if (identityGroup) {
            selectGroup(identityGroup.key);
            return;
        }
        guardedNavigation(
            {
                kind: 'area',
                label: 'the Identity evidence area',
                target: 'identity',
            },
            () => {
                onViewChange?.({ area: 'identity', filter: 'all', page: 1 });
                onGroupChange?.('identity');
                onFilterChange?.('all');
                onPageChange?.(1);
            },
        );
    }

    function rowUrl(item) {
        return typeof item.detail_url === 'string' && item.detail_url
            ? item.detail_url
            : (item.crop_asset?.url ?? null);
    }

    function rowCropRect(item) {
        return (
            item.crop_rect ?? item.region ?? item.geometry?.crop_rect ?? null
        );
    }

    function fieldAspect(field) {
        const region = field?.region;
        const width = Number(normalizedPage?.sheet_map?.width) || 1;
        const height = Number(normalizedPage?.sheet_map?.height) || 1;
        return region
            ? Math.max(
                  0.2,
                  (Number(region.w) * width) / (Number(region.h) * height),
              )
            : 1;
    }

    function fieldImageStyle(field) {
        const region = field?.region;
        if (!region) return '';
        return `width:${100 / Number(region.w)}%;max-width:none;left:${(-Number(region.x) / Number(region.w)) * 100}%;top:${(-Number(region.y) / Number(region.h)) * 100}%;`;
    }

    function shortHash(value) {
        const hash = String(value ?? '');
        return hash.length > 12
            ? `${hash.slice(0, 12)}…`
            : hash || 'Unavailable';
    }

    function rowExpected(item) {
        const expected = rowView(item)?.expected;
        if (expected === null) return 'Leave blank';
        if (blindReview && !revealedItems.has(rowId(item)))
            return 'Hidden until saved';
        return expected ?? 'No expected answer';
    }

    function rowDetected(item) {
        const view = rowView(item);
        if (
            item.expected == null &&
            (view?.machine == null || view.machine.selected_option == null)
        )
            return '—';
        return view ? detectedText(view) : stateLabel(item.state ?? null);
    }

    function rowView(item) {
        return itemView(item);
    }

    function problemLabel(item) {
        return (
            {
                incorrect: 'Incorrect',
                ambiguous: 'Ambiguous',
                missing: 'No answer detected',
            }[itemProblem(item)] ?? null
        );
    }

    function rowActionLabel(item, view = rowView(item)) {
        const displayedReview = reviewForDisplay(item, view);
        if (
            displayedReview?.decision &&
            displayedReview.decision !== 'unresolved'
        ) {
            return 'Edit';
        }
        if (displayedReview?.decision === 'unresolved') return 'Mark';
        const machine = itemMachine(item, view);
        const issueCodes = [
            ...(Array.isArray(machine?.issue_codes) ? machine.issue_codes : []),
            ...(Array.isArray(view?.issues)
                ? view.issues.map((issue) =>
                      typeof issue === 'string'
                          ? issue
                          : (issue?.code ?? issue?.issue_code ?? ''),
                  )
                : []),
        ].map((code) => String(code).toLowerCase());
        const confidence = Number(machine?.confidence);
        const lowConfidence =
            issueCodes.includes('low_confidence') ||
            (Number.isFinite(confidence) && confidence < 0.8);
        const definite =
            machine?.state === 'marked' &&
            machine.selected_option !== null &&
            machine.selected_option !== undefined &&
            machine.observed_options?.length === 1 &&
            !lowConfidence;
        return definite ? 'Edit' : 'Mark';
    }

    function rowDisplayLabel(item, view = rowView(item)) {
        const area = item?.area_key ?? view?.area_key ?? null;
        if (item?.question_no !== null && item?.question_no !== undefined) {
            return area
                ? `${area} · Q${item.question_no}`
                : `Q${item.question_no}`;
        }
        return view?.label ?? item?.item_id ?? area ?? 'Item';
    }

    function metadataValue(value) {
        if (value === null || value === undefined) return '—';
        const text = String(value).trim();
        return text || '—';
    }

    function scoreDisplay() {
        const score = contextSummary?.score;
        if (!score) return '—';
        if (score.display !== null && score.display !== undefined) {
            return metadataValue(score.display);
        }
        const earned = metadataValue(score.earned);
        const possible = metadataValue(score.possible);
        return earned === '—' && possible === '—'
            ? '—'
            : `${earned} / ${possible}`;
    }
    function hasMetadataValue(value) {
        return metadataValue(value) !== '—';
    }

    function toggleMobileRail() {
        mobileRailExpanded = !mobileRailExpanded;
        if (mobileRailExpanded) moreOpen = false;
    }

    function pageLabel() {
        return 'Evidence pages';
    }

    function paginationPages() {
        if (!normalizedPage) return [];
        const count = normalizedPage.page_count;
        if (count <= 7)
            return Array.from({ length: count }, (_, index) => index + 1);
        const first = Math.max(1, Math.min(normalizedPage.page - 2, count - 4));
        return Array.from({ length: 5 }, (_, index) => first + index);
    }

    function returnHref() {
        if (!returnUrl || !sourceView) return null;
        return mappingUrl(
            returnUrl,
            returnLabel(
                sourceView,
                normalizeEvidenceFilter(normalizedPage?.filter) === 'mismatch'
                    ? 'problems'
                    : 'all',
            ),
        );
    }

    function retry() {
        onRetry?.({
            method: normalizedPage?.method,
            capture_id: normalizedPage?.capture_id,
            area: normalizedPage?.area,
            filter: normalizedPage?.filter,
            page: normalizedPage?.page,
            per_page: normalizedPage?.per_page,
        });
    }
</script>

<div
    class="omr-evidence-panel text-foreground flex min-w-0 flex-col gap-4 text-sm"
    data-omr-evidence-panel={normalizedPage?.method ?? 'empty'}
>
    {#if loading}
        <div
            class="border-border bg-muted/20 text-muted-foreground flex items-center gap-2 rounded-lg border border-dashed p-4 text-xs"
            role="status"
            aria-live="polite"
        >
            <Loader2 class="size-4 animate-spin" aria-hidden="true" />
            <span>{labels.loading ?? 'Evidence is loading…'}</span>
        </div>
    {:else if error}
        <div
            class="border-destructive/30 bg-destructive/5 text-destructive flex items-start gap-3 rounded-lg border p-4 text-xs"
            role="alert"
        >
            <XCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">
                    {labels.errorTitle ?? 'Evidence could not be loaded'}
                </p>
                <p class="text-destructive/80 mt-1">
                    {typeof error === 'string'
                        ? error
                        : (error.detail ??
                          error.message ??
                          'The private evidence read failed.')}
                </p>
                {#if onRetry}
                    <button
                        type="button"
                        class="border-destructive/30 hover:bg-destructive/10 focus-visible:ring-ring mt-3 inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        onclick={retry}
                    >
                        <RefreshCw class="size-3.5" aria-hidden="true" />
                        {labels.retryLabel ?? 'Retry'}
                    </button>
                {/if}
            </div>
        </div>
    {:else if !normalizedPage}
        <div
            class="border-border bg-muted/20 text-muted-foreground rounded-lg border border-dashed p-4 text-xs"
            role="status"
            aria-live="polite"
        >
            {labels.empty ?? 'No evidence is available for this capture yet.'}
            {#if onRetry}
                <button
                    type="button"
                    class="text-foreground focus-visible:ring-ring ml-2 inline-flex items-center gap-1 font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    onclick={retry}>{labels.retryLabel ?? 'Retry'}</button
                >
            {/if}
        </div>
    {:else}
        <div
            class="evidence-heading flex min-w-0 flex-wrap items-center justify-between gap-2"
        >
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <h3 class="truncate text-sm font-bold">
                    {labels.title ?? 'Evidence'}
                </h3>
                {#if blindReview}
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-900/40 dark:text-amber-100"
                    >
                        <EyeOff class="size-3" aria-hidden="true" /> Blind
                    </span>
                {/if}
                {#if contextSummary?.simulated}
                    <span
                        class="inline-flex items-center rounded-full border border-amber-300/70 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        Simulated
                    </span>
                {/if}
            </div>
            {#if returnHref()}
                <a
                    class="text-primary focus-visible:ring-ring shrink-0 text-xs font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                    href={returnHref()}
                >
                    {labels.returnAction ?? 'Return to mapping'}
                </a>
            {/if}
        </div>

        {#if blindReview}
            <p
                class="border-amber-300/50 bg-amber-50/70 px-2.5 py-1.5 text-[10px] text-amber-950 dark:bg-amber-950/20 dark:text-amber-100"
                role="status"
            >
                <LockKeyhole class="mr-1 inline size-3" aria-hidden="true" />
                Correctness stays hidden until a decision is saved.
            </p>
        {/if}

        {#if pendingNavigation}
            <div
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-400/80 bg-amber-50 p-3 text-xs text-amber-950 dark:bg-amber-950/40 dark:text-amber-100"
                role="alertdialog"
                aria-labelledby={`${idPrefix}-discard-heading`}
                aria-describedby={`${idPrefix}-discard-prompt`}
            >
                <div class="min-w-0 flex-1">
                    <p id={`${idPrefix}-discard-heading`} class="font-bold">
                        Unsaved review changes
                    </p>
                    <p
                        id={`${idPrefix}-discard-prompt`}
                        class="mt-0.5 text-amber-900/90 dark:text-amber-200/90"
                    >
                        You have unsaved changes in progress. Discard drafts
                        before navigating to {pendingNavigation.label}?
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="border-border bg-background hover:bg-muted focus-visible:ring-ring rounded-md border px-3 py-1.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        onclick={() => (pendingNavigation = null)}
                    >
                        Continue editing
                    </button>
                    <button
                        type="button"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90 focus-visible:ring-ring rounded-md px-3 py-1.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        onclick={confirmDiscard}
                    >
                        Discard changes
                    </button>
                </div>
            </div>
        {/if}

        <section
            class:ticket-collapsed={mobileRailExpanded}
            class="ticket-summary border-border bg-card/80 rounded-xl border p-2.5"
            aria-labelledby={`${idPrefix}-ticket-heading`}
        >
            <h4 id={`${idPrefix}-ticket-heading`} class="sr-only">
                Evidence ticket
            </h4>
            <div class="ticket-grid">
                <div class="ticket-field ticket-identity min-w-0">
                    <span class="ticket-label">Examinee ID</span>
                    {#if identityWriteIn?.region && identityWriteIn?.aligned_url}
                        <div class="identity-write-in-preview mb-1.5 overflow-hidden rounded border border-border bg-background max-w-[14rem]">
                            <div
                                class="relative overflow-hidden"
                                style={`aspect-ratio: ${fieldAspect({ region: identityWriteIn.region })}`}
                            >
                                <img
                                    src={identityWriteIn.aligned_url}
                                    alt="Handwritten Applicant Number crop"
                                    class="absolute max-w-none select-none"
                                    style={fieldImageStyle({ region: identityWriteIn.region })}
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                        </div>
                        {#if identityWriteIn.page_no && normalizedPage?.capture_page_no && identityWriteIn.page_no !== normalizedPage.capture_page_no}
                            <span class="text-[9px] text-muted-foreground">Captured on page {identityWriteIn.page_no}</span>
                        {/if}
                    {/if}
                    {#if identitySummaryItems.length > 0}
                        <div
                            class="identity-write-in"
                            role="group"
                            aria-label="Examinee ID digit boxes"
                        >
                            {#each identitySummaryItems as item, index (rowId(item))}
                                {@const digit = detectedIdentity[index] ?? '·'}
                                <button
                                    type="button"
                                    class="identity-digit-box focus-visible:ring-ring"
                                    aria-label={`Inspect examinee ID digit ${index + 1}, detected ${digit}`}
                                    onclick={() => {
                                        moreOpen = true;
                                        selectSheetItem(item);
                                    }}
                                >
                                    <span>{digit}</span>
                                </button>
                            {/each}
                        </div>
                    {:else}
                        {#if Boolean(identityWriteIn?.region)}
                            <span>Manual write-in ID</span>
                        {:else}
                            <span class="ticket-value font-mono">—</span>
                        {/if}
                    {/if}
                </div>
                <div
                    class="ticket-field ticket-answer-sheet"
                    class:ticket-field-empty={!hasMetadataValue(contextSummary?.answer_sheet_number)}
                >
                    <span class="ticket-label">Answer Sheet No.</span>
                    <span class="ticket-value truncate">
                        {metadataValue(contextSummary?.answer_sheet_number)}
                    </span>
                </div>
                <div class="ticket-field min-w-0">
                    <span class="ticket-label">Applicant</span>
                    {#if candidateNameField?.region && (candidateNameField.aligned_url || normalizedPage?.lazy_asset_urls?.aligned)}
                        <div class="candidate-name-preview mb-1 overflow-hidden rounded border border-border bg-background max-w-[14rem]">
                            <div class="relative overflow-hidden" style={`aspect-ratio: ${fieldAspect(candidateNameField)}`}>
                                <img
                                    src={candidateNameField.aligned_url || normalizedPage.lazy_asset_urls.aligned}
                                    alt="Handwritten Applicant Name crop"
                                    class="absolute max-w-none select-none"
                                    style={fieldImageStyle(candidateNameField)}
                                    loading="lazy"
                                    decoding="async"
                                />
                            </div>
                        </div>
                    {/if}
                    <span class="ticket-value truncate">
                        {metadataValue(contextSummary?.applicant_name)}
                    </span>
                </div>
                <div
                    class="ticket-field ticket-session"
                    class:ticket-field-empty={!hasMetadataValue(contextSummary?.exam_session)}
                >
                    <span class="ticket-label">Exam session</span>
                    <span class="ticket-value truncate">
                        {metadataValue(contextSummary?.exam_session)}
                    </span>
                </div>
                <div
                    class="ticket-field ticket-schedule"
                    class:ticket-field-empty={!hasMetadataValue(contextSummary?.schedule)}
                >
                    <span class="ticket-label">Schedule</span>
                    <span class="ticket-value truncate">
                        {metadataValue(contextSummary?.schedule)}
                    </span>
                </div>
                <div
                    class="ticket-field ticket-room"
                    class:ticket-field-empty={!hasMetadataValue(contextSummary?.room_seat)}
                >
                    <span class="ticket-label">Room / seat</span>
                    <span class="ticket-value truncate">
                        {metadataValue(contextSummary?.room_seat)}
                    </span>
                </div>
                <div
                    class="ticket-field ticket-score"
                    class:ticket-field-empty={scoreDisplay() === '—'}
                >
                    <span class="ticket-label">Score</span>
                    <span class="ticket-value font-mono tabular-nums">
                        {scoreDisplay()}
                    </span>
                </div>
            </div>

            <div class="ticket-mobile-line" aria-hidden={!mobileRailExpanded}>
                <span class="ticket-mobile-id">
                    <span class="ticket-label">Examinee ID</span>
                    <span class="ticket-value font-mono"
                        >{detectedIdentity || '—'}</span
                    >
                </span>
                <span class="ticket-mobile-applicant">
                    <span class="ticket-label">Applicant</span>
                    <span class="ticket-value truncate"
                        >{metadataValue(contextSummary?.applicant_name)}</span
                    >
                </span>
                <span
                    class="ticket-mobile-score"
                    class:ticket-field-empty={scoreDisplay() === '—'}
                >
                    <span class="ticket-label">Score</span>
                    <span class="ticket-value font-mono tabular-nums"
                        >{scoreDisplay()}</span
                    >
                </span>
            </div>

            <details
                class="ticket-more border-border border-t"
                open={moreOpen}
                ontoggle={(event) => (moreOpen = event.currentTarget.open)}
            >
                <summary
                    class="text-primary focus-visible:ring-ring cursor-pointer px-1 pt-2 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                >
                    View more
                </summary>
                <dl class="ticket-optional-details border-border bg-background rounded-lg border p-3 text-xs">
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Answer Sheet No.</dt><dd>{metadataValue(contextSummary?.answer_sheet_number)}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Exam session</dt><dd>{metadataValue(contextSummary?.exam_session)}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Schedule</dt><dd>{metadataValue(contextSummary?.schedule)}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Room / seat</dt><dd>{metadataValue(contextSummary?.room_seat)}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted-foreground">Score</dt><dd>{scoreDisplay()}</dd></div>
                </dl>
                <div class="ticket-more-content mt-2 grid gap-3 lg:grid-cols-2">
                    <section
                        class="identity-details border-border bg-background rounded-lg border p-3"
                        aria-labelledby={`${idPrefix}-identity-details-heading`}
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h5
                                    id={`${idPrefix}-identity-details-heading`}
                                    class="text-[10px] font-bold tracking-wide uppercase"
                                >
                                    Examinee ID evidence
                                </h5>
                                <p
                                    class="text-muted-foreground mt-0.5 text-[10px]"
                                >
                                    {identityConfidence === null
                                        ? 'Confidence unavailable'
                                        : `${Math.round(identityConfidence * 100)}% average confidence`}
                                </p>
                            </div>
                            {#if identitySummaryItems.length > 0}
                                <span class="text-muted-foreground text-[10px]">
                                    {identitySummaryItems.length} digits
                                </span>
                            {/if}
                        </div>
                        {#if identitySummaryItems.length > 0}
                            {#if identityDetailsComplete && isReviewable && identityItemsReviewable}
                                <div
                                    class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end"
                                >
                                    <label
                                        class="min-w-0 flex-1 text-[10px] font-semibold"
                                        for={`${idPrefix}-identity-value`}
                                    >
                                        Corrected value
                                        <input
                                            id={`${idPrefix}-identity-value`}
                                            class="border-border bg-background focus-visible:ring-ring mt-1 block min-h-9 w-full rounded-md border px-3 font-mono text-sm tracking-[0.2em] focus-visible:ring-2 focus-visible:outline-none"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            maxlength={identityItems.length}
                                            value={identityCorrection}
                                            oninput={(event) => {
                                                identityCorrection =
                                                    event.currentTarget.value.replace(
                                                        /\D/g,
                                                        '',
                                                    );
                                                identityCorrectionError = null;
                                            }}
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        class="bg-primary text-primary-foreground focus-visible:ring-ring inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md px-3 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        disabled={savingIdentity ||
                                            identityItems.length === 0}
                                        onclick={saveIdentityCorrection}
                                    >
                                        <Save
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {savingIdentity
                                            ? 'Saving…'
                                            : 'Save corrected ID'}
                                    </button>
                                </div>
                                {#if identityCorrectionError}
                                    <p
                                        class="text-destructive mt-2 text-[10px]"
                                        role="alert"
                                    >
                                        {identityCorrectionError}
                                    </p>
                                {/if}
                                {#if identityDraftCount > 0}
                                    <p
                                        class="text-muted-foreground mt-2 text-[10px]"
                                    >
                                        {identityDraftCount} changed {identityDraftCount ===
                                        1
                                            ? 'digit'
                                            : 'digits'} staged for review.
                                    </p>
                                {/if}
                            {:else}
                                <button
                                    type="button"
                                    class="border-primary/30 text-primary hover:bg-primary/5 focus-visible:ring-ring mt-3 inline-flex min-h-8 items-center justify-center rounded-md border px-2.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                                    onclick={focusIdentity}
                                >
                                    {normalizedPage.area === 'identity'
                                        ? 'Load Identity details'
                                        : 'Open Identity focus'}
                                </button>
                            {/if}

                            {#if normalizedPage.area === 'identity' && identityItems.length > 0}
                                <details
                                    class="border-border mt-3 rounded-md border p-2"
                                >
                                    <summary
                                        class="cursor-pointer text-[10px] font-semibold"
                                    >
                                        Inspect per-digit bubble evidence
                                        <span
                                            class="text-muted-foreground ml-1 font-normal"
                                            >({identityItems.length})</span
                                        >
                                    </summary>
                                    {#if identityDetailsComplete}
                                        <div
                                            class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"
                                        >
                                            {#each identityItems as item, index (rowId(item))}
                                                {@const view = itemView(item)}
                                                <details
                                                    class="border-border bg-muted/15 rounded-md border p-1.5"
                                                >
                                                    <summary
                                                        class="flex cursor-pointer items-center justify-between gap-1 text-[9px] font-semibold"
                                                    >
                                                        <span
                                                            >Digit {index +
                                                                1}</span
                                                        >
                                                        <span class="font-mono"
                                                            >{itemMachine(
                                                                item,
                                                                view,
                                                            )
                                                                ?.selected_option ??
                                                                '—'}</span
                                                        >
                                                    </summary>
                                                    <div
                                                        class="mt-1 max-h-56 overflow-y-auto overscroll-contain"
                                                    >
                                                        <OmrRegionCrop
                                                            item={view}
                                                            machine={view?.machine}
                                                            expected={blindReview
                                                                ? { option: null }
                                                                : view?.expected_evidence}
                                                            geometry={view?.geometry}
                                                            asset={view
                                                                ?.provenance
                                                                ?.asset}
                                                            page_asset={view
                                                                ?.provenance
                                                                ?.page_asset ??
                                                                sheetAsset}
                                                            crop_source={view
                                                                ?.provenance
                                                                ?.crop_source}
                                                            cropUrl={rowUrl(
                                                                item,
                                                            )}
                                                            imageUrl={activeImageUrl}
                                                            region={rowCropRect(
                                                                item,
                                                            )}
                                                            options={view?.options ??
                                                                []}
                                                            detectedOption={view
                                                                ?.machine
                                                                ?.selected_option ??
                                                                null}
                                                            expectedOption={blindReview
                                                                ? null
                                                                : (view?.expected ??
                                                                  null)}
                                                            state={view?.machine
                                                                ?.state ?? null}
                                                            blind={blindReview}
                                                            mode={activeMode}
                                                            alt={`Bubble evidence for examinee ID digit ${index + 1}`}
                                                            title={`Examinee ID digit ${index + 1}`}
                                                            width={120}
                                                            id={`${idPrefix}-identity-crop-${rowId(item)}`}
                                                        />
                                                    </div>
                                                </details>
                                            {/each}
                                        </div>
                                    {:else}
                                        <p
                                            class="text-muted-foreground mt-2 text-[10px]"
                                        >
                                            Choose Identity focus to load all
                                            digit evidence before correcting the
                                            identifier.
                                        </p>
                                    {/if}
                                </details>
                            {/if}
                        {:else}
                            <p class="text-muted-foreground mt-3 text-[10px]">
                                Examinee ID evidence is not present in this
                                capture projection.
                            </p>
                        {/if}
                    </section>

                    <section
                        class="document-summary border-border bg-background rounded-lg border p-3"
                        aria-labelledby={`${idPrefix}-document-fields-heading`}
                    >
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <h5
                                    id={`${idPrefix}-document-fields-heading`}
                                    class="text-[10px] font-bold tracking-wide uppercase"
                                >
                                    Document fields
                                </h5>
                                <p
                                    class="text-muted-foreground mt-0.5 text-[10px]"
                                >
                                    Image-only crops
                                </p>
                            </div>
                            <span class="text-muted-foreground text-[10px]"
                                >{documentFields.length}</span
                            >
                        </div>
                        {#if documentFields.length > 0}
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                {#each documentFields as field (field.id ?? field.label)}
                                    <figure
                                        class="border-border bg-muted/20 overflow-hidden rounded-md border"
                                    >
                                        <figcaption
                                            class="border-border bg-background border-b px-2 py-1 text-[9px] font-semibold"
                                        >
                                            {field.label}
                                        </figcaption>
                                        {#if normalizedPage.lazy_asset_urls.aligned && field.region}
                                            <div
                                                class="relative overflow-hidden"
                                                style={`aspect-ratio:${fieldAspect(field)}`}
                                            >
                                                <img
                                                    src={normalizedPage
                                                        .lazy_asset_urls
                                                        .aligned}
                                                    alt={`Image crop for ${field.label}; value not transcribed`}
                                                    class="absolute max-w-none select-none"
                                                    style={fieldImageStyle(
                                                        field,
                                                    )}
                                                    loading="lazy"
                                                    decoding="async"
                                                />
                                            </div>
                                        {:else}
                                            <div
                                                class="text-muted-foreground flex min-h-12 items-center justify-center p-2 text-center text-[9px]"
                                            >
                                                Image unavailable
                                            </div>
                                        {/if}
                                    </figure>
                                {/each}
                            </div>
                        {:else}
                            <p class="text-muted-foreground mt-3 text-[10px]">
                                No document field crops were supplied.
                            </p>
                        {/if}
                    </section>
                </div>
            </details>
        </section>

        <div class="workbench-grid">
            <section
                class="canvas-column min-w-0"
                aria-labelledby={`${idPrefix}-canvas-heading`}
            >
                <div
                    class="canvas-toolbar border-border bg-background flex min-h-10 items-center justify-between gap-2 rounded-t-xl border border-b-0 px-3 py-2"
                >
                    <h4
                        id={`${idPrefix}-canvas-heading`}
                        class="truncate text-xs font-bold sm:text-sm"
                    >
                        Sheet canvas
                    </h4>
                    {#if blindReview}
                        <span
                            class="text-muted-foreground inline-flex items-center gap-1 text-[10px]"
                        >
                            <LockKeyhole class="size-3" aria-hidden="true" />
                            Blind
                        </span>
                    {/if}
                </div>

                {#if activeMode === 'stencil' && blindReview}
                    <p
                        class="border-amber-300/50 bg-amber-50 px-3 py-2 text-[10px] text-amber-950 dark:bg-amber-950/20 dark:text-amber-100"
                        role="status"
                    >
                        <LockKeyhole
                            class="mr-1 inline size-3.5"
                            aria-hidden="true"
                        />
                        Stencil is unavailable during blind review.
                    </p>
                {:else if activeMode === 'stencil' && !stencilTruthAvailable}
                    <p
                        class="border-amber-300/50 bg-amber-50 px-3 py-2 text-[10px] text-amber-950 dark:bg-amber-950/20 dark:text-amber-100"
                        role="status"
                    >
                        <CircleDotDashed
                            class="mr-1 inline size-3.5"
                            aria-hidden="true"
                        />
                        Stencil is unavailable without expected bubble geometry.
                    </p>
                {/if}

                <OmrSheetEvidenceCanvas
                    sheetMap={normalizedPage.sheet_map}
                    imageUrl={activeImageUrl}
                    mode={activeMode}
                    selectedId={selectedSheetItemId}
                    blind={blindReview}
                    onSelect={selectSheetItem}
                />
            </section>

            <aside
                class:mobile-rail-expanded={mobileRailExpanded}
                class="review-rail border-border bg-card min-w-0 rounded-xl border"
                aria-label="Evidence review queue"
                data-review-rail
            >
                <div id={`${idPrefix}-rail-body`} class="review-rail-body">
                    <div
                        class="rail-controls border-border bg-card flex min-h-11 shrink-0 items-center gap-1.5 overflow-x-auto border-b px-2 py-1.5 sm:px-3"
                        role="toolbar"
                        aria-label="Evidence queue controls"
                    >
                        {#if normalizedPage?.groups?.length > 1}
                            <div class="queue-area-select flex items-center gap-1">
                                <label for={`${idPrefix}-area-filter`} class="sr-only">Filter by area</label>
                                <select
                                    id={`${idPrefix}-area-filter`}
                                    class="border-border bg-background text-foreground h-7 rounded border px-1.5 py-0.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    value={normalizedPage.area ?? 'all'}
                                    onchange={(e) => selectArea(e.currentTarget.value)}
                                >
                                    {#each normalizedPage.groups as group (group.key)}
                                        <option value={group.key}>
                                            {group.label} ({group.total})
                                        </option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                        <div
                            class="queue-filter flex shrink-0 items-center gap-0.5"
                            role="group"
                            aria-label="Review queue filter"
                        >
                            <button
                                type="button"
                                class="focus-visible:ring-ring inline-flex min-h-7 items-center gap-0.5 rounded px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none {queueFilter ===
                                'all'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background'}"
                                aria-pressed={queueFilter === 'all'}
                                onclick={() => selectFilter('all')}
                            >
                                All <span class="text-[9px] opacity-70"
                                    >{normalizedPage.total}</span
                                >
                            </button>
                            <button
                                type="button"
                                class="focus-visible:ring-ring inline-flex min-h-7 items-center gap-0.5 rounded px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none {queueFilter ===
                                'needs_review'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-background'}"
                                aria-pressed={queueFilter === 'needs_review'}
                                disabled={needsReviewCount === 0}
                                onclick={() => selectFilter('needs_review')}
                            >
                                Needs review <span class="text-[9px] opacity-70"
                                    >{needsReviewCount}</span
                                >
                            </button>
                        </div>

                        <div
                            class="mode-segmented border-border bg-muted/35 flex shrink-0 items-center gap-0.5 rounded-md border p-0.5"
                            role="tablist"
                            aria-label="Evidence display mode"
                        >
                            {#each [{ key: 'raw', label: 'Raw', icon: ImageIcon }, { key: 'stencil', label: 'Stencil', icon: ScanLine }, { key: 'markings', label: 'Markings', icon: Layers3 }] as mode (mode.key)}
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={activeMode === mode.key}
                                    class="focus-visible:ring-ring inline-flex min-h-7 items-center gap-1 rounded px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none {activeMode ===
                                    mode.key
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground'}"
                                    aria-label={`${mode.label} evidence`}
                                    onclick={() => selectMode(mode.key)}
                                >
                                    <svelte:component
                                        this={mode.icon}
                                        class="size-3"
                                        aria-hidden="true"
                                    />
                                    <span>{mode.label}</span>
                                </button>
                            {/each}
                        </div>

                        <button
                            type="button"
                            class="rail-expand-button border-border bg-background hover:bg-muted focus-visible:ring-ring ml-auto inline-flex size-8 shrink-0 items-center justify-center rounded-md border focus-visible:ring-2 focus-visible:outline-none lg:hidden"
                            aria-expanded={mobileRailExpanded}
                            aria-controls={`${idPrefix}-rail-body`}
                            aria-label={mobileRailExpanded
                                ? 'Collapse evidence review queue'
                                : 'Expand evidence review queue'}
                            onclick={toggleMobileRail}
                        >
                            {#if mobileRailExpanded}
                                <ChevronDown
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            {:else}
                                <ChevronUp
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                            {/if}
                        </button>
                    </div>
                    <div class="queue-scroll px-2 py-2">
                        {#if visibleItems.length === 0}
                            <div
                                class="border-border text-muted-foreground rounded-md border border-dashed p-3 text-center text-[10px]"
                                role="status"
                            >
                                {labels.noRows ??
                                    'No evidence rows match this view.'}
                            </div>
                        {:else}
                            <div
                                class="queue-table"
                                class:queue-read-only={!isReviewable}
                                role="table"
                                aria-label="Evidence review queue"
                            >
                                <div
                                    class="queue-header text-muted-foreground"
                                    role="row"
                                >
                                    <span role="columnheader">Item</span>
                                    <span role="columnheader">Evidence</span>
                                    <span role="columnheader">Result</span>
                                    {#if isReviewable}
                                        <span role="columnheader" class="sr-only"
                                            >Action</span
                                        >
                                    {/if}
                                </div>
                                {#each visibleItems as item, index (rowId(item))}
                                    {@const itemId = rowId(item)}
                                    {@const normalizedItem = itemView(item)}
                                    {@const displayedReview = normalizedItem
                                        ? reviewForDisplay(item, normalizedItem)
                                        : null}
                                    {@const problem = normalizedItem
                                        ? problemLabel(normalizedItem)
                                        : null}
                                    {@const selected =
                                        selectedSheetItemId === itemId}
                                    {@const prevItem = index > 0 ? visibleItems[index - 1] : null}
                                    {@const isNewArea = index === 0 || item.area_key !== prevItem?.area_key}
                                    {#if isNewArea && item.area_key && item.group_type !== 'identity'}
                                        {@const areaGroup = normalizedPage?.groups?.find(g => g.key === item.area_key || g.area_key === item.area_key)}
                                        <div class="queue-section-header border-y border-border/80 bg-muted/40 px-2.5 py-1 text-[10px] font-bold text-foreground flex items-center justify-between col-span-full" role="row" aria-label={`${areaGroup?.label ?? item.area_key} area, code ${item.area_key}`}>
                                            <span role="columnheader" title={item.area_key}>{areaGroup?.label ?? item.area_key}</span>
                                        </div>
                                    {/if}
                                    <div
                                        id={`${idPrefix}-row-${itemId}`}
                                        class:queue-selected={selected}
                                        class="queue-row border-border/70"
                                        role="row"
                                        aria-selected={selected}
                                    >
                                        <button
                                            type="button"
                                            class="queue-item focus-visible:ring-ring min-w-0 text-left focus-visible:ring-2 focus-visible:outline-none"
                                            role="cell"
                                            onclick={() => focusRow(item)}
                                        >
                                            <span
                                                class="block truncate text-[10px] font-semibold"
                                            >
                                                {rowDisplayLabel(
                                                    item,
                                                    normalizedItem,
                                                )}
                                            </span>
                                            {#if problem}
                                                <span
                                                    class="text-destructive block text-[9px] font-semibold"
                                                    >{problem}</span
                                                >
                                            {:else if displayedReview}
                                                <span
                                                    class="block text-[9px] font-semibold text-emerald-700 dark:text-emerald-300"
                                                    >Reviewed</span
                                                >
                                            {/if}
                                        </button>
                                        <div class="queue-bubbles" role="cell">
                                            <OmrRegionCrop
                                                item={normalizedItem}
                                                machine={normalizedItem?.machine}
                                                expected={blindReview &&
                                                !revealedItems.has(itemId)
                                                    ? { option: null }
                                                    : normalizedItem?.expected_evidence}
                                                geometry={normalizedItem?.geometry}
                                                review={reviewForDisplay(
                                                    item,
                                                    normalizedItem,
                                                )}
                                                asset={normalizedItem
                                                    ?.provenance?.asset}
                                                page_asset={normalizedItem
                                                    ?.provenance?.page_asset ??
                                                    sheetAsset}
                                                crop_source={normalizedItem
                                                    ?.provenance?.crop_source}
                                                cropUrl={rowUrl(item)}
                                                imageUrl={activeImageUrl}
                                                region={rowCropRect(item)}
                                                options={normalizedItem?.options ??
                                                    []}
                                                detectedOption={normalizedItem
                                                    ?.machine
                                                    ?.selected_option ?? null}
                                                expectedOption={blindReview &&
                                                !revealedItems.has(itemId)
                                                    ? null
                                                    : (normalizedItem?.expected ??
                                                      null)}
                                                state={normalizedItem?.machine
                                                    ?.state ??
                                                    item.state ??
                                                    null}
                                                blind={blindReview &&
                                                !revealedItems.has(itemId)}
                                                mode={activeMode}
                                                alt={`Scanned bubbles for ${normalizedItem?.label ?? item.item_id ?? 'item'}`}
                                                title={normalizedItem?.label ??
                                                    item.item_id ??
                                                    'Scanned bubbles'}
                                                width={112}
                                                id={`${idPrefix}-crop-${itemId}`}
                                            />
                                        </div>
                                        <div
                                            class="queue-result min-w-0"
                                            role="cell"
                                            aria-label={`Detected ${rowDetected(item)}; expected ${rowExpected(item)}`}
                                        >
                                            <span
                                                class="block truncate font-mono text-[10px]"
                                                >{rowDetected(item)}</span
                                            >
                                            <span
                                                class="text-muted-foreground block truncate text-[9px]"
                                                >Expected: {rowExpected(
                                                    item,
                                                )}</span
                                            >
                                            {#if normalizedItem?.score != null}
                                                <span
                                                    class="text-muted-foreground block text-[9px]"
                                                    >{normalizedItem.score} pts</span
                                                >
                                            {/if}
                                        </div>
                                        {#if isReviewable && item.reviewable === true}
                                            <button
                                                type="button"
                                                class="queue-edit text-primary focus-visible:ring-ring rounded px-1.5 py-1 text-[10px] font-bold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none"
                                                aria-label={`${rowActionLabel(item, normalizedItem)} ${rowDisplayLabel(item, normalizedItem)} ${item.item_id ?? ''}`.trim()}
                                                onclick={(event) => {
                                                    event.stopPropagation();
                                                    editRow(item);
                                                }}
                                            >
                                                {rowActionLabel(
                                                    item,
                                                    normalizedItem,
                                                )}
                                            </button>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    {#if selectedEditorItem}
                        {@const editorId = rowId(selectedEditorItem)}
                        {@const editorView = selectedEditorView}
                        {@const draft = drafts[editorId] ?? null}
                        {@const saveError = saveErrors[editorId] ?? null}
                        {@const displayedReview = editorView
                            ? reviewForDisplay(selectedEditorItem, editorView)
                            : null}
                        {@const unavailableReason = editorView
                            ? itemUnavailableReason(
                                  selectedEditorItem,
                                  editorView,
                              )
                            : 'missing-view'}
                        <section
                            class="inline-editor border-border bg-background border-t px-3 py-3"
                            aria-label={`Edit ${rowDisplayLabel(selectedEditorItem, editorView)}`}
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-bold">
                                        {rowDisplayLabel(
                                            selectedEditorItem,
                                            editorView,
                                        )}
                                    </p>
                                    <p
                                        class="text-muted-foreground mt-0.5 text-[10px]"
                                    >
                                        Detected {rowDetected(
                                            selectedEditorItem,
                                        )} · Expected {rowExpected(
                                            selectedEditorItem,
                                        )}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex size-7 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
                                    aria-label="Close inline editor"
                                    onclick={closeEditor}
                                >
                                    <X class="size-3.5" aria-hidden="true" />
                                </button>
                            </div>

                            {#if unavailableReason}
                                <div
                                    class="border-destructive/30 bg-destructive/5 text-destructive mt-2 rounded-md border p-2 text-[10px]"
                                    role="alert"
                                >
                                    <p class="font-semibold">
                                        {unavailableReasonPresentation(unavailableReason).message}
                                    </p>
                                    <details class="mt-0.5">
                                        <summary class="cursor-pointer text-muted-foreground">Technical details</summary>
                                        <span class="text-muted-foreground">{unavailableReasonPresentation(unavailableReason).technicalDetail}</span>
                                    </details>
                                    <p class="mt-0.5">
                                        Review save is blocked until provenance
                                        and geometry are available.
                                    </p>
                                </div>
                            {/if}

                            {#if displayedReview}
                                <div
                                    class="mt-2 rounded-md border border-emerald-300/50 bg-emerald-50/50 p-2 text-[10px] dark:bg-emerald-950/20"
                                    aria-label="Current review"
                                >
                                    <p class="font-semibold">
                                        Current review · {reviewLabel(
                                            displayedReview.decision,
                                        )}
                                    </p>
                                    <p class="text-muted-foreground mt-1">
                                        {displayedReview.selected_option ??
                                            'Blank/unresolved'} · {displayedReview
                                            .reviewer?.display_name ??
                                            'Reviewer unavailable'} · {displayedReview.reviewed_at ??
                                            'Timestamp unavailable'}
                                    </p>
                                    <p class="text-muted-foreground mt-1">
                                        Reason {displayedReview.reason_code ??
                                            'Not required'} · evidence {shortHash(
                                            displayedReview.evidence_hash,
                                        )}
                                    </p>
                                </div>
                            {/if}

                            {#if reviewHistory(selectedEditorItem, editorView).length > 0}
                                <details
                                    class="border-border mt-2 rounded-md border p-2 text-[10px]"
                                >
                                    <summary
                                        class="cursor-pointer font-semibold"
                                    >
                                        Review history ({reviewHistory(
                                            selectedEditorItem,
                                            editorView,
                                        ).length})
                                    </summary>
                                    <ol
                                        class="mt-2 space-y-1.5"
                                        aria-label="Immutable review history"
                                    >
                                        {#each reviewHistory(selectedEditorItem, editorView) as historyEntry (historyEntry.revision ?? `${historyEntry.reviewed_at}-${historyEntry.decision}`)}
                                            <li
                                                class="border-border/70 border-l-2 pl-2"
                                            >
                                                <strong
                                                    >Revision {historyEntry.revision ??
                                                        '—'} · {reviewLabel(
                                                        historyEntry.decision,
                                                    )}</strong
                                                >
                                                <span
                                                    class="text-muted-foreground block"
                                                >
                                                    {historyEntry.reviewer
                                                        ?.display_name ??
                                                        'Reviewer unavailable'} ·
                                                    {historyEntry.reviewed_at ??
                                                        'Timestamp unavailable'} ·
                                                    {shortHash(
                                                        historyEntry.evidence_hash,
                                                    )}
                                                </span>
                                                {#if historyEntry.stale}
                                                    <span class="text-amber-700"
                                                        >Stale history</span
                                                    >
                                                {/if}
                                            </li>
                                        {/each}
                                    </ol>
                                </details>
                            {/if}

                            {#if isReviewable && editorView?.reviewable === true}
                                <div
                                    class="review-editor-controls border-primary/20 bg-primary/5 mt-2 rounded-md border p-2.5"
                                    aria-label="Review decision controls"
                                >
                                    <p
                                        class="text-muted-foreground mb-2 text-[10px]"
                                    >
                                        Choose a decision, then save explicitly.
                                        Machine evidence remains unchanged.
                                    </p>
                                    <div class="grid grid-cols-2 gap-1.5">
                                        <button
                                            type="button"
                                            class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                            disabled={editorView?.machine
                                                ?.state !== 'marked' ||
                                                savingItemId === editorId}
                                            aria-pressed={draft?.decision ===
                                                'confirm_machine'}
                                            onclick={() =>
                                                setDecision(
                                                    selectedEditorItem,
                                                    'confirm_machine',
                                                )}
                                        >
                                            <Check
                                                class="size-3"
                                                aria-hidden="true"
                                            /> Use detected
                                        </button>
                                        <button
                                            type="button"
                                            class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                            disabled={savingItemId === editorId}
                                            aria-pressed={draft?.decision ===
                                                'set_option'}
                                            onclick={() =>
                                                setDecision(
                                                    selectedEditorItem,
                                                    'set_option',
                                                )}
                                        >
                                            Choose answer
                                        </button>
                                        <button
                                            type="button"
                                            class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                            disabled={savingItemId === editorId}
                                            aria-pressed={draft?.decision ===
                                                'blank'}
                                            onclick={() =>
                                                setDecision(
                                                    selectedEditorItem,
                                                    'blank',
                                                )}
                                        >
                                            Mark blank
                                        </button>
                                        <button
                                            type="button"
                                            class="border-border bg-background focus-visible:ring-ring inline-flex min-h-8 items-center justify-center gap-1 rounded-md border px-2 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                            disabled={savingItemId === editorId}
                                            aria-pressed={draft?.decision ===
                                                'unresolved'}
                                            onclick={() =>
                                                setDecision(
                                                    selectedEditorItem,
                                                    'unresolved',
                                                )}
                                        >
                                            <CircleAlert
                                                class="size-3"
                                                aria-hidden="true"
                                            /> Unresolved
                                        </button>
                                    </div>

                                    {#if draft?.decision === 'set_option'}
                                        <fieldset class="mt-2">
                                            <legend
                                                class="text-muted-foreground text-[10px] font-semibold"
                                                >Pinned options</legend
                                            >
                                            <div
                                                class="mt-1 flex flex-wrap gap-1.5"
                                                role="radiogroup"
                                                aria-label="Pinned options"
                                            >
                                                {#each editorView?.options ?? [] as option (option.code)}
                                                    <label
                                                        class="border-border bg-background inline-flex cursor-pointer items-center gap-1 rounded-md border px-2 py-1 text-[10px]"
                                                    >
                                                        <input
                                                            type="radio"
                                                            name={`${idPrefix}-option-${editorId}`}
                                                            value={option.code}
                                                            checked={draft.selected_option ===
                                                                option.code}
                                                            onchange={() =>
                                                                chooseOption(
                                                                    selectedEditorItem,
                                                                    option.code,
                                                                )}
                                                        />
                                                        <span class="font-mono"
                                                            >{option.code}</span
                                                        >
                                                    </label>
                                                {/each}
                                            </div>
                                        </fieldset>
                                    {/if}

                                    {#if draft}
                                        <div class="mt-2 grid gap-2">
                                            <label
                                                class="text-[10px] font-semibold"
                                                for={`${idPrefix}-reason-${editorId}`}
                                            >
                                                Reason {draftNeedsReason(
                                                    selectedEditorItem,
                                                    draft,
                                                    editorView,
                                                )
                                                    ? '(required)'
                                                    : '(optional)'}
                                            </label>
                                            <select
                                                id={`${idPrefix}-reason-${editorId}`}
                                                class="border-border bg-background rounded-md border px-2 py-1.5 text-[10px]"
                                                value={draft.reason_code ?? ''}
                                                onchange={(event) =>
                                                    setDraft(
                                                        selectedEditorItem,
                                                        {
                                                            ...draft,
                                                            reason_code:
                                                                event
                                                                    .currentTarget
                                                                    .value ||
                                                                null,
                                                        },
                                                    )}
                                            >
                                                <option value=""
                                                    >Select a reason</option
                                                >
                                                {#each reasons as reason (reason.code)}
                                                    <option value={reason.code}
                                                        >{reason.label}</option
                                                    >
                                                {/each}
                                            </select>
                                            <label
                                                class="text-[10px] font-semibold"
                                                for={`${idPrefix}-note-${editorId}`}
                                            >
                                                Note {draftNeedsNote(
                                                    selectedEditorItem,
                                                    draft,
                                                    editorView,
                                                )
                                                    ? '(required)'
                                                    : '(optional)'}
                                            </label>
                                            <textarea
                                                id={`${idPrefix}-note-${editorId}`}
                                                class="border-border bg-background min-h-16 rounded-md border px-2 py-1.5 text-[10px]"
                                                maxlength={noteMax}
                                                value={draft.note ?? ''}
                                                oninput={(event) =>
                                                    setDraft(
                                                        selectedEditorItem,
                                                        {
                                                            ...draft,
                                                            note: event
                                                                .currentTarget
                                                                .value,
                                                        },
                                                    )}
                                                placeholder="Keep the explanation concise"
                                            ></textarea>
                                            <span class="text-muted-foreground"
                                                >{String(draft.note ?? '')
                                                    .length}/{noteMax}</span
                                            >
                                        </div>
                                        {#if saveError}
                                            <p
                                                class="text-destructive mt-2 text-[10px]"
                                                role="alert"
                                            >
                                                {saveError.kind}: {saveError.detail}
                                            </p>
                                        {/if}
                                        <button
                                            type="button"
                                            class="bg-primary text-primary-foreground focus-visible:ring-ring mt-2 inline-flex min-h-9 w-full items-center justify-center gap-1 rounded-md px-3 py-1.5 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                            disabled={savingItemId === editorId}
                                            onclick={() =>
                                                saveItem(selectedEditorItem)}
                                        >
                                            <Save
                                                class="size-3.5"
                                                aria-hidden="true"
                                            />
                                            {savingItemId === editorId
                                                ? 'Saving…'
                                                : 'Save'}
                                        </button>
                                        <p
                                            class="text-muted-foreground text-[10px]"
                                            aria-live="polite"
                                        >
                                            Reviewed appears only after
                                            authoritative server state returns.
                                        </p>
                                    {/if}
                                </div>
                            {:else}
                                <p
                                    class="text-muted-foreground border-border mt-2 rounded-md border border-dashed p-2 text-[10px]"
                                >
                                    {normalizedPage.lifecycle &&
                                    normalizedPage.lifecycle !== 'open'
                                        ? 'This evidence context is closed; history is read-only.'
                                        : 'Read-only evidence. Review controls appear only when an authorized save callback is supplied.'}
                                </p>
                            {/if}
                        </section>
                    {/if}

                    {#if normalizedPage.page_count > 1}
                        <nav
                            class="border-border flex items-center justify-between gap-2 border-t px-3 py-2"
                            aria-label={labels.paginationLabel ??
                                'Evidence pagination'}
                        >
                            <button
                                type="button"
                                class="border-border focus-visible:ring-ring inline-flex min-h-8 items-center rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                disabled={loading ||
                                    normalizedPage.page <= 1 ||
                                    !onPageChange}
                                aria-label="Previous evidence page"
                                onclick={() =>
                                    selectPage(normalizedPage.page - 1)}
                                >Previous</button
                            >
                            <div
                                class="flex items-center gap-1"
                                aria-label={pageLabel()}
                            >
                                {#each paginationPages() as pageNumber (pageNumber)}
                                    <button
                                        type="button"
                                        class="focus-visible:ring-ring inline-flex size-7 items-center justify-center rounded-md text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none {pageNumber ===
                                        normalizedPage.page
                                            ? 'bg-primary text-primary-foreground'
                                            : 'border-border bg-background hover:bg-muted border'}"
                                        aria-label={`Evidence page ${pageNumber}`}
                                        aria-current={pageNumber ===
                                        normalizedPage.page
                                            ? 'page'
                                            : undefined}
                                        onclick={() => selectPage(pageNumber)}
                                        >{pageNumber}</button
                                    >
                                {/each}
                            </div>
                            <button
                                type="button"
                                class="border-border focus-visible:ring-ring inline-flex min-h-8 items-center rounded-md border px-2.5 py-1 text-[10px] font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                disabled={loading ||
                                    normalizedPage.page >=
                                        normalizedPage.page_count ||
                                    !onPageChange}
                                aria-label="Next evidence page"
                                onclick={() =>
                                    selectPage(normalizedPage.page + 1)}
                                >Next</button
                            >
                        </nav>
                    {/if}
                </div>
            </aside>
        </div>
    {/if}
</div>

<style>
    .evidence-heading {
        display: none;
    }
    .omr-evidence-panel {
        container-type: inline-size;
    }

    .ticket-summary {
        position: relative;
    }

    .ticket-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 0.45rem;
    }

    .ticket-field {
        min-width: 0;
        min-height: 2.25rem;
        padding: 0.35rem 0.5rem;
        border: 1px solid var(--border);
        border-radius: 0.45rem;
        background: var(--background);
    }
    .ticket-field-empty {
        display: none;
    }

    .ticket-field:nth-child(1) {
        grid-column: span 3;
    }

    .ticket-field:nth-child(2) {
        grid-column: span 2;
    }

    .ticket-field:nth-child(3) {
        grid-column: span 3;
    }

    .ticket-field:nth-child(4) {
        grid-column: span 2;
    }

    .ticket-field:nth-child(5),
    .ticket-field:nth-child(6) {
        grid-column: span 3;
    }

    .ticket-field:nth-child(7) {
        grid-column: 11 / span 2;
        grid-row: 1;
        border-color: color-mix(in oklab, var(--primary) 35%, var(--border));
    }

    .ticket-label,
    .ticket-value {
        display: block;
    }

    .ticket-label {
        margin-bottom: 0.2rem;
        color: var(--muted-foreground);
        font-size: 0.55rem;
        font-weight: 750;
        letter-spacing: 0.065em;
        line-height: 1;
        text-transform: uppercase;
    }

    .ticket-value {
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .ticket-score .ticket-value {
        color: var(--primary);
        font-size: 0.95rem;
    }

    .ticket-mobile-line {
        display: none;
    }

    .ticket-more-content {
        position: absolute;
        z-index: 30;
        top: calc(100% + 0.4rem);
        right: 0;
        left: 0;
        max-height: min(70vh, 44rem);
        padding: 0.75rem;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 0.75rem;
        background: var(--background);
        box-shadow: 0 1rem 2.5rem
            color-mix(in oklab, var(--foreground) 18%, transparent);
    }

    .workbench-grid {
        display: grid;
        grid-template-columns: minmax(0, 16fr) minmax(23rem, 9fr);
        align-items: start;
        gap: 0.75rem;
    }

    .review-rail {
        position: sticky;
        top: 0.5rem;
        max-height: calc(94vh - 7rem);
        overflow: hidden;
    }

    .review-rail-body {
        display: flex;
        min-height: 0;
        flex: 1 1 auto;
        max-height: calc(94vh - 7rem);
        flex-direction: column;
        overflow: hidden;
        overscroll-behavior: contain;
    }

    .rail-controls {
        position: sticky;
        z-index: 12;
        top: 0;
    }

    .queue-scroll {
        min-height: 0;
        flex: 1 1 auto;
        overflow: auto;
        overscroll-behavior: contain;
    }

    .queue-table {
        min-width: 25rem;
    }

    .queue-header,
    .queue-row {
        display: grid;
        grid-template-columns:
            6.5rem minmax(7.5rem, 1fr) minmax(4.8rem, 0.65fr) 3.5rem;
        align-items: center;
        gap: 0.45rem;
    }

    .queue-table.queue-read-only .queue-header,
    .queue-table.queue-read-only .queue-row {
        grid-template-columns: 7.5rem minmax(7.5rem, 1fr) minmax(4.8rem, 0.65fr);
    }

    .queue-section-header {
        grid-column: 1 / -1;
    }
    .queue-header {
        position: sticky;
        z-index: 10;
        top: -0.5rem;
        padding: 0.35rem 0.4rem;
        border-bottom: 1px solid var(--border);
        background: var(--card);
        font-size: 0.56rem;
        font-weight: 750;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .queue-row {
        min-height: 4.25rem;
        padding: 0.4rem;
        border-bottom-width: 1px;
        transition:
            background-color 120ms ease,
            box-shadow 120ms ease;
    }

    .queue-row:last-child {
        border-bottom: 0;
    }

    .queue-row:hover,
    .queue-row.queue-selected {
        background: color-mix(in oklab, var(--primary) 6%, var(--background));
    }

    .queue-row.queue-selected {
        box-shadow: inset 2px 0 0 var(--primary);
    }

    .queue-bubbles,
    .queue-result {
        min-width: 0;
    }

    .queue-bubbles :global(.region-crop) {
        width: 100%;
        max-width: 8.5rem;
    }

    .inline-editor {
        z-index: 14;
        max-height: 18rem;
        flex: 0 0 auto;
        overflow-y: auto;
        box-shadow: 0 -0.75rem 1.5rem
            color-mix(in oklab, var(--foreground) 10%, transparent);
    }

    .identity-write-in {
        display: flex;
        flex-wrap: nowrap;
        gap: 0.2rem;
    }

    .identity-digit-box {
        display: inline-flex;
        min-width: 1.4rem;
        height: 1.55rem;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border);
        border-radius: 0.2rem;
        background: var(--background);
        font-family: var(--font-mono, ui-monospace, monospace);
        font-size: 0.68rem;
        font-weight: 700;
    }

    .identity-digit-box:hover {
        border-color: var(--primary);
        background: color-mix(in oklab, var(--primary) 8%, var(--background));
    }

    .identity-digit-box:focus-visible {
        outline: 2px solid var(--ring);
        outline-offset: 2px;
    }

    @media (max-width: 1023px) {
        .omr-evidence-panel {
            gap: 0.5rem;
        }

        .evidence-heading {
            display: none;
        }

        .ticket-summary {
            position: sticky;
            z-index: 20;
            top: -0.5rem;
            padding: 0.45rem;
            border-radius: 0.55rem;
            background: var(--background);
        }

        .ticket-grid {
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.35fr) minmax(
                    4.5rem,
                    0.7fr
                );
            gap: 0.35rem;
        }

        .ticket-field {
            display: none;
            min-height: 2.25rem;
            padding: 0.35rem 0.5rem;
        }

        .ticket-field:nth-child(1),
        .ticket-field:nth-child(2),
        .ticket-field:nth-child(3),
        .ticket-field:nth-child(7) {
            display: block;
        }

        .ticket-field:nth-child(1),
        .ticket-field:nth-child(3),
        .ticket-field:nth-child(7) {
            grid-column: auto;
            grid-row: 1;
        }

        .ticket-field:nth-child(2) {
            grid-column: 1 / -1;
            grid-row: 2;
            min-height: 2.5rem;
        }
        .ticket-field-empty {
            display: none !important;
        }

        .ticket-identity .identity-write-in {
            gap: 0;
        }

        .ticket-identity .identity-digit-box {
            min-width: 0.78rem;
            height: 1rem;
            border: 0;
            font-size: 0.68rem;
        }

        .ticket-score .ticket-value {
            font-size: 0.8rem;
        }

        .ticket-summary.ticket-collapsed {
            min-height: 2.65rem;
            padding: 0.4rem 0.5rem;
        }

        .ticket-collapsed .ticket-grid,
        .ticket-collapsed .ticket-more {
            display: none;
        }

        .ticket-collapsed .ticket-mobile-line {
            display: grid;
            grid-template-columns: minmax(4.75rem, 0.8fr) minmax(0, 1.5fr) auto;
            align-items: center;
            gap: 0.5rem;
        }

        .ticket-mobile-line .ticket-label {
            display: none;
        }

        .ticket-mobile-line .ticket-value {
            font-size: 0.68rem;
            line-height: 1;
        }

        .ticket-mobile-score {
            color: var(--primary);
        }

        .ticket-more-content {
            position: fixed;
            top: 3.25rem;
            right: 0.5rem;
            bottom: 4rem;
            left: 0.5rem;
            max-height: none;
        }

        .workbench-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .review-rail {
            position: sticky;
            z-index: 24;
            bottom: -0.5rem;
            height: 46px;
            max-height: 46px;
            min-height: 46px;
            border-radius: 0.65rem 0.65rem 0 0;
            box-shadow: 0 -0.35rem 1rem
                color-mix(in oklab, var(--foreground) 9%, transparent);
        }

        .review-rail-body {
            height: 100%;
            max-height: none;
        }

        .review-rail:not(.mobile-rail-expanded) .queue-scroll,
        .review-rail:not(.mobile-rail-expanded) .inline-editor,
        .review-rail:not(.mobile-rail-expanded) nav {
            display: none;
        }

        .review-rail.mobile-rail-expanded {
            height: 52dvh;
            max-height: 52dvh;
            min-height: 52dvh;
        }

        .review-rail.mobile-rail-expanded .queue-scroll {
            min-height: 0;
        }

        .rail-controls {
            min-height: 2.8rem;
            padding-block: 0.25rem;
        }

        .mode-segmented span {
            display: none;
        }

        .mode-segmented button {
            min-width: 1.8rem;
            padding-inline: 0.4rem;
        }

        .queue-table {
            min-width: 0;
            width: 100%;
        }

        .queue-header,
        .queue-row {
            grid-template-columns:
                minmax(0, 1fr) minmax(0, 1.4fr) minmax(0, 1fr)
                minmax(1.6rem, auto);
            gap: 0.3rem;
        }
        .queue-table.queue-read-only .queue-header,
        .queue-table.queue-read-only .queue-row {
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) minmax(
                    0,
                    1fr
                );
        }

        .inline-editor {
            position: absolute;
            inset: 46px 0 0;

            max-height: calc(52dvh - 46px);
            background: var(--background);
        }
    }

    @media (max-width: 420px) {
        .ticket-summary {
            margin-inline: -0.15rem;
        }

        .ticket-field:nth-child(1) {
            grid-column: span 1;
        }

        .rail-controls {
            gap: 0.2rem;
            padding-inline: 0.25rem;
        }

        .queue-filter button {
            padding-inline: 0.4rem;
        }

        .rail-expand-button {
            margin-left: 0.1rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        :global([data-omr-evidence-panel]) * {
            transition-duration: 0ms !important;
            animation-duration: 0ms !important;
        }
    }
</style>
