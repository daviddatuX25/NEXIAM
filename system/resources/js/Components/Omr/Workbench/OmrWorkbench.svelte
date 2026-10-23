<script lang="ts">
    import { ArrowLeft, FileImage, LayoutGrid, List } from 'lucide-svelte';
    import type { Snippet } from 'svelte';
    import { MediaQuery } from 'svelte/reactivity';
    import OmrBatchQueueModal from './OmrBatchQueueModal.svelte';
    import OmrRapidTriageGrid, { type RapidTriageItem } from './OmrRapidTriageGrid.svelte';
    import OmrCaptureStage from './OmrCaptureStage.svelte';
    import OmrEvidenceStage from './OmrEvidenceStage.svelte';
    import OmrCompletionBar from './OmrCompletionBar.svelte';
    import OmrProcessingStage from './OmrProcessingStage.svelte';
    import OmrRecoveryCard from './OmrRecoveryCard.svelte';
    import OmrWorkbenchHeader from './OmrWorkbenchHeader.svelte';
    import OmrShortcutsModal from './OmrShortcutsModal.svelte';
    import OmrCameraDeck, { type OmrCameraDeckProps } from './OmrCameraDeck.svelte';
    import type {
        EvidencePage,
        OmrReviewDecisionPayload,
    } from '@/lib/omrDiagnosticEvidence';
    import { normalizeItem } from '@/lib/omrDiagnosticEvidence';
    import { newIdempotencyKey } from '@/lib/utils';
    import { buildOmrConsoleReviewPayload } from '@/lib/omrConsoleReview';
    import {
        OMR_WORKBENCH_COMMANDS,
        type OmrWorkbenchCommand,
        type OmrWorkbenchProjection,
        type OmrWorkbenchRecoveryAction,
        type OmrWorkbenchRecoveryItem,
        type OmrWorkbenchSheet,
    } from '@/lib/omrWorkbench';

    type ValueRecord = Record<string, unknown>;
    type EvidenceRow = Record<string, unknown>;
    type EvidencePageInput = EvidencePage | ValueRecord | null;
    type EvidenceStateError = { kind?: string; detail?: string } | null;
    type CompletionCommand = Extract<
        OmrWorkbenchCommand,
        'production_complete' | 'diagnostic_compare' | 'diagnostic_complete'
    >;

    type RetryHandler = (context?: ValueRecord) => void | Promise<void>;
    type SelectSheetHandler = (
        sheet: OmrWorkbenchSheet,
    ) => unknown;
    type ReviewActionHandler = (
        payload: OmrReviewDecisionPayload,
        row: EvidenceRow,
    ) => unknown | Promise<unknown>;
    type RecoveryActionHandler = (
        action: OmrWorkbenchRecoveryAction,
        recovery: OmrWorkbenchRecoveryItem,
    ) => void | Promise<void>;
    type CompleteHandler = (command: CompletionCommand) => void | Promise<void>;
    type DirtyChangeHandler = (dirty: boolean) => void;
    type DirtyNavigationHandler = (intent: ValueRecord) => boolean | void;
    type EvidenceCallback = (...args: never[]) => unknown;

    type WorkbenchProps = {
        projection: OmrWorkbenchProjection;
        connectionState?: string;
        captureContent?: Snippet;
        cameraDeckProps?: OmrCameraDeckProps;
        cameraDeckSettingsExtras?: Snippet;
        cameraSettingsOpen?: boolean;
        cameraMode?: 'manual' | 'auto' | 'batch';
        queueContent?: Snippet;
        statusContent?: Snippet;
        selectionContent?: Snippet;
        evidencePage?: EvidencePageInput;
        evidenceContextSummary?: ValueRecord;
        evidenceLoading?: boolean;
        evidenceError?: EvidenceStateError;
        triageInventory?: {
            loading: boolean;
            error: EvidenceStateError;
            items: EvidenceRow[];
        };
        selectedSheetId?: number | null;
        stageView?: 'live' | 'gallery' | 'review' | 'camera' | 'triage' | 'queue';
        onSelectSheet?: SelectSheetHandler;
        onSelectBubbleItem?: (item: EvidenceRow) => void;
        onRetry?: RetryHandler;
        onRetakeSheet?: (sheet: OmrWorkbenchSheet) => void;
        onDiscardSheet?: (sheet: OmrWorkbenchSheet) => void;
        onRetryTriageInventory?: () => void;
        onPageChange?: EvidenceCallback;
        onGroupChange?: EvidenceCallback;
        onFilterChange?: EvidenceCallback;
        onViewChange?: EvidenceCallback;
        onReviewAction?: ReviewActionHandler;
        onRecoveryAction?: RecoveryActionHandler;
        onComplete?: CompleteHandler;
        onDirtyChange?: DirtyChangeHandler;
        onDirtyNavigation?: DirtyNavigationHandler;
        onBack?: () => void;
        onStageViewChange?: (view: 'camera' | 'review' | 'triage' | 'queue') => void;
        onOpenGuide?: () => void | Promise<void>;
        sessionTitle?: string;
        onSettings?: () => void;
        onRestart?: () => void;
        initialStageView?: 'live' | 'gallery' | 'review' | 'camera' | 'triage' | 'queue';
    }

    let {
        projection,
        connectionState = undefined,
        captureContent = undefined,
        cameraDeckProps = undefined,
        cameraDeckSettingsExtras = undefined,
        cameraSettingsOpen = $bindable(false),
        cameraMode = $bindable<'manual' | 'auto' | 'batch'>('auto'),
        queueContent = undefined,
        statusContent = undefined,
        selectionContent = undefined,
        evidencePage = null,
        evidenceContextSummary = {},
        evidenceLoading = false,
        evidenceError = null,
        triageInventory = { loading: false, error: null, items: [] },
        selectedSheetId = null,
        stageView = undefined,
        onSelectSheet = undefined,
        onSelectBubbleItem = undefined,
        onPageChange = undefined,
        onGroupChange = undefined,
        onFilterChange = undefined,
        onViewChange = undefined,
        onRetry = undefined,
        onRetakeSheet = undefined,
        onDiscardSheet = undefined,
        onRetryTriageInventory = undefined,
        onReviewAction = undefined,
        onRecoveryAction = undefined,
        onComplete = undefined,
        onDirtyChange = undefined,
        onDirtyNavigation = undefined,
        onBack = undefined,
        onStageViewChange = undefined,
        onOpenGuide = undefined,
        sessionTitle = undefined,
        onSettings = undefined,
        onRestart = undefined,
        initialStageView = 'live',
    }: WorkbenchProps = $props();

    let activeSheetId = $state<number | null>(null);
    let activeStageView = $state<'live' | 'gallery' | 'review'>(
        stageView === 'triage' || stageView === 'gallery'
            ? 'gallery'
            : stageView === 'review'
              ? 'review'
              : initialStageView === 'triage' || initialStageView === 'gallery'
                ? 'gallery'
                : initialStageView === 'review'
                  ? 'review'
                  : 'live',
    );
    let queueModalOpen = $state(stageView === 'queue' || initialStageView === 'queue');
    let batchModalOpen = $state(false);
    function createMediaQuery(query: string) {
        if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
            try {
                return new MediaQuery(query);
            } catch {
                // fallback
            }
        }
        return { current: false };
    }
    const compactViewport = createMediaQuery('max-width: 1023px');
    const compactQueueOpen = $derived(compactViewport.current && queueModalOpen);
    let evidenceFocusOpen = $state(false);
    let guideActive = $state(false);
    let cameraBatchTrigger = $state<'auto_steady' | 'manual_qr'>('auto_steady');
    let cameraSteadyDelayMs = $state<150 | 300 | 500 | 800>(300);
    let cameraCountdownSeconds = $state(3);
    let cameraApplyCountdownToAuto = $state(false);
    let cameraSourceKind = $state<'local' | 'paired_phone'>('local');
    let cameraDeviceId = $state('');
    let cameraLastCapturedTargetKey = $state<string | null>(null);
    let cameraAwaitingSheetSwap = $state(false);

    async function handleOpenGuide(): Promise<void> {
        if (!onOpenGuide) return;
        guideActive = true;
        try {
            await onOpenGuide();
        } finally {
            guideActive = false;
        }
    }

    $effect(() => {
        if (activeStageView !== 'review') {
            evidenceFocusOpen = false;
        }
    });
    $effect(() => {
        if (stageView === 'queue') {
            activeStageView = 'live';
            queueModalOpen = true;
            return;
        }
        if (stageView !== undefined) {
            queueModalOpen = false;
            activeStageView =
                stageView === 'triage' || stageView === 'gallery'
                    ? 'gallery'
                    : stageView === 'review'
                      ? 'review'
                      : 'live';
        }
    });
    const headerStageView = $derived(
        compactQueueOpen
            ? 'queue'
            : activeStageView === 'live'
              ? (queueModalOpen ? 'queue' : 'camera')
              : activeStageView === 'gallery'
                ? 'triage'
                : 'review',
    );

    function changeStageView(view: 'camera' | 'review' | 'triage' | 'queue'): void {
        if (view === 'queue') {
            if (onDirtyNavigation?.({ type: 'stage', from: activeStageView, to: 'queue' }) === false) return;
            activeStageView = 'live';
            queueModalOpen = true;
            onStageViewChange?.(view);
            return;
        }
        const nextStageView =
            view === 'camera' ? 'live' : view === 'triage' ? 'gallery' : 'review';
        if (
            activeStageView !== nextStageView &&
            onDirtyNavigation?.({
                type: 'stage',
                from: activeStageView,
                to: nextStageView,
            }) === false
        ) {
            return;
        }

        queueModalOpen = false;
        activeStageView = nextStageView;
        onStageViewChange?.(view);
    }
    let recoveryBusy = $state(false);
    let queueViewMode = $state<'list' | 'grid'>('list');
    let queueFilter = $state<'all' | 'needs_review' | 'ready'>('all');
    let quickMarks = $state<Record<string, string>>({});
    let selectedReviewItemKey = $state<string | null>(null);
    let quickReasons = $state<Record<string, string>>({});
    let quickNotes = $state<Record<string, string>>({});
    let quickIdempotencyKeys = $state<
        Record<string, { intent: string; key: string }>
    >({});
    let shortcutsModalOpen = $state(false);

    function handleGlobalKeydown(e: KeyboardEvent): void {
        if (
            e.defaultPrevented ||
            e.repeat ||
            e.ctrlKey ||
            e.metaKey ||
            e.altKey
        )
            return;
        const target = e.target;
        if (
            target instanceof HTMLElement &&
            target.closest(
                'input, textarea, select, button, a, [contenteditable="true"], [role="dialog"]',
            )
        )
            return;
        if (
            shortcutsModalOpen ||
            document.querySelector('[role="dialog"], dialog[open]')
        )
            return;
        if (e.key === '?') {
            e.preventDefault();
            shortcutsModalOpen = true;
        } else if (
            e.key === 'Escape' &&
            activeStageView === 'review' &&
            !isSavingReview
        ) {
            e.preventDefault();
            handleCancelReview();
        }
    }

    function reviewItemKey(item: EvidenceRow, _index: number): string {
        return String(item.id ?? item.item_id ?? '');
    }


    function setQuickReason(key: string, value: string): void {
        if (isSavingReview) return;
        quickReasons = { ...quickReasons, [key]: value };
    }

    function setQuickNote(key: string, value: string): void {
        if (isSavingReview) return;
        quickNotes = { ...quickNotes, [key]: value };
    }

    function idempotencyFor(key: string, intent: string): string {
        const current = quickIdempotencyKeys[key];
        if (current?.intent === intent) return current.key;
        const next = {
            ...quickIdempotencyKeys,
            [key]: { intent, key: newIdempotencyKey() },
        };
        quickIdempotencyKeys = next;
        return next[key].key;
    }

    let isSavingReview = $state(false);
    let reviewSaveError = $state<string | null>(null);

    function setQuickMark(
        item: EvidenceRow,
        index: number,
        mark: string,
    ): void {
        if (isSavingReview) return;
        const key = reviewItemKey(item, index);
        if (!key) return;
        quickMarks = { ...quickMarks, [key]: mark };
        const reasons = { ...quickReasons };
        delete reasons[key];
        quickReasons = reasons;
        const notes = { ...quickNotes };
        delete notes[key];
        quickNotes = notes;
        onDirtyChange?.(true);
    }
    function handleBubbleSelect(item: EvidenceRow): void {
        const key = reviewItemKey(item, 0);
        const source =
            evidenceItems.find((candidate) => reviewItemKey(candidate, 0) === key) ??
            item;
        selectedReviewItemKey = key || null;
        onSelectBubbleItem?.(source);
    }

    function handleBubbleDecision(item: EvidenceRow, mark: string): void {
        const key = reviewItemKey(item, 0);
        const source =
            evidenceItems.find((candidate) => reviewItemKey(candidate, 0) === key) ??
            item;
        const index = evidenceItems.indexOf(source);
        setQuickMark(source, index < 0 ? 0 : index, mark);
        selectedReviewItemKey = key || null;
    }

    function handleBubbleReason(item: EvidenceRow, reason: string): void {
        const key = reviewItemKey(item, 0);
        setQuickReason(key, reason);
    }
    function handleBubbleNote(item: EvidenceRow, note: string): void {
        const key = reviewItemKey(item, 0);
        setQuickNote(key, note);
    }
    async function handleSaveReviewFor(
        triageItem: RapidTriageItem,
        mark: string,
        reason?: string,
    ): Promise<boolean> {
        const sourceRow = triageItem.evidence;
        const reasonText = textValue(reason);
        const reasonCode =
            reasonText
                ?.toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .slice(0, 64) ?? '';
        if (
            !sourceRow ||
            sourceRow.reviewable !== true ||
            !onReviewAction ||
            isSavingReview ||
            reasonText === null ||
            reasonCode === ''
        ) {
            reviewSaveError = 'Choose a review reason before saving this item.';
            return false;
        }

        const safeReasonCode = /^[a-z]/.test(reasonCode)
            ? reasonCode
            : `reason_${reasonCode}`.slice(0, 64);
        const source: EvidenceRow = {
            ...sourceRow,
            capture_id: triageItem.capture_id,
            parent_version: triageItem.parent_version,
            item_revision: triageItem.item_revision,
            evidence_hash: triageItem.evidence_hash,
        };
        const key = `${String(triageItem.capture_id)}:${String(triageItem.item_id ?? triageItem.id)}`;
        quickMarks = { ...quickMarks, [key]: mark === 'void' ? 'blank' : mark };
        quickReasons = { ...quickReasons, [key]: safeReasonCode };
        quickNotes = { ...quickNotes, [key]: reasonText };
        await handleSaveReview(false, key, source);
        return !reviewSaveError;
    }

    async function handleLinkApplicant(
        triageItem: RapidTriageItem,
        applicantId: string,
    ): Promise<boolean> {
        const item = triageItem.evidence as EvidenceRow | undefined;
        if (!item || !onRecoveryAction) return false;

        await onRecoveryAction('match_applicant', {
            ...item,
            applicant_id: applicantId,
            capture_id: triageItem.capture_id,
        });
        return true;
    }
    async function handleSaveReview(
        andNext = false,
        onlyKey?: string,
        onlyItem?: EvidenceRow,
    ): Promise<void> {
        if (isSavingReview || !onReviewAction) return;
        const keys = onlyKey ? [onlyKey] : Object.keys(quickMarks);
        if (keys.length === 0) {
            if (andNext) advanceToNextFlagged();
            return;
        }

        isSavingReview = true;
        reviewSaveError = null;
        try {
            for (const key of keys) {
                const selectedOption = quickMarks[key];
                const item =
                    onlyItem ??
                    evidenceItems.find(
                        (candidate) => reviewItemKey(candidate, 0) === key,
                    );
                if (!item)
                    throw new Error(
                        'A selected item is no longer present. Refresh the evidence before saving.',
                    );
                const normalized = normalizeItem(item);
                const decision =
                    selectedOption === 'unresolved'
                        ? 'unresolved'
                        : selectedOption === 'blank'
                          ? 'blank'
                          : selectedOption === 'confirm_machine'
                            ? 'confirm_machine'
                            : 'set_option';
                const selected =
                    decision === 'set_option'
                        ? selectedOption
                        : decision === 'confirm_machine'
                          ? normalized.machine?.selected_option
                          : null;
                const reasonCode = textValue(quickReasons[key]);
                const note = textValue(quickNotes[key]);
                const machineOption =
                    normalized.machine?.selected_option ?? null;
                const machineMarked =
                    normalized.machine?.state === 'marked' &&
                    machineOption !== null;
                if (
                    ((decision === 'set_option' ||
                        decision === 'unresolved' ||
                        (decision === 'blank' && machineMarked)) &&
                        reasonCode === null) ||
                    (decision === 'set_option' &&
                        machineMarked &&
                        selected !== machineOption &&
                        note === null)
                ) {
                    throw new Error(
                        decision === 'set_option' && selected !== machineOption
                            ? 'Choose a review reason and add a note for a changed answer.'
                            : 'Choose a review reason before saving this item.',
                    );
                }
                const itemId = item.id ?? item.item_id;
                const captureId = item.capture_id ?? item.sheet_id ?? activeSheetId ?? activeSheet?.id;
                const parentVersion =
                    normalized.parent_version ??
                    item.parent_version ??
                    (Number(captureId) === Number(activeSheetId)
                        ? activeSheet?.version
                        : null);
                const evidenceHash =
                    normalized.machine?.evidence_hash ??
                    normalized.detected?.evidence_hash ??
                    item.evidence_hash;
                const intent = JSON.stringify([
                    projection.workbench_id,
                    captureId,
                    key,
                    decision,
                    selected,
                    reasonCode,
                    note,
                    parentVersion,
                    normalized.item_revision,
                    evidenceHash,
                ]);
                const idempotencyKey = idempotencyFor(key, intent);
                const row = {
                    ...item,
                    id: itemId,
                    capture_id: captureId,
                    parent_version: parentVersion,
                    item_revision: normalized.item_revision,
                    machine: normalized.machine,
                    options: normalized.options,
                };
                const payload: OmrReviewDecisionPayload =
                    buildOmrConsoleReviewPayload(
                        {
                            decision,
                            selected_option: selected,
                            reason_code: reasonCode,
                            note,
                            expected_parent_version: parentVersion,
                            expected_item_revision:
                                normalized.item_revision ?? 0,
                            reviewed_machine_evidence_hash: evidenceHash,
                            idempotency_key: idempotencyKey,
                        },
                        row,
                        idempotencyKey,
                    );
                const result = await onReviewAction(payload, row);
                if (!result || typeof result !== 'object') {
                    throw new Error(
                        'The server did not acknowledge this item review.',
                    );
                }
                const remaining = { ...quickMarks };
                delete remaining[key];
                quickMarks = remaining;
                const reasons = { ...quickReasons };
                delete reasons[key];
                quickReasons = reasons;
                const notes = { ...quickNotes };
                delete notes[key];
                quickNotes = notes;
                const savedKeys = { ...quickIdempotencyKeys };
                delete savedKeys[key];
                quickIdempotencyKeys = savedKeys;
                onDirtyChange?.(Object.keys(remaining).length > 0);
            }
            if (andNext) advanceToNextFlagged();
        } catch (error: unknown) {
            reviewSaveError =
                error instanceof Error
                    ? error.message
                    : 'Failed to save review decisions. Please try again.';
        } finally {
            isSavingReview = false;
        }
    }

    function isNeedsReviewSheet(sheet: OmrWorkbenchSheet): boolean {
        const status = textValue(sheet.status)?.toLowerCase();

        return (
            (sheet.recovery !== null && sheet.recovery !== undefined) ||
            ['needs_review', 'failed', 'failed_terminal'].includes(status ?? '')
        );
    }

    function isReadySheet(sheet: OmrWorkbenchSheet): boolean {
        const status = textValue(sheet.status)?.toLowerCase();

        return (
            !isNeedsReviewSheet(sheet) &&
            ['ready', 'reviewed', 'processed', 'complete', 'completed'].includes(status ?? '')
        );
    }

    function advanceToNextFlagged(): void {
        const nextSheet = sheets.find(
            (sheet) =>
                sheet.id !== activeSheetId && isNeedsReviewSheet(sheet),
        );

        if (nextSheet) {
            selectSheet(nextSheet);
        } else {
            changeStageView('camera');
        }
    }

    function openNextNeedsReview(): void {
        if (!nextNeedsReviewSheet) return;
        if (selectSheet(nextNeedsReviewSheet)) {
            changeStageView('review');
        }
    }

    function reviewSheet(sheet: OmrWorkbenchSheet): void {
        if (selectSheet(sheet)) {
            changeStageView('review');
        }
    }

    function selectSheet(sheet: OmrWorkbenchSheet): boolean {
        if (isSavingReview) {
            return false;
        }
        if (Object.keys(quickMarks).length > 0 && onDirtyNavigation) {
            const allowed = onDirtyNavigation({
                target: 'sheet',
                sheet_id: sheet.id,
            });
            if (allowed === false) return false;
        }
        if (onSelectSheet?.(sheet) === false) {
            return false;
        }
        if (sheet.id !== activeSheetId) {
            quickMarks = {};
            selectedReviewItemKey = null;
            quickReasons = {};
            quickNotes = {};
            quickIdempotencyKeys = {};
            onDirtyChange?.(false);
            reviewSaveError = null;
            activeSheetId = sheet.id;
        }
        activeStageView = 'review';
        return true;

    }


    function handleCancelReview(): void {
        if (isSavingReview) return;
        if (Object.keys(quickMarks).length > 0 && onDirtyNavigation) {
            const allowed = onDirtyNavigation({ target: 'camera' });
            if (allowed === false) return;
        }
        quickMarks = {};
        quickReasons = {};
        quickNotes = {};
        quickIdempotencyKeys = {};
        onDirtyChange?.(false);
        reviewSaveError = null;
        activeStageView = 'live';
        onStageViewChange?.('camera');
    }

    function sheetThumbnailUrl(sheet: OmrWorkbenchSheet): string | null {
        const s = sheet as any;
        return (
            s.lazy_asset_urls?.input ??
            s.asset_urls?.thumbnail ??
            s.asset_urls?.input ??
            null
        );
    }
    function asRecord(value: unknown): ValueRecord {
        if (
            value === null ||
            typeof value !== 'object' ||
            Array.isArray(value)
        ) {
            return {};
        }

        return value as ValueRecord;
    }

    function textValue(value: unknown): string | null {
        if (typeof value !== 'string') return null;
        const text = value.trim();
        return text === '' ? null : text;
    }

    function displayValue(value: unknown): string | null {
        if (typeof value === 'string') return textValue(value);
        if (typeof value === 'number' && Number.isFinite(value)) {
            return String(value);
        }
        if (typeof value === 'boolean') return value ? 'Yes' : 'No';
        return null;
    }

    function nonNegative(value: unknown): number | null {
        if (typeof value !== 'number' || !Number.isFinite(value)) return null;
        return Math.max(0, Math.floor(value));
    }

    function titleCase(value: string): string {
        return value
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase());
    }
    function phaseLabel(value: unknown): string {
        const phase = textValue(value);
        if (phase === null) return 'Awaiting status';

        return (
            {
                capture: 'Capture',
                preflight: 'Preflight',
                context: 'Context',
                processing: 'Processing',
                evidence: 'Evidence',
                review: 'Review',
                recovery: 'Needs attention',
                completion: 'Completion',
                complete: 'Complete',
                terminal: 'Terminal',
            }[phase] ?? titleCase(phase)
        );
    }

    const contextDetails = $derived.by(() => {
        const details: { label: string; value: string }[] = [];
        const sessionId = displayValue(context.session_id);
        const runId = displayValue(context.run_id);
        const status =
            displayValue(context.status) ?? displayValue(context.raw_status);
        const stage =
            displayValue(context.raw_phase) ?? phaseLabel(projection.phase);

        if (sessionId !== null && projection.execution_kind === 'production') {
            details.push({ label: 'Session', value: sessionId });
        }
        if (runId !== null) details.push({ label: 'Run', value: runId });
        if (status !== null)
            details.push({ label: 'Status', value: titleCase(status) });
        if (stage !== null)
            details.push({ label: 'Stage', value: phaseLabel(stage) });

        return details;
    });

    function sheetLabel(sheet: OmrWorkbenchSheet): string {
        const captureId = textValue(sheet.client_capture_id);
        return captureId !== null
            ? `Capture ${captureId}`
            : `Sheet ${sheet.id}`;
    }
    function sheetStatus(sheet: OmrWorkbenchSheet): string {
        if (sheet.recovery !== null && sheet.recovery !== undefined) {
            return 'Needs attention';
        }
        const status = textValue(sheet.status);
        return status !== null ? titleCase(status) : 'Processed';
    }

    function projectedCommand(command: OmrWorkbenchCommand): boolean {
        return projection.commands.includes(command);
    }

    function candidateLabel(sheet: OmrWorkbenchSheet): string {
        const details = asRecord(sheet);
        return (
            textValue(details.candidate_name) ??
            textValue(details.applicant_name) ??
            textValue(details.detected_applicant_number) ??
            textValue(sheet.client_capture_id) ??
            `Scan #${sheet.id}`
        );
    }

    function canInvokeRecoveryAction(
        action: OmrWorkbenchRecoveryAction,
    ): boolean {
        if (!OMR_WORKBENCH_COMMANDS.includes(action as OmrWorkbenchCommand)) {
            return true;
        }

        return projection.commands.includes(action as OmrWorkbenchCommand);
    }
    const reviewable = $derived(
        projectedCommand('review_save') && onReviewAction !== undefined,
    );


    const sheets = $derived(projection.sheets ?? []);
    const needsReviewSheets = $derived(sheets.filter(isNeedsReviewSheet));
    const readySheets = $derived(sheets.filter(isReadySheet));
    const visibleQueueSheets = $derived(
        queueFilter === 'needs_review'
            ? needsReviewSheets
            : queueFilter === 'ready'
              ? readySheets
              : sheets,
    );
    const nextNeedsReviewSheet = $derived.by(() => {
        const remaining = needsReviewSheets.filter(
            (sheet) => sheet.id !== activeSheetId,
        );

        return remaining[0] ?? needsReviewSheets[0] ?? null;
    });
    const evidenceItems = $derived.by(() => {
        const items = asRecord(evidencePage).items;
        return Array.isArray(items) ? (items as EvidenceRow[]) : [];
    });
    const rapidTriageItems = $derived<RapidTriageItem[]>(
        triageInventory.items.flatMap((item, index) => {
            const normalized = normalizeItem(item);
            if (normalized.machine?.state !== 'ambiguous') return [];
            const captureId = String(item.capture_id ?? item.sheet_id ?? '');
            const itemId = String(item.id ?? item.item_id ?? index);
            const pageNo = Number(item.page_no ?? item.capture_page_no);
            const parentVersion =
                normalized.parent_version ?? item.parent_version ?? undefined;
            const evidenceHash =
                normalized.machine?.evidence_hash ??
                normalized.detected?.evidence_hash ??
                (typeof item.evidence_hash === 'string'
                    ? item.evidence_hash
                    : undefined);
            return [{
                id: `${captureId}:${itemId}`,
                capture_id: captureId,
                page_no: Number.isFinite(pageNo) ? pageNo : undefined,
                item_id: itemId,
                item_revision: normalized.item_revision ?? undefined,
                parent_version: parentVersion as string | number | undefined,
                evidence_hash: evidenceHash ?? undefined,
                evidence: (item.evidence ?? item) as EvidenceRow,
                type: 'ambiguous_bubble' as const,
                sheet: { number: captureId ?? '—' },
                question: { number: String(item.question_no ?? item.question_number ?? '—') },
                options: normalized.options.map((option) => ({
                    code: option.code,
                    mean: option.mean ?? null,
                })),
                observedSelection: normalized.machine.selected_option ?? null,
                reviewReason: '',
                cropUrl: typeof item.crop_url === 'string' ? item.crop_url : null,
                imageUrl: typeof item.imageUrl === 'string' ? item.imageUrl : null,
                cropRect: item.cropRect as RapidTriageItem['cropRect'],
                imageDimensions:
                    item.sourceWidth && item.sourceHeight
                        ? {
                              width: Number(item.sourceWidth),
                              height: Number(item.sourceHeight),
                          }
                        : undefined,
                machineReading: normalized.machine.selected_option ?? 'Ambiguous machine reading',
            }];
        }),
    );
    const activeSheet = $derived(
        sheets.find((sheet) => sheet.id === activeSheetId) ?? null,
    );
    const context = $derived(asRecord(projection.context));
    const progress = $derived(asRecord(context.progress));
    const progressCompleted = $derived(nonNegative(progress.completed) ?? 0);
    const progressTotal = $derived(nonNegative(progress.total));
    const progressKnown = $derived(
        progress.known === true && progressTotal !== null && progressTotal > 0,
    );
    const recoveryItems = $derived(projection.recovery.items ?? []);
    const allRecoveryItems = $derived.by(() => {
        const items = [...recoveryItems];
        if (activeSheet?.recovery) {
            const sheetRec = activeSheet.recovery as OmrWorkbenchRecoveryItem;
            const alreadyExists = items.some(
                (item) =>
                    item.code === sheetRec.code &&
                    Number(item.capture_id ?? 0) === Number(activeSheet?.id ?? 0),
            );
            if (!alreadyExists) {
                items.unshift({
                    ...sheetRec,
                    capture_id: sheetRec.capture_id ?? activeSheet.id,
                });
            }
        }
        return items;
    });
    const attentionItems = $derived.by(() => {
        const list = [...(projection.recovery.needs_attention ?? [])];
        if (list.length === 0 && allRecoveryItems.length > 0) {
            const owners = new Map<string, number>();
            for (const item of allRecoveryItems) {
                const owner = textValue(item.owner) ?? 'Operator';
                owners.set(owner, (owners.get(owner) ?? 0) + 1);
            }
            return Array.from(owners.entries()).map(([owner, count]) => ({
                owner,
                count,
            }));
        }
        return list;
    });
    const attentionCount = $derived(
        needsReviewSheets.length > 0
            ? needsReviewSheets.length
            : attentionItems.reduce(
                  (total, item) => total + (nonNegative(item.count) ?? 0),
                  0,
              ),
    );
    const contextSummary = $derived.by(() => {
        const summary: ValueRecord = {};
        const session = displayValue(context.session_id);
        const status =
            displayValue(context.status) ?? displayValue(context.raw_status);

        const run = displayValue(context.run_id);
        if (session !== null) summary.Session = session;
        if (run !== null) summary.Run = run;
        if (status !== null) summary.Status = status;
        return { ...summary, ...evidenceContextSummary };
    });

    const recoveryHandler = $derived<RecoveryActionHandler | null>(
        onRecoveryAction === undefined ? null : handleRecoveryAction,
    );

    $effect(() => {
        const incoming = selectedSheetId;
        const incomingExists =
            incoming !== null &&
            incoming !== undefined &&
            sheets.some((sheet) => sheet.id === incoming);
        const activeExists =
            activeSheetId !== null &&
            sheets.some((sheet) => sheet.id === activeSheetId);

        if (incomingExists) {
            activeSheetId = incoming as number;
            return;
        }

        if (!activeExists) {
            activeSheetId = sheets[0]?.id ?? null;
        }
    });


    function retryProcessing(): void {
        if (!projectedCommand('capture_retry')) return;
        void onRetry?.({
            reason: 'processing',
            sheet_id: activeSheetId,
        });
    }

    async function handleRecoveryAction(
        action: OmrWorkbenchRecoveryAction,
        recovery: OmrWorkbenchRecoveryItem,
    ): Promise<void> {
        if (
            !onRecoveryAction ||
            !canInvokeRecoveryAction(action) ||
            recoveryBusy
        ) {
            return;
        }

        recoveryBusy = true;
        try {
            await onRecoveryAction(action, recovery);
        } finally {
            recoveryBusy = false;
        }
    }


    async function handleComplete(command: CompletionCommand): Promise<void> {
        if (!onComplete || !projection.commands.includes(command)) return;
        await onComplete(command);
    }
</script>

{#snippet sharedCaptureContent()}
    {#if captureContent}
        {@render captureContent()}
    {/if}
    {#if cameraDeckProps}
        <OmrCameraDeck
            {...cameraDeckProps}
            settingsExtras={cameraDeckSettingsExtras}
            bind:mode={cameraMode}
            bind:batchTrigger={cameraBatchTrigger}
            bind:steadyDelayMs={cameraSteadyDelayMs}
            bind:countdownSeconds={cameraCountdownSeconds}
            bind:applyCountdownToAuto={cameraApplyCountdownToAuto}
            bind:sourceKind={cameraSourceKind}
            bind:selectedDeviceId={cameraDeviceId}
            bind:lastCapturedTargetKey={cameraLastCapturedTargetKey}
            bind:awaitingSheetSwap={cameraAwaitingSheetSwap}
            bind:settingsOpen={cameraSettingsOpen}
            paused={guideActive || queueModalOpen || batchModalOpen || shortcutsModalOpen || activeStageView !== 'live' || Boolean(cameraDeckProps.paused)}
            onOpenGallery={cameraDeckProps.onOpenGallery ?? (() => changeStageView('queue'))}
        />
    {/if}
{/snippet}

<svelte:window onkeydown={handleGlobalKeydown} />

<div
    class="omr-workbench bg-background text-foreground flex h-dvh w-full flex-col overflow-hidden"
    data-omr-workbench
    data-omr-workbench-id={projection.workbench_id}
    data-omr-execution-kind={projection.execution_kind}
>
    <OmrWorkbenchHeader
        {projection}
        {connectionState}
        onSettings={cameraDeckProps ? () => (cameraSettingsOpen = !cameraSettingsOpen) : onSettings}
        {onBack}
        {sessionTitle}
        activeStageView={headerStageView}
        onStageViewChange={changeStageView}
        onShortcuts={() => (shortcutsModalOpen = true)}
        {onRestart}
        sheetsCount={sheets.length}
        onComplete={onComplete ? handleComplete : undefined}
        onOpenGuide={onOpenGuide ? handleOpenGuide : undefined}
    />
    {#if statusContent}
        <div class="shrink-0" data-omr-workbench-notice>
            {@render statusContent()}
        </div>
    {/if}


    {#if compactQueueOpen || activeStageView !== 'live'}
        <div class="border-border/60 bg-card flex shrink-0 items-center justify-between gap-3 border-b px-3 py-2 lg:hidden" data-omr-mobile-navigation>
            <button
                type="button"
                class="text-primary inline-flex min-h-11 items-center gap-2 text-sm font-medium"
                onclick={() => changeStageView(compactQueueOpen ? 'camera' : 'queue')}
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                {compactQueueOpen ? 'Back to Camera' : 'Back to Scans queue'}
            </button>
            <h2 class="text-foreground min-w-0 truncate text-sm font-semibold">
                {compactQueueOpen ? 'Scans queue' : activeStageView === 'review' ? 'Sheet review' : 'Rapid Triage'}
            </h2>
            {#if compactQueueOpen}
                <button type="button" class="text-primary min-h-11 shrink-0 text-xs font-semibold" onclick={() => changeStageView('triage')}>Rapid Triage</button>
            {/if}
        </div>
    {/if}

    {#if activeStageView === 'review'}
        <div data-omr-stage-view="review" class="flex min-h-0 flex-1 flex-col overflow-hidden" class:hidden={compactQueueOpen} aria-hidden={compactQueueOpen} inert={compactQueueOpen}>
            {#if evidenceLoading}
                <div class="border-border bg-card flex shrink-0 items-center gap-2 border-b px-4 py-2 text-sm" role="status" aria-live="polite">
                    <span class="size-2 animate-pulse rounded-full bg-primary"></span>
                    Loading private evidence for {activeSheet ? sheetLabel(activeSheet) : 'the selected sheet'}…
                </div>
            {:else if evidenceError}
                <div class="border-destructive/30 bg-destructive/10 text-destructive flex shrink-0 flex-wrap items-center justify-between gap-3 border-b px-4 py-2 text-sm" role={evidenceError.kind === 'denied' ? 'alert' : 'status'}>
                    <span>{evidenceError.kind === 'denied' ? 'Evidence access denied.' : 'Evidence loading failed.'} {evidenceError.detail ?? ''}</span>
                    <button type="button" class="rounded-md border border-current px-3 py-1.5 text-xs font-semibold" onclick={() => onRetry?.()}>Retry evidence</button>
                </div>
            {:else if activeSheet && ['failed', 'failed_terminal'].includes(String(activeSheet.status ?? '').toLowerCase())}
                <div class="border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200 flex shrink-0 flex-col items-start gap-3 border-b px-4 py-2 text-sm sm:flex-row sm:items-center sm:justify-between" role="status">
                    <span class="min-w-0">Scan processing failed. Retake this page or retry processing the same image.</span>
                    <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0">
                        {#if onRetry}
                            <button type="button" class="min-h-11 rounded-md border border-current px-2.5 py-1 text-xs font-semibold" onclick={() => onRetry?.()}>Retry processing</button>
                        {/if}
                    </div>
                </div>
            {:else if !evidencePage}
                <div class="border-border bg-muted/20 text-muted-foreground flex shrink-0 items-center justify-between gap-3 border-b px-4 py-2 text-sm" role="status">
                    Evidence is unavailable for this sheet.
                    <button type="button" class="rounded-md border border-current px-3 py-1.5 text-xs font-semibold" onclick={() => onRetry?.()}>Retry evidence</button>
                </div>
            {:else if !asRecord(asRecord(evidencePage).sheet_map).width || !asRecord(asRecord(evidencePage).sheet_map).height}
                <div class="border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200 flex shrink-0 items-center gap-2 border-b px-4 py-2 text-sm" role="status">
                    Evidence is available, but mapped sheet geometry is missing.
                </div>
            {:else if !asRecord(asRecord(evidencePage).lazy_asset_urls).aligned && !asRecord(asRecord(evidencePage).lazy_asset_urls).input && !asRecord(asRecord(evidencePage).lazy_asset_urls).overlay}
                <div class="border-border bg-muted/20 text-muted-foreground flex shrink-0 items-center justify-between gap-3 border-b px-4 py-2 text-sm" role="status">
                    The sheet image is unavailable for this evidence page.
                    <button type="button" class="rounded-md border border-current px-3 py-1.5 text-xs font-semibold" onclick={() => onRetry?.()}>Retry evidence</button>
                </div>
            {/if}
            <OmrEvidenceStage
                {evidencePage}
                {contextSummary}
                {sheets}
                {activeSheet}
                {activeSheetId}
                stageView="review"
                {onRetakeSheet}
                {onDiscardSheet}
                focusOpen={evidenceFocusOpen}
                onFocusOpenChange={(open) => (evidenceFocusOpen = open)}
                selectedItemKey={selectedReviewItemKey}
                selectedDecision={selectedReviewItemKey ? quickMarks[selectedReviewItemKey] ?? null : null}
                selectedReason={selectedReviewItemKey ? quickReasons[selectedReviewItemKey] ?? '' : ''}
                selectedNote={selectedReviewItemKey ? quickNotes[selectedReviewItemKey] ?? '' : ''}
                saving={isSavingReview}
                saveError={reviewSaveError}
                onSelectSheet={(sheet) => selectSheet(sheet)}
                onSelectBubbleItem={handleBubbleSelect}
                onDecisionChange={handleBubbleDecision}
                onReasonChange={handleBubbleReason}
                onNoteChange={handleBubbleNote}
                onSaveDecision={() => handleSaveReview(false)}
                onSaveAndNext={() => handleSaveReview(true)}
                onPageChange={(value) => onPageChange?.(value as never)}
                onGroupChange={(value) => onGroupChange?.(value as never)}
                onFilterChange={(value) => onFilterChange?.(value as never)}
                onViewChange={(value) => onViewChange?.(value as never)}
            />
        </div>
    {:else if activeStageView === 'gallery'}
        <div class="flex min-h-0 flex-1 flex-col" class:hidden={compactQueueOpen} aria-hidden={compactQueueOpen} inert={compactQueueOpen}>
        {#if triageInventory.loading}
            <div class="flex min-h-0 flex-1 items-center justify-center" role="status" aria-live="polite">Loading flagged-sheet decision items…</div>
        {:else if triageInventory.error}
            <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 p-6 text-center" role={triageInventory.error.kind === 'denied' ? 'alert' : 'status'}>
                <p>{triageInventory.error.kind === 'denied' ? 'Batch evidence access denied.' : 'Batch evidence inventory failed to load.'} {triageInventory.error.detail ?? ''}</p>
                <button type="button" class="rounded-md border border-border px-3 py-2 text-sm font-semibold" onclick={() => onRetryTriageInventory?.()}>Retry inventory</button>
            </div>
        {:else}
            <div class="min-h-0 flex-1 overflow-hidden" data-omr-workbench-flow>
                <OmrRapidTriageGrid
                    items={rapidTriageItems}
                    onConfirmDecision={(item, mark, reason?: string) => handleSaveReviewFor(item, mark, reason)}
                    onConfirmMatch={(item, applicant) => handleLinkApplicant(item, applicant.applicantId)}
                    onBack={() => changeStageView('queue')}
                    onCommit={() => {
                        if (projection.commands.includes('production_complete')) {
                            void handleComplete('production_complete');
                        } else if (projection.commands.includes('diagnostic_complete')) {
                            void handleComplete('diagnostic_complete');
                        } else if (projection.commands.includes('diagnostic_compare')) {
                            void handleComplete('diagnostic_compare');
                        }
                    }}
                />
            </div>
        {/if}
        </div>
    {/if}
    <div
        class:hidden={activeStageView !== 'live' && !compactQueueOpen}
        class="flex min-h-0 flex-1 flex-col overflow-hidden"
        aria-hidden={activeStageView !== 'live' && !compactQueueOpen}
        inert={activeStageView !== 'live' && !compactQueueOpen}
    >
        <div
            class="grid min-h-0 flex-1 grid-cols-1 grid-rows-1 items-stretch gap-4 overflow-hidden p-3 sm:p-4 lg:grid-cols-12"
            data-omr-workbench-flow
        >
            <div class="relative flex h-full min-h-0 flex-col overflow-hidden lg:col-span-7 xl:col-span-8" class:hidden={compactQueueOpen} aria-hidden={compactQueueOpen} inert={compactQueueOpen}>
                <OmrCaptureStage
                    captureContent={sharedCaptureContent}
                    activeStageView="live"
                    {sheets}
                    {activeSheetId}
                    {evidencePage}
                    {activeSheet}
                    onSelectSheet={(sheet) => selectSheet(sheet)}
                    {onRetakeSheet}
                    {onDiscardSheet}
                    onSwitchToLive={handleCancelReview}
                    onSwitchToGallery={() => changeStageView('triage')}
                />
            </div>
            <div
                class={`border-border/60 bg-card/40 h-full min-h-0 flex-col overflow-hidden rounded-xl border-l lg:col-span-5 xl:col-span-4 ${compactQueueOpen ? 'flex' : 'hidden lg:flex'}`}
                data-omr-stage="queue"
                data-omr-stage-view="queue"
                aria-hidden={compactViewport.current && !compactQueueOpen}
                inert={compactViewport.current && !compactQueueOpen}
            >
                <section
                    class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
                    data-omr-stage="queue"
                    data-omr-queue-selection
                    aria-labelledby="omr-queue-selection-title"
                >
                    <header class="border-border/60 bg-card/50 shrink-0 border-b px-4 py-3.5 sm:px-5">
                        <div class="flex flex-wrap items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2">
                                <h2 id="omr-queue-selection-title" class="text-foreground min-w-0 font-mono text-xs font-bold tracking-[0.14em] uppercase">SCANS QUEUE</h2>
                                <p class="text-muted-foreground font-mono text-[11px]">{sheets.length} Scanned · {needsReviewSheets.length} Needs Check</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" class="border-primary/30 bg-primary/10 text-primary hover:bg-primary/15 inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-semibold transition-colors" onclick={() => (batchModalOpen = true)}>
                                    Batch Queue <span class="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px]">{needsReviewSheets.length}</span>
                                </button>
                                <div class="border-border/60 bg-muted/40 text-muted-foreground inline-flex rounded-lg border p-0.5" role="group" aria-label="Queue view mode">
                                    <button type="button" class={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${queueViewMode === 'list' ? 'bg-card text-foreground shadow-xs font-semibold' : 'hover:text-foreground'}`} aria-pressed={queueViewMode === 'list'} onclick={() => (queueViewMode = 'list')}><List class="size-3.5" aria-hidden="true" /><span>List</span></button>
                                    <button type="button" class={`inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-medium transition-colors ${queueViewMode === 'grid' ? 'bg-card text-foreground shadow-xs font-semibold' : 'hover:text-foreground'}`} aria-pressed={queueViewMode === 'grid'} onclick={() => (queueViewMode = 'grid')}><LayoutGrid class="size-3.5" aria-hidden="true" /><span>Grid</span></button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2" data-omr-queue-filters>
                            <div class="border-border/60 bg-muted/40 inline-flex flex-wrap rounded-lg border p-0.5" role="group" aria-label="Filter scans by status">
                                <button type="button" class={`rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors ${queueFilter === 'all' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`} aria-pressed={queueFilter === 'all'} onclick={() => (queueFilter = 'all')}>All <span class="font-mono">{sheets.length}</span></button>
                                <button type="button" class={`rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors ${queueFilter === 'needs_review' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`} aria-pressed={queueFilter === 'needs_review'} onclick={() => (queueFilter = 'needs_review')}>Needs Review <span class="font-mono">{needsReviewSheets.length}</span></button>
                                <button type="button" class={`rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors ${queueFilter === 'ready' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`} aria-pressed={queueFilter === 'ready'} onclick={() => (queueFilter = 'ready')}>Ready <span class="font-mono">{readySheets.length}</span></button>
                            </div>
                            <button type="button" class="border-primary/30 bg-primary/10 text-primary hover:bg-primary/15 rounded-md border px-3 py-1.5 text-xs font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50" disabled={!nextNeedsReviewSheet} onclick={openNextNeedsReview}>Next needs review</button>
                        </div>
                    </header>
                    <div class="min-h-0 min-w-0 flex-1 overflow-y-auto p-3 sm:p-4">
                        {#if visibleQueueSheets.length > 0}
                            <div class={queueViewMode === 'grid' ? 'grid min-w-0 grid-cols-2 gap-3' : 'flex min-w-0 flex-col gap-2'} role="listbox" aria-label="OMR sheet queue" data-omr-sheet-queue>
                                {#each visibleQueueSheets as sheet (sheet.id)}
                                    {@const failure = asRecord(asRecord(sheet).failure)}
                                    <button type="button" role="option" aria-selected={activeSheetId === sheet.id} aria-label={`Review ${candidateLabel(sheet)}, sheet ${sheet.id}`} class={`group border-border/60 bg-card hover:border-primary/40 hover:bg-muted/30 focus-visible:outline-primary border text-left transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 ${queueViewMode === 'grid' ? 'min-w-0 rounded-xl p-2' : 'flex min-w-0 items-center gap-3 rounded-xl p-3'} ${activeSheetId === sheet.id ? 'border-primary ring-primary/40 ring-1' : ''}`} data-omr-sheet-option data-omr-sheet-id={sheet.id} onclick={() => reviewSheet(sheet)}>
                                        <div class={`border-border/60 bg-muted/30 relative shrink-0 overflow-hidden rounded-lg border ${queueViewMode === 'grid' ? 'aspect-[3/4] w-full' : 'aspect-[3/4] w-14'}`} aria-hidden="true">
                                            {#if sheetThumbnailUrl(sheet)}
                                                <img src={sheetThumbnailUrl(sheet)} alt="" class="h-full w-full object-cover" loading="lazy" />
                                            {:else}
                                                <div class="text-muted-foreground flex h-full w-full flex-col items-center justify-center p-1"><FileImage class="size-5 opacity-40" /><span class="mt-1 font-mono text-[8px] opacity-70">#{sheet.id}</span></div>
                                            {/if}
                                            <div class="absolute top-1 right-1"><span class={`block size-2 shrink-0 rounded-full ${isNeedsReviewSheet(sheet) ? 'ring-background bg-amber-500 ring-2' : 'ring-background bg-emerald-500 ring-2'}`}></span></div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class={`text-foreground group-hover:text-primary truncate font-sans font-bold ${queueViewMode === 'grid' ? 'mt-2 text-xs' : 'text-sm'}`} title={sheetLabel(sheet)}>{sheetLabel(sheet)}</div>
                                            <div class="text-muted-foreground mt-0.5 truncate font-mono text-[11px]">{candidateLabel(sheet) !== sheetLabel(sheet) && candidateLabel(sheet) !== sheet.client_capture_id ? `${candidateLabel(sheet)} · Sheet #${sheet.id}` : `Sheet #${sheet.id}`}</div>
                                            <span class={`mt-2 inline-flex rounded border px-2 py-0.5 font-mono text-[9px] font-bold tracking-wide uppercase ${isNeedsReviewSheet(sheet) ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'}`}>{sheetStatus(sheet)}</span>
                                            {#if typeof failure.message === 'string'}
                                                <div class="mt-2 space-y-1 text-xs leading-relaxed" data-omr-sheet-failure>
                                                    <p class="text-destructive">{failure.message}</p>
                                                    {#if typeof failure.stage === 'string'}<p class="text-muted-foreground">Stage: {titleCase(failure.stage)}</p>{/if}
                                                    {#if typeof failure.recovery === 'string'}<p class="text-foreground">{failure.recovery}</p>{/if}
                                                    {#if typeof failure.support_code === 'string'}<p class="text-muted-foreground break-all font-mono">{failure.support_code}</p>{/if}
                                                </div>
                                            {/if}
                                            <div class="mt-2 flex items-center justify-between gap-2"><span class="text-muted-foreground text-[10px]">{isNeedsReviewSheet(sheet) ? 'Action required' : 'Processed'}</span><span class="text-primary shrink-0 text-[11px] font-bold transition-transform group-hover:translate-x-0.5" aria-hidden="true">Review Sheet →</span></div>
                                        </div>
                                    </button>
                                {/each}
                            </div>
                        {:else}
                            <div class="border-border/60 bg-muted/20 text-muted-foreground rounded-lg border border-dashed px-3 py-8 text-center text-sm" data-omr-sheet-queue-empty role="status">
                                {sheets.length === 0 ? 'No sheets are in this run yet. Use the capture controls to add one.' : 'No sheets match this queue filter.'}
                            </div>
                        {/if}
                        {#if Object.keys(contextSummary).length > 0}
                            <dl class="border-border/60 bg-muted/20 mt-3 grid min-w-0 grid-cols-2 gap-2 rounded-lg border p-3 font-mono text-xs" data-omr-context-summary>
                                {#each Object.entries(contextSummary) as [label, value] (label)}
                                    <div class="min-w-0"><dt class="text-muted-foreground text-[10px] uppercase">{titleCase(label)}</dt><dd class="text-foreground mt-0.5 truncate font-medium">{displayValue(value)}</dd></div>
                                {/each}
                            </dl>
                        {/if}
                        {#if queueContent}<div class="mt-3" data-omr-queue-slot>{@render queueContent()}</div>{/if}
                        {#if selectionContent}<div class="mt-3" data-omr-selection-slot>{@render selectionContent()}</div>{/if}
                        {#if attentionItems.length > 0 || contextDetails.length > 0 || allRecoveryItems.length > 0}
                            <details class="border-border/70 bg-muted/20 text-muted-foreground mt-3 rounded-lg border px-3 py-2.5 text-xs leading-relaxed">
                                <summary class="text-foreground cursor-pointer font-semibold">
                                    Run details and recovery
                                    {#if needsReviewSheets.length > 0}<span class="text-amber-700 dark:text-amber-300">· {needsReviewSheets.length} need review</span>{:else if attentionCount > 0}<span>· {attentionCount} need attention</span>{/if}
                                </summary>
                                {#if contextDetails.length > 0}
                                    <dl class="mt-2 grid min-w-0 grid-cols-2 gap-2 font-mono text-xs sm:grid-cols-4">
                                        {#each contextDetails as detail (detail.label)}
                                            <div class="min-w-0"><dt class="text-muted-foreground text-[10px] uppercase">{detail.label}</dt><dd class="text-foreground mt-0.5 truncate font-medium" title={detail.value}>{detail.value}</dd></div>
                                        {/each}
                                    </dl>
                                {/if}
                                {#if attentionItems.length > 0}
                                    <ul class="mt-2 space-y-1">
                                        {#each attentionItems as attention, index (`${attention.owner ?? 'owner'}-${index}`)}
                                            <li>{attention.owner ?? 'Assigned owner'} · {attention.count ?? 0} item{attention.count === 1 ? '' : 's'}</li>
                                        {/each}
                                    </ul>
                                {/if}
                                {#if allRecoveryItems.length > 0}
                                    <div class="mt-2 space-y-3" data-omr-review-recovery>
                                        {#each allRecoveryItems as recovery, index (`${recovery.capture_id ?? recovery.support_code ?? index}`)}
                                            <OmrRecoveryCard {recovery} onAction={recoveryHandler} busy={recoveryBusy} />
                                        {/each}
                                    </div>
                                {/if}
                            </details>
                        {/if}
                        <div class="border-border/60 bg-muted/20 text-muted-foreground mt-3 flex flex-col gap-2 rounded-lg border px-3 py-2.5 text-xs leading-relaxed" data-omr-queue-progress role="status" aria-live="polite">
                            <span>
                                {#if progressKnown}{progressCompleted} of {progressTotal} units processed{:else if progressCompleted > 0}{progressCompleted} unit{progressCompleted === 1 ? '' : 's'} processed · total not yet known{:else}Waiting for the server to report processed units{/if}
                            </span>
                            {#if activeSheet}<span class="text-foreground truncate font-medium">Selected: {sheetLabel(activeSheet)}</span>{/if}
                        </div>
                    </div>
                    {#if onComplete && (sheets.length > 0 || projection.execution_kind === 'production' || projection.commands.some((c) => c.includes('complete') || c.includes('compare')))}
                        <footer class="border-border/60 shrink-0 border-t p-2 sm:p-3">
                            <OmrCompletionBar {projection} onComplete={handleComplete} />
                        </footer>
                    {/if}
                </section>
            </div>
        </div>
    </div>
    <OmrBatchQueueModal
        open={batchModalOpen}
        {sheets}
        scannedCount={sheets.length}
        {onRetakeSheet}
        onDiscardSheet={onDiscardSheet ? (sheet) => { batchModalOpen = false; onDiscardSheet(sheet); } : undefined}
        totalCount={progressTotal ?? sheets.length}
        flaggedCount={needsReviewSheets.length}
        canCommit={needsReviewSheets.length === 0 && sheets.length > 0 && onComplete !== undefined && (projection.commands.includes('production_complete') || projection.commands.includes('diagnostic_complete') || projection.commands.includes('diagnostic_compare'))}
        onClose={() => {
            batchModalOpen = false;
            if (stageView === 'queue') onStageViewChange?.('camera');
        }}
        onSelectSheet={(sheet) => {
            if (selectSheet(sheet)) changeStageView('review');
        }}
        onOpenTriage={() => changeStageView('triage')}
        onCommit={() => {
            if (projection.commands.includes('production_complete')) void handleComplete('production_complete');
            else if (projection.commands.includes('diagnostic_complete')) void handleComplete('diagnostic_complete');
            else if (projection.commands.includes('diagnostic_compare')) void handleComplete('diagnostic_compare');
        }}
    />


    <OmrShortcutsModal
        open={shortcutsModalOpen}
        onClose={() => (shortcutsModalOpen = false)}
    />
</div>

<style>
    [data-omr-workbench-flow]
        > div:first-child
        > :global([data-omr-stage='capture']) {
        flex: 1 1 0%;
        min-height: 0;
    }
    @media (max-width: 767px) {
        .omr-workbench :global(button),
        .omr-workbench :global(select) {
            min-height: 44px;
            min-width: 44px;
        }
    }

</style>
