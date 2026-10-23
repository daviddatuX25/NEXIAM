<script>
    import { onMount, untrack } from 'svelte';
    import { router, Link } from '@inertiajs/svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Textarea } from '@/Components/ui/textarea';
    import * as Tabs from '@/Components/ui/tabs';
    import * as Dialog from '@/Components/ui/dialog';
    import * as Card from '@/Components/ui/card';
    import { Badge } from '@/Components/ui/badge';
    import { Cpu, GraduationCap, Calendar, CheckCircle2, Clock, FileEdit, MessageSquare, StickyNote, Eye, FlaskConical, MoreHorizontal, ArrowLeft, Layers, History, GitCompare, Sparkles, ChevronRight, SlidersHorizontal, X } from 'lucide-svelte';
    import TabFmBenchmarkCalibrator from '@/Components/admission-support/TabFmBenchmarkCalibrator.svelte';
    import TabFmRevisionCompare from '@/Components/admission-support/TabFmRevisionCompare.svelte';
    import TabFmStickyNoteBoard from '@/Components/admission-support/TabFmStickyNoteBoard.svelte';
    import TabFmResultView from '@/Components/admission-support/TabFmResultView.svelte';
    import GovernanceDiscussionPanel from '@/Components/admission-support/GovernanceDiscussionPanel.svelte';
    import { newIdempotencyKey } from '@/lib/utils';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    import { governanceRoute as route, governanceRequest, governanceError, GovernanceRequestError, invalidatePreview, beginPreview, receivePreview, isUncertainCommandOutcome, reusePendingGovernancePayload, beginGovernanceRequest, isCurrentGovernanceRequest, applyGovernanceNoteDetail, reconcileGovernanceNoteEditor, reconcileStudioGovernance, classifyPreviewResponse, focusGovernanceTarget } from '@/lib/governanceRequests';
    import { benchmarkStatusLabel, cloneTabFmContent, hasIncompleteAuthoredRange } from '@/lib/tabFmRangeAuthoring';

    let { governance = {} } = $props();
    let programId = $state('');
    let cycleId = $state('');
    let showScopeChooser = $state(false);
    let comparisonData = $state(null);
    let comparisonBaselineId = $state('');
    let comparisonRevisionId = $state('');
    let comparisonBusy = $state(false);
    let activeTab = $state('calibrate');
    let selectedTool = $state(null);
    let cutoverReason = $state('');
    let cutoverReviewed = $state(false);
    let discussionTopic = $state('benchmark');
    let selectedBand = $state('cutoff');
    let previewOpen = $state(false);
    let previewMobileTab = $state('inputs');
    let moreOpen = $state(false);
    let content = $state(null);
    let savedContent = $state(null);
    let draft = $state(null);
    let recovery = $state(null);
    let recoveryContent = $state(null);
    let activeSet = $state(null);
    let reason = $state('');
    let busy = $state(false);
    let error = $state('');
    let notice = $state('');
    let denied = $state(false);
    let pendingCommand = $state(null);
    let historySequence = 0;
    let comparisonSequence = 0;
    let noteSequence = 0;
    let hydratedScope = null;
    let allowActivationReload = false;
    let benchmarkHistory = $state({ items: [], pagination: null });
    let inspectedRevision = $state(null);
    let publishedReceipt = $state(null);
    let confirmation = $state(null);
    let confirmationOpen = $state(false);
    let confirmed = $state(false);
    let noteBoard = $state(governance.notes ?? { items: [], pagination: null, counts: null });
    let noteSearch = $state('');
    let selectedNoteId = $state(null);
    let selectedNote = $state(null);
    let noteLoading = $state(false);
    let noteDraft = $state(null);
    let latestNoteDraft = $state(null);
    let publishedNote = $state(null);
    let noteText = $state('');
    let noteAudience = $state('program');
    let noteHistory = $state({ items: [], pagination: null });
    let publicationHistory = $state({ items: [], pagination: null });
    let copyCycle = $state('');
    let copySource = $state('');
    let noteInspected = $state(null);
    let importText = $state('');
    let importReason = $state('');
    let source = $state('active');
    let gwa = $state('');
    let aptitudeInputs = $state({});
    let preview = $state({ status: 'idle', current: null, result: null, error: '' });
    let previewController = null;
    const program = $derived(governance.selected_program);
    const cycle = $derived(governance.selected_cycle);
    const scope = $derived(`${program?.id ?? ''}:${cycle?.id ?? ''}`);
    const capabilities = $derived(denied ? {} : governance.capabilities ?? {});
    const features = $derived(governance.schema_features ?? []);
    const modelFeatures = $derived(features.filter((feature) => feature.key === 'gwa' || feature.aptitude_area));
    const inputSchema = $derived(governance.input_schema ?? { subjects: [], activities: [] });
    const aptitudeAreas = $derived(governance.aptitude_areas ?? []);
    const canBenchmark = $derived(!!capabilities['admission.benchmark.governance.view'] || !!capabilities['admission.benchmark.draft.edit'] || !!capabilities['admission.benchmark.preview']);
    const dirty = $derived(JSON.stringify(content) !== JSON.stringify(savedContent));
    const noteDirty = $derived(noteText !== (noteDraft?.content ?? ''));
    const noteServerChanged = $derived(noteDraft?.version !== latestNoteDraft?.version || noteDraft?.current_revision_id !== latestNoteDraft?.current_revision_id || noteDraft?.content !== latestNoteDraft?.content);
    const pendingNoteSaveRetry = $derived(pendingCommand?.action === 'advising-note.save');
    const pendingDraftSave = $derived(['benchmarks.draft.save', 'benchmarks.draft.start'].includes(pendingCommand?.action));
    const tabBadges = $derived(governance.tab_badges ?? {});
    const previewHasIncompleteRanges = $derived(source === 'draft' && hasIncompleteAuthoredRange(tierContent(draft?.content)));
    const previewFingerprint = $derived(JSON.stringify({ scope, source, revision: source === 'draft' ? draft?.current_revision_id : null, activation: activeSet?.activation_id, gwa, aptitudeInputs }));
    const previewComplete = $derived(!previewHasIncompleteRanges && gwa !== '' && gwa != null && Number.isFinite(Number(gwa)) && aptitudeAreas.length > 0 && aptitudeAreas.every((area) => aptitudeInputs[area.code] !== '' && aptitudeInputs[area.code] != null && Number.isFinite(Number(aptitudeInputs[area.code]))) && (source === 'active' ? !!activeSet : !!draft?.current_revision_id));
    const diffs = $derived.by(() => {
        const rows = [];
        const reviewed = confirmation?.content ?? inspectedRevision?.content ?? draft?.content;
        if (!reviewed) return rows;
        for (const level of ['1', '2', '3', '4', '5']) {
            for (const feature of features) {
                const before = governance.active_content?.tiers?.[level]?.values?.[feature.key] ?? null;
                const after = reviewed.tiers?.[level]?.values?.[feature.key] ?? null;
                if (JSON.stringify(before) !== JSON.stringify(after)) rows.push({ level, feature, before, after });
            }
        }
        return rows;
    });
    const breadcrumbs = [{ label: 'Setup', href: '/setup' }, { label: 'Decision Support' }];
    onMount(() => {
        const removeBefore = router.on('before', (event) => {
            if (allowActivationReload) {
                allowActivationReload = false;
                return;
            }
            const visit = event.detail.visit;
            const target = new URL(visit.url, window.location.origin);
            const isSameScopeReload = visit.method === 'get'
                && !busy
                && !pendingCommand
                && target.pathname === window.location.pathname
                && [String(program?.id ?? ''), program?.code].includes(target.searchParams.get('program_id') ?? target.searchParams.get('program'))
                && [String(cycle?.id ?? ''), cycle?.code].includes(target.searchParams.get('cycle_id') ?? target.searchParams.get('cycle'));
            if (!isSameScopeReload && (dirty || noteDirty || busy || pendingCommand)) {
                event.preventDefault();
                error = pendingCommand && !busy ? 'The last command outcome is unknown. Retry the same action before leaving.' : busy ? 'Wait for the current action to finish before leaving.' : 'Save or discard unsaved benchmark and note changes before leaving.';
            }
        });
        const beforeUnload = (event) => {
            if (dirty || noteDirty || busy || pendingCommand) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', beforeUnload);
        return () => {
            removeBefore();
            window.removeEventListener('beforeunload', beforeUnload);
        };
    });

    function tierContent(value) {
        return cloneTabFmContent(value);
    }

    function measurementLabel(feature) {
        const source = feature?.source_definition ?? {};
        const supported = ['percentile_rank', 'percentile', 'gwa_scale', 'count'];
        const unit = [feature?.declared_unit, source.unit, source.scale, feature?.declared_scale]
            .find((value) => typeof value === 'string' && supported.includes(value.trim()));
        if (unit) {
            return ({
                percentile_rank: 'Percentile rank',
                percentile: 'Percentile',
                gwa_scale: 'GWA scale',
                count: 'Count',
            })[unit.trim()];
        }
        return 'unit unverified';
    }


    $effect(() => {
        const incomingScope = scope;
        const incomingGovernance = governance;
        untrack(() => {
            const incomingProgram = incomingGovernance.selected_program;
            const incomingCycle = incomingGovernance.selected_cycle;
            if (incomingScope !== hydratedScope) {
                hydratedScope = incomingScope;
                activeTab = incomingGovernance.initial_tab === 'tools' ? 'calibrate' : incomingGovernance.initial_tab ?? 'calibrate';
                selectedTool = incomingGovernance.initial_tool ?? null;
                selectedBand = 'cutoff'; previewOpen = false;
                cutoverReason = ''; cutoverReviewed = false;
                showScopeChooser = !incomingProgram || !incomingCycle;
                discussionTopic = ['benchmark', 'quota', 'advising'].find((topic) => incomingGovernance.capabilities?.[`admission.governance-discussion.${topic}.read`]) ?? 'benchmark';
                programId = incomingProgram ? String(incomingProgram.id) : '';
                const defaultCycle = incomingCycle ?? (incomingGovernance.cycles ?? []).find((c) => c.is_active) ?? incomingGovernance.cycles?.[0] ?? null;
                cycleId = defaultCycle ? String(defaultCycle.id) : '';
                draft = incomingGovernance.draft ?? null;
                recovery = incomingGovernance.recovery ?? null;
                recoveryContent = tierContent(incomingGovernance.recovery_content);
                activeSet = incomingGovernance.active_set ?? null;
                content = tierContent(draft?.content ?? incomingGovernance.active_content ?? recoveryContent);
                savedContent = tierContent(draft?.content ?? incomingGovernance.active_content ?? recoveryContent);
                noteBoard = incomingGovernance.notes ?? { items: [], pagination: null, counts: null };
                selectedNoteId = null; selectedNote = null; noteLoading = false;
                noteDraft = null; latestNoteDraft = null; publishedNote = null; noteText = '';
                noteHistory = { items: [], pagination: null }; publicationHistory = { items: [], pagination: null };
                noteAudience = 'program';
                if (incomingGovernance.note_detail) applyNoteDetail({ note: incomingGovernance.note_detail });
                benchmarkHistory = { items: incomingGovernance.benchmark_revision_history ?? [], pagination: null };
                aptitudeInputs = Object.fromEntries((incomingGovernance.aptitude_areas ?? []).map((area) => [area.code, '']));
                source = activeSet ? 'active' : draft?.current_revision_id ? 'draft' : 'active';
                comparisonData = incomingGovernance.version_comparison ?? null;
                comparisonBaselineId = comparisonData?.baseline?.id ? String(comparisonData.baseline.id) : activeSet?.id ? String(activeSet.id) : '';
                comparisonRevisionId = comparisonData?.revision?.id ? String(comparisonData.revision.id) : draft?.current_revision_id ? String(draft.current_revision_id) : '';
                inspectedRevision = null;
                noteInspected = null;
                publishedReceipt = null;
                pendingCommand = null;
                reason = ''; error = ''; notice = ''; denied = false;
                previewController?.abort();
                preview = { status: 'idle', current: null, result: null, error: '' };
            } else {
                const refreshed = reconcileStudioGovernance({
                    activeSet, draft, content, savedContent, recovery, recoveryContent, noteBoard, benchmarkHistory, dirty, busy, pendingCommand,
                }, incomingGovernance);
                activeSet = refreshed.activeSet;
                draft = refreshed.draft;
                content = tierContent(refreshed.content);
                savedContent = tierContent(refreshed.savedContent);
                recovery = refreshed.recovery;
                recoveryContent = tierContent(refreshed.recoveryContent);
                noteBoard = refreshed.noteBoard;
                benchmarkHistory = refreshed.benchmarkHistory;
            }
        });
    });

    $effect(() => {
        previewFingerprint;
        untrack(() => {
            previewController?.abort();
            preview = invalidatePreview(preview);
        });
        return () => previewController?.abort();
    });

    $effect(() => {
        const tab = activeTab;
        const tool = selectedTool;
        const noteId = selectedNoteId;
        const currentProgram = program;
        const currentCycle = cycle;
        if (typeof window === 'undefined' || !currentProgram || !currentCycle || showScopeChooser) return;
        untrack(() => {
            const url = new URL(window.location.href);
            url.searchParams.set('program_id', String(currentProgram.id));
            url.searchParams.set('cycle_id', String(currentCycle.id));
            url.searchParams.set('tab', ['notes', 'discussion'].includes(tab) ? tab : 'tools');
            if (tool) url.searchParams.set('tool', tool); else url.searchParams.delete('tool');
            if (tab === 'notes' && noteId) url.searchParams.set('note_id', String(noteId)); else url.searchParams.delete('note_id');
            if (url.href !== window.location.href) router.replace({ url: url.pathname + url.search, preserveState: true, preserveScroll: true });
        });
    });
    $effect(() => {
        const currentNotice = notice;
        if (!currentNotice) return;
        toastSuccess(currentNotice);
        const timer = setTimeout(() => {
            if (notice === currentNotice) notice = '';
        }, 5000);
        return () => clearTimeout(timer);
    });
    $effect(() => {
        const currentError = error;
        if (!currentError) return;
        toastError(currentError);
    });

    function selectScope() {
        if (!programId || !cycleId) { error = 'Choose an authorized program and academic year/semester before opening the context.'; return; }
        if (dirty || noteDirty || busy || pendingCommand) {
            error = 'Save or discard unsaved changes and finish pending actions before changing programme or academic year.';
            programId = program ? String(program.id) : '';
            cycleId = cycle ? String(cycle.id) : '';
            return;
        }
        router.get(route('setup.decision-support.index'), { program_id: programId, cycle_id: cycleId, tab: ['notes', 'discussion'].includes(activeTab) ? activeTab : 'tools', ...(selectedTool ? { tool: selectedTool } : {}), ...(governance.return_to ? { return_to: governance.return_to } : {}) }, { preserveState: false, preserveScroll: true });
    }
    function beginScopeChange() {
        if (dirty || noteDirty || busy || pendingCommand) {
            error = 'Save or discard unsaved changes and finish pending actions before changing programme or academic year.';
            return;
        }
        programId = String(program?.id ?? '');
        cycleId = String(cycle?.id ?? '');
        showScopeChooser = true;
    }

    function cancelScopeChange() {
        programId = String(program?.id ?? '');
        cycleId = String(cycle?.id ?? '');
        showScopeChooser = false;
        error = '';
    }


    async function command(action, payload, cycleOnly = false) {
        if (busy) return null;
        const capturedScope = scope;
        const fingerprint = JSON.stringify({ scope, action, payload });
        if (pendingCommand && pendingCommand.fingerprint !== fingerprint) {
            error = 'A previous command outcome is unknown. Retry that exact action before starting another change.';
            return null;
        }
        if (!pendingCommand) pendingCommand = { fingerprint, key: newIdempotencyKey(), action, payload: { ...payload } };
        busy = true; error = ''; notice = '';
        try {
            const data = await governanceRequest(route(`setup.admission-support.${action}`, cycleOnly ? { cycle: cycle.id } : { program: program.id, cycle: cycle.id }), { ...payload, idempotency_key: pendingCommand.key });
            if (scope !== capturedScope) return null;
            pendingCommand = null;
            return data;
        } catch (failure) {
            if (scope === capturedScope) {
                error = governanceError(failure);
                if (!isUncertainCommandOutcome(failure)) pendingCommand = null;
                if (failure instanceof GovernanceRequestError && failure.status === 403) { denied = true; preview = invalidatePreview(preview); }
            }
            return null;
        } finally { if (scope === capturedScope) busy = false; }
    }

    async function saveDraft() {
        const isStarting = (!draft?.current_revision_id && !content) || pendingCommand?.action === 'benchmarks.draft.start';
        const action = isStarting ? 'benchmarks.draft.start' : 'benchmarks.draft.save';
        const payload = pendingCommand?.action === action
            ? pendingCommand.payload
            : isStarting
                ? { expected_version: 0, reason }
                : { content, expected_version: draft?.current_version ?? 0, reason };
        if (!String(payload.reason ?? '').trim() || String(payload.reason).length > 500 || (!isStarting && !payload.content)) {
            error = 'Enter a reason of up to 500 characters before saving.';
            return false;
        }
        const data = await command(action, payload);
        if (!data) return false;
        draft = { id: data.revision.draft_id, current_version: data.revision.version, current_revision_id: data.revision.id, content: data.revision.content, base_benchmark_set_id: data.revision.base_benchmark_set_id, feature_schema_digest: data.revision.feature_schema_digest };
        content = tierContent(data.revision.content); savedContent = tierContent(data.revision.content);
        reason = ''; notice = isStarting ? 'Draft started. Nothing has been published or put in use.' : `Draft v${data.revision.version} saved. Live benchmarks are unchanged.`;
        await loadBenchmarkHistory(1);
        const recoveryRefreshed = await refreshRecovery();
        await refreshGovernanceProjection();
        return !dirty && !busy && !pendingCommand && !error && (!recovery || recoveryRefreshed);
    }

    async function refreshRecovery() {
        if (!recovery || !program || !cycle) return false;
        try {
            const data = await governanceRequest(route('setup.admission-support.governance.show', { program: program.id, cycle: cycle.id }));
            if (!data.recovery) return false;
            recovery = { ...recovery, ...data.recovery };
            return true;
        } catch (failure) {
            error = governanceError(failure);
            if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
            return false;
        }
    }
    async function recoverDraft() {
        if (!capabilities['admission.benchmark.draft.edit'] || !recovery || recovery.program_in_source !== true || draft?.current_revision_id || recovery.status !== 'available') return;
        if (!reason.trim()) { error = 'Enter a reason before recovering this historical set as a draft.'; return; }
        const data = await command('benchmarks.draft.recover', {
            expected_version: draft?.current_version ?? 0,
            expected_source_digest: recovery.source_snapshot_digest,
            reason,
        });
        if (!data) return;
        draft = { id: data.revision.draft_id, current_version: data.revision.version, current_revision_id: data.revision.id, content: data.revision.content, base_benchmark_set_id: data.revision.base_benchmark_set_id, feature_schema_digest: data.revision.feature_schema_digest };
        content = tierContent(data.revision.content);
        savedContent = tierContent(data.revision.content);
        recovery = { ...recovery, ...(data.recovery ?? {}), status: 'draft' };
        reason = '';
        notice = `Historical set #${recovery.source_set_id} recovered as draft v${data.revision.version}. It remains unpublished and inactive.`;
        await loadBenchmarkHistory(1);
        await refreshRecovery();
        await refreshGovernanceProjection();
    }

    async function rebaseDraft() {
        if (!capabilities['admission.benchmark.draft.edit'] || !draft?.current_revision_id || !activeSet || dirty) return;
        if (!reason.trim()) { error = 'Enter a specific reason in the benchmark editor before rebasing the saved draft.'; return; }
        const data = await command('benchmarks.draft.rebase', { expected_version: draft.current_version, benchmark_set_id: activeSet.id, expected_content_sha256: activeSet.content_sha256, reason });
        if (!data) return;
        draft = { id: data.revision.draft_id, current_version: data.revision.version, current_revision_id: data.revision.id, content: data.revision.content, base_benchmark_set_id: data.revision.base_benchmark_set_id, feature_schema_digest: data.revision.feature_schema_digest };
        content = tierContent(data.revision.content); savedContent = tierContent(data.revision.content);
        reason = ''; notice = `Saved draft rebased as v${data.revision.version} to active set #${data.revision.base_benchmark_set_id}. Live benchmarks are unchanged.`;
        await loadBenchmarkHistory(1);
        await refreshGovernanceProjection();
    }

    async function loadBenchmarkHistory(page = 1) {
        const capturedScope = scope;
        const sequence = ++historySequence;
        try {
            const data = await governanceRequest(`${route('setup.admission-support.benchmarks.history', { program: program.id, cycle: cycle.id })}?page=${page}&per_page=20`);
            if (capturedScope === scope && sequence === historySequence) benchmarkHistory = data;
        } catch (failure) {
            if (capturedScope === scope && sequence === historySequence) {
                error = governanceError(failure);
                if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
            }
        }
    }
    async function loadComparison(setId = comparisonBaselineId, revisionId = comparisonRevisionId) {
        if (!capabilities['admission.benchmark.draft.edit'] && !capabilities['admission.benchmark.preview']) return;
        if (!revisionId) {
            error = 'Choose an exact saved revision before comparing. The working copy is shown separately.';
            return;
        }
        const capturedScope = scope;
        const sequence = ++comparisonSequence;
        comparisonData = null;
        comparisonBusy = true;
        error = '';
        const query = new URLSearchParams({ revision_id: String(revisionId) });
        if (setId) query.set('set_id', String(setId));
        try {
            const data = await governanceRequest(`${route('setup.admission-support.benchmarks.comparison', { program: program.id, cycle: cycle.id })}?${query.toString()}`);
            if (capturedScope === scope && sequence === comparisonSequence) {
                comparisonData = data.comparison;
                comparisonBaselineId = String(data.comparison.baseline.id);
                comparisonRevisionId = String(data.comparison.revision.id);
            }
        } catch (failure) {
            if (capturedScope === scope && sequence === comparisonSequence) {
                error = governanceError(failure);
                if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
            }
        } finally {
            if (capturedScope === scope && sequence === comparisonSequence) comparisonBusy = false;
        }
    }

    async function openComparison() {
        if (dirty && !draft?.current_revision_id) {
            error = 'Save the first draft before comparison. Unsaved working-copy values are never used as the saved revision.';
            return;
        }
        activeTab = 'version-compare';
        moreOpen = false;
        if (!benchmarkHistory.items.length) await loadBenchmarkHistory(1);
        comparisonRevisionId = String(draft?.current_revision_id ?? comparisonData?.revision?.id ?? benchmarkHistory.items[0]?.id ?? '');
        comparisonBaselineId = String(comparisonData?.baseline?.id ?? activeSet?.id ?? '');
        await loadComparison(comparisonBaselineId, comparisonRevisionId);
    }

    async function restoreBenchmark(revision) {
        if (!reason.trim()) { error = 'Enter a reason in the benchmark editor before restoring a revision.'; return; }
        if (dirty) { error = 'Save or discard unsaved benchmark changes before restoring.'; return; }
        const data = await command('benchmarks.draft.restore', { source_revision_id: revision.id, expected_version: draft?.current_version ?? 0, reason });
        if (!data) return;
        draft = { id: data.revision.draft_id, current_version: data.revision.version, current_revision_id: data.revision.id, content: data.revision.content, base_benchmark_set_id: data.revision.base_benchmark_set_id, feature_schema_digest: data.revision.feature_schema_digest };
        content = tierContent(data.revision.content); savedContent = tierContent(data.revision.content);
        notice = `Revision #${revision.id} restored as new draft v${data.revision.version}.`; reason = '';
        await loadBenchmarkHistory(1);
        await refreshRecovery();
        await refreshGovernanceProjection();
    }

    function applyNoteDetail(data) {
        const refreshed = applyGovernanceNoteDetail({
            selectedNote, selectedNoteId, draft: noteDraft, latestDraft: latestNoteDraft, text: noteText,
        }, data);
        selectedNote = refreshed.selectedNote;
        selectedNoteId = refreshed.selectedNoteId;
        noteDraft = refreshed.draft;
        latestNoteDraft = refreshed.latestDraft;
        publishedNote = refreshed.published;
        noteHistory = refreshed.history;
        publicationHistory = refreshed.publicationHistory;
        noteText = refreshed.text;
        noteAudience = noteDraft?.audience ?? 'program';
    }

    async function selectNote(noteId) {
        if (noteDirty) { error = 'Save or discard this note before opening another.'; return; }
        if (pendingCommand?.action?.startsWith('advising-note.')) {
            error = 'Retry the pending note action before opening another note.';
            return;
        }
        const token = beginGovernanceRequest(scope, noteSequence);
        noteSequence = token.sequence;
        selectedNoteId = Number(noteId);
        selectedNote = null; noteDraft = null; latestNoteDraft = null; publishedNote = null; noteText = ''; noteLoading = true;
        noteHistory = { items: [], pagination: null }; publicationHistory = { items: [], pagination: null };

        try {
            const data = await governanceRequest(`${route('setup.admission-support.governance.show', { program: program.id, cycle: cycle.id })}?note_id=${encodeURIComponent(noteId)}`);
            if (!isCurrentGovernanceRequest(token, scope, noteSequence)) return;
            noteBoard = data.notes ?? noteBoard;
            applyNoteDetail(data);
        } catch (failure) {
            if (isCurrentGovernanceRequest(token, scope, noteSequence)) {
                error = governanceError(failure);
                if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
            }
        }
        finally {
            if (isCurrentGovernanceRequest(token, scope, noteSequence)) noteLoading = false;
        }
    }

    function startNewNote() {
        if (!capabilities['admission.advising-note.draft.edit']) return;
        if (pendingCommand?.action?.startsWith('advising-note.')) {
            error = 'Retry the pending note action before starting a new note.';
            return;
        }
        if (noteDirty && !window.confirm('Discard unsaved changes to this note?')) return;
        noteSequence++;
        selectedNoteId = null;
        selectedNote = { note_id: null, version: 0, content: '' };
        noteLoading = false;
        noteDraft = null; latestNoteDraft = null; publishedNote = null; noteText = '';
        noteHistory = { items: [], pagination: null }; publicationHistory = { items: [], pagination: null };
        noteInspected = null; noteAudience = 'program'; error = '';
    }

    async function refreshNotes(notePage = 1, publicationPage = 1) {
        const token = beginGovernanceRequest(scope, noteSequence);
        noteSequence = token.sequence;
        try {
            const params = new URLSearchParams({ note_page: String(notePage), publication_page: String(publicationPage) });
            if (noteSearch.trim()) params.set('note_search', noteSearch.trim());
            if (selectedNoteId) params.set('note_id', String(selectedNoteId));
            const data = await governanceRequest(`${route('setup.admission-support.governance.show', { program: program.id, cycle: cycle.id })}?${params}`);
            if (!isCurrentGovernanceRequest(token, scope, noteSequence)) return;
            noteBoard = data.notes ?? noteBoard;
            if (selectedNoteId && data.note) applyNoteDetail(data);
        } catch (failure) {
            if (isCurrentGovernanceRequest(token, scope, noteSequence)) {
                error = governanceError(failure);
                if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
            }
        }
    }


    async function noteAction(operation, revision = null, staffCopy = false) {
        const action = `advising-note.${operation}`;
        const retryPayload = pendingCommand?.action === action
            ? reusePendingGovernancePayload(action, {}, pendingCommand)
            : null;
        let payload;
        if (retryPayload) {
            payload = retryPayload;
        } else {
            if (operation !== 'save' && operation !== 'copy' && noteDirty) { error = 'Save or discard unsaved note text before this action.'; return; }
            if (operation !== 'save' && operation !== 'copy' && !selectedNoteId) { error = 'Choose a saved note before this action.'; return; }
            payload = { expected_version: noteDraft?.version ?? 0, ...(selectedNoteId ? { note_id: selectedNoteId } : {}) };
            if (operation === 'save') {
                payload.content = noteText;
                payload.audience = noteAudience;
                if (selectedNoteId) payload.note_id = selectedNoteId;
            }
            if (operation === 'publish') { payload.note_id = selectedNoteId; payload.revision_id = confirmation?.operation === 'note-publish' ? confirmation.revisionId : noteDraft.current_revision_id; }
            if (operation === 'restore') { payload.note_id = selectedNoteId; payload.source_revision_id = revision.id; }
            if (operation === 'retract') {
                try {
                    const current = await governanceRequest(`${route('setup.admission-support.governance.show', { program: program.id, cycle: cycle.id })}?note_id=${selectedNoteId}&publication_page=1`);
                    const reviewedId = confirmation?.operation === 'note-retract' ? confirmation.revisionId : publishedNote?.revision_id;
                    if (current.note?.published?.revision_id !== reviewedId) { error = 'The shared note changed during review. Refresh and review the current version.'; return; }
                    const publication = (current.note.publication_history?.items ?? []).find((item) => item.operation === 'publish' && item.revision_id === reviewedId);
                    if (!publication) { error = 'The exact sharing receipt is unavailable; unsharing was not submitted.'; return; }
                    payload.note_id = selectedNoteId; payload.publication_id = publication.id;
                } catch (failure) { error = governanceError(failure); return; }
            }
            if (operation === 'copy') {
                if (staffCopy) {
                    payload = { expected_version: 0, source_revision_id: confirmation?.revisionId, target_cycle_id: cycle.id, target_audience: 'program' };
                } else {
                    if (!copyCycle || !copySource || String(copyCycle) === String(cycle.id)) { error = 'Choose an exact source revision and a different target academic year.'; return; }
                    payload = { expected_version: 0, source_revision_id: Number(copySource), target_cycle_id: Number(copyCycle) };
                }
            }
        }
        const data = await command(action, payload);
        if (!data) return;
        if (operation === 'save' && data.revision) {
            selectedNoteId = data.revision.note_id ?? data.revision.program_advising_note_id ?? selectedNoteId;
            noteDraft = {
                ...(noteDraft ?? {}),
                note_id: selectedNoteId,
                version: data.revision.version,
                current_revision_id: data.revision.id,
                content: data.revision.content,
            };
            latestNoteDraft = noteDraft;
        }
        if (operation === 'copy') {
            const targetCycle = governance.cycles?.find((item) => String(item.id) === String(payload.target_cycle_id));
            notice = `Revision #${payload.source_revision_id} copied to ${targetCycle?.staff_label ?? targetCycle?.name ?? `academic year #${payload.target_cycle_id}`} as a draft, not shared.`;
            if (staffCopy && data.revision) await selectNote(data.revision.note_id ?? data.revision.program_advising_note_id);
        } else {
            notice = `Note ${operation === 'save' ? 'draft saved' : operation === 'restore' ? 'restored as a new version' : operation === 'publish' ? 'shared' : 'unshared'}.`;
        }
        await refreshNotes(1, 1);
        await refreshGovernanceProjection();
    }
    function refreshGovernanceProjection() {
        router.reload({ only: ['governance'], preserveState: true, preserveScroll: true });
    }
    async function cutoverDraft() {
        if (dirty || busy || !cutoverReviewed || !cutoverReason.trim()) return;
        const data = await command('benchmarks.draft.cutover', { expected_version: draft?.current_version ?? 0, expected_source_digest: governance.input_cutover_source_digest, reason: cutoverReason });
        if (!data?.revision) return;
        draft = { id: data.revision.draft_id, current_version: data.revision.version, current_revision_id: data.revision.id, content: data.revision.content, base_benchmark_set_id: data.revision.base_benchmark_set_id, feature_schema_digest: data.revision.feature_schema_digest };
        content = tierContent(draft.content); savedContent = tierContent(draft.content);
        cutoverReason = ''; cutoverReviewed = false;
        notice = 'Aptitude and GWA successor saved. Existing published sources and results are unchanged.';
        await refreshGovernanceProjection();
    }
    async function reviewSet(operation, selectedSet = publishedReceipt) {
        if (busy) return;
        if (operation === 'publish' && (dirty || !draft?.current_revision_id)) { error = 'Save and review the exact draft revision before publication.'; return; }
        if (operation === 'publish') {
            if (governance.schema_cutover) {
                const review = governance.schema_cutover;
                if (!review.ready || !review.revision_ids_by_program || String(review.revision_ids_by_program[program.id]) !== String(draft.current_revision_id)) {
                    error = 'Every retained program needs an exact reviewed aptitude and GWA successor before publication.';
                    return;
                }
                confirmation = { operation, schemaCutover: true, revisionId: draft.current_revision_id, version: draft.current_version, content: tierContent(draft.content), revisionIds: review.revision_ids_by_program, manifest: Object.entries(review.revision_ids_by_program), baseDigest: review.source_content_sha256, predecessorSchemaDigest: review.expected_predecessor_schema_digest, activationId: activeSet?.activation_id ?? recovery?.source_activation_id ?? null };
            } else if (recovery?.status === 'draft') {
                busy = true;
                const refreshed = await refreshRecovery();
                busy = false;
                if (!refreshed || recovery?.status !== 'draft') {
                    error ||= 'Current recovery publication metadata could not be refreshed. No revision manifest was submitted.';
                    return;
                }
                const revisionIds = recovery.revision_ids_by_program ?? null;
                const incomplete = recovery.incomplete_programs ?? [];
                if ((revisionIds !== null && (typeof revisionIds !== 'object' || Array.isArray(revisionIds))) || (!revisionIds && incomplete.length === 0)) {
                    error = 'The current exact recovery revision manifest is unavailable. No revision map was inferred.';
                    return;
                }
                const manifest = revisionIds ? Object.entries(revisionIds).sort(([left], [right]) => Number(left) - Number(right)) : [];
                const complete = manifest.length === Number(recovery.source_program_count) && incomplete.length === 0;
                confirmation = {
                    operation,
                    recovery: true,
                    revisionId: draft.current_revision_id,
                    version: draft.current_version,
                    content: tierContent(draft.content),
                    revisionIds,
                    manifest,
                    incomplete,
                    publishBlocked: !complete || String(revisionIds?.[String(program.id)] ?? '') !== String(draft.current_revision_id),
                    baseDigest: recovery.expected_content_sha256,
                    recoverySourceSetId: recovery.source_set_id,
                    sourceSnapshotDigest: recovery.expected_source_snapshot_digest,
                    sourceActivationId: recovery.expected_source_activation_id,
                    activationId: recovery.source_activation_id,
                };
            } else {
                confirmation = { operation, revisionId: draft.current_revision_id, version: draft.current_version, content: tierContent(draft.content), baseDigest: activeSet?.content_sha256, activationId: activeSet?.activation_id ?? null };
            }
        } else {
            if (!selectedSet || busy) return;
            busy = true;
            try {
                const data = await governanceRequest(`${route('setup.admission-support.governance.show', { program: program.id, cycle: cycle.id })}?benchmark_set_id=${selectedSet.id}`);
                confirmation = { operation, set: data.benchmark_review.set, content: data.benchmark_review.content, activationId: activeSet?.activation_id ?? recovery?.source_activation_id ?? null };
            } catch (failure) { error = governanceError(failure); if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true; return; }
            finally { busy = false; }
        }
        confirmed = false; confirmationOpen = true;
    }

    async function confirmSet() {
        const reviewed = confirmation;
        if (!reviewed || !confirmed) return;
        let data;
        if (reviewed.operation === 'publish') {
            if (!reason.trim()) { error = 'Enter a publication reason in the review dialog.'; return; }
            if (reviewed.publishBlocked) { error = 'The exact complete recovery manifest is unavailable or incomplete. Review the listed source-program gaps; no partial recovery publication was submitted.'; return; }
            const publishPayload = reviewed.schemaCutover
                ? { revision_ids_by_program: reviewed.revisionIds, expected_content_sha256: reviewed.baseDigest, schema_cutover: true, expected_predecessor_schema_digest: reviewed.predecessorSchemaDigest, reason }
                : reviewed.recovery
                ? {
                    revision_ids_by_program: reviewed.revisionIds,
                    expected_content_sha256: reviewed.baseDigest,
                    recovery_source_set_id: reviewed.recoverySourceSetId,
                    expected_source_snapshot_digest: reviewed.sourceSnapshotDigest,
                    expected_source_activation_id: reviewed.sourceActivationId,
                    reason,
                }
                : { revision_ids_by_program: { [program.id]: reviewed.revisionId }, expected_content_sha256: reviewed.baseDigest, reason };
            data = await command('benchmarks.publish', publishPayload, true);
            if (data) { publishedReceipt = { ...data.set, reviewed_content: reviewed.content, reviewed_revision_id: reviewed.revisionId }; notice = `Set v${data.set.version} published. It is not active.`; }
        } else {
            data = await command('benchmarks.activate', { benchmark_set_id: reviewed.set.id, expected_current_activation_id: reviewed.activationId, expected_content_sha256: reviewed.set.content_sha256 }, true);
            if (data) {
                activeSet = { ...reviewed.set, activation_id: data.activation.id };
                publishedReceipt = null;
                notice = `Published set #${reviewed.set.id} activated for ${cycle.staff_label ?? cycle.name}.`;
                allowActivationReload = true;
                router.reload({ only: ['governance'], onFinish: () => { allowActivationReload = false; }, onSuccess: (page) => { const refreshed = page.props.governance; activeSet = refreshed.active_set; recovery = refreshed.recovery ?? null; recoveryContent = tierContent(refreshed.recovery_content); preview = invalidatePreview(preview); } });
            }
        }
        if (data) { confirmationOpen = false; reason = ''; }
    }

    async function importBenchmarks() {
        let payload;
        try { payload = JSON.parse(importText); } catch { error = 'The import payload is not valid JSON.'; return; }
        const data = await command('benchmarks.import', { payload, change_reason: importReason }, true);
        if (data) { publishedReceipt = data.set; notice = `Set #${data.set.id} imported, not activated. Review its exact identity before a separate activation.`; }
    }

    async function runPreview() {
        if (!previewComplete || !capabilities['admission.benchmark.preview']) return;
        previewController?.abort(); previewController = new AbortController();
        const identity = { requestId: newIdempotencyKey(), fingerprint: previewFingerprint };
        preview = beginPreview(preview, identity);
        const input = {
            gwa: Number(gwa),
            aptitudes: Object.fromEntries(aptitudeAreas.map((area) => [area.code, Number(aptitudeInputs[area.code])])),
        };
        const expectedIdentity = {
            requestId: identity.requestId,
            source,
            programId: program.id,
            cycleId: cycle.id,
            activationId: source === 'active' ? activeSet?.activation_id ?? null : null,
            setId: source === 'active' ? activeSet?.id ?? null : draft?.base_benchmark_set_id ?? null,
            revisionId: source === 'draft' ? draft?.current_revision_id ?? null : null,
            draftId: source === 'draft' ? draft?.id ?? null : null,
            baseSetId: source === 'draft' ? draft?.base_benchmark_set_id ?? null : null,
        };
        try {
            const data = await governanceRequest(route('setup.admission-support.sandbox.governance-preview', { program: program.id, cycle: cycle.id }), { source, ...(source === 'draft' ? { revision_id: draft.current_revision_id } : {}), request_id: identity.requestId, input }, previewController.signal);
            if (identity.fingerprint !== previewFingerprint || preview.current?.requestId !== identity.requestId) return;
            const evaluation = data.evaluation;
            const context = evaluation?.evaluation_context;
            const responseKind = classifyPreviewResponse(evaluation, expectedIdentity);
            if (responseKind === 'mismatch') {
                preview = { ...preview, status: 'error', error: 'The server result did not match the selected request, scope, or saved benchmark source. Run preview again.' };
                return;
            }
            const available = responseKind === 'evaluated' && evaluation.recommendations?.length > 0;
            preview = receivePreview(preview, identity, previewFingerprint, context?.request_id ?? null, evaluation, available);
            if (available) {
                previewMobileTab = 'results';
            }
            if (preview.current?.requestId === identity.requestId && preview.status === 'pending') preview = { ...preview, status: 'error', error: 'The server response did not match the current request identity. Run preview again.' };
        } catch (failure) {
            if (identity.fingerprint !== previewFingerprint || preview.current?.requestId !== identity.requestId || failure?.name === 'AbortError') return;
            preview = { ...preview, status: 'error', error: governanceError(failure) };
            if (failure instanceof GovernanceRequestError && failure.status === 403) denied = true;
        }
    }

    function fillSampleProfile(level = '2') {
        const sampleTier = content?.tiers?.[level]?.values ?? content?.tiers?.['3']?.values ?? {};
        gwa = String(sampleTier.gwa ?? (level === '1' ? 92.5 : level === '3' ? 83.0 : 88.5));
        aptitudeInputs = Object.fromEntries(
            aptitudeAreas.map((area) => [area.code, String(sampleTier[area.code] ?? (level === '1' ? 88 : level === '3' ? 74 : 80))])
        );
    }

    $effect(() => {
        if (typeof document === 'undefined') return;
        const main = document.getElementById('main-content');
        if (previewOpen) {
            document.body.style.overflow = 'hidden';
            if (main) main.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
            if (main) main.style.overflow = '';
        }
        return () => {
            document.body.style.overflow = '';
            if (main) main.style.overflow = '';
        };
    });
    const studioTourContext = $derived({
        entityKey: 'tabfm-studio',
        safetyKey: `${program?.id ?? 'none'}:${cycle?.id ?? 'none'}:${activeSet?.version ?? 'none'}`,
        freshness: 'current',
        hasActiveCycle: Boolean(cycle),
        cycleName: cycle?.staff_label ?? cycle?.name ?? null,
        hasActiveBenchmark: Boolean(activeSet),
        activeBenchmarkVersion: activeSet ? String(activeSet.version) : null,
    });
</script>

<StaffLayout {breadcrumbs}>
    <!-- Ambient Radial Glow Backdrop -->
    <div
        class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_at_80%_10%,oklch(0.82_0.12_145_/_0.15),transparent_40%),radial-gradient(ellipse_at_15%_80%,oklch(0.9_0.08_85_/_0.12),transparent_40%)] dark:bg-[radial-gradient(ellipse_at_80%_10%,oklch(0.38_0.1_145_/_0.15),transparent_40%),radial-gradient(ellipse_at_15%_80%,oklch(0.35_0.07_85_/_0.12),transparent_40%)]"
        aria-hidden="true"
    ></div>

    <div class="min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <PageHeaderBar
            title="Decision Support"
            description="Program benchmarks, advisory tools, notes and discussion."
            tourKey="admission-support"
            context={studioTourContext}
        />

        <div class="min-w-0 space-y-5">
            {#if error}
                <div role="alert" class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">
                    {error}
                    {#if error.startsWith('Conflict:')}
                        <p class="mt-2 text-foreground">Your input is kept. Inspect Changes, then explicitly reload the current context to reconcile; no overwrite was applied.</p>
                        <Button variant="outline" size="sm" class="mt-2" onclick={() => router.get(route('setup.decision-support.index'), { program_id: program.id, cycle_id: cycle.id, tab: ['notes', 'discussion'].includes(activeTab) ? activeTab : 'tools', ...(selectedTool ? { tool: selectedTool } : {}), ...(activeTab === 'notes' && selectedNoteId ? { note_id: selectedNoteId } : {}), ...(governance.return_to ? { return_to: governance.return_to } : {}) }, { preserveState: false })}>Reload current context (discard unsaved input)</Button>
                    {/if}
                </div>
            {/if}
            {#if notice}
                <div role="status" class="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-900 dark:text-emerald-100">
                    <CheckCircle2 class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <span>{notice}</span>
                </div>
            {/if}

            {#if showScopeChooser || !program || !cycle}
                <Card.Root class="overflow-hidden border-border/60 bg-card/85 shadow-sm backdrop-blur-sm">
                    <div class="border-b border-border/50 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-4 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary">
                                    <Cpu class="size-6" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="flex items-center gap-1.5 text-base sm:text-lg font-semibold tracking-tight text-foreground">
                                        <span>Choose program and academic year</span>
                                        <InfoPopover
                                            title="Program & cycle scope"
                                            content="Each degree program and academic year maintains its own isolated benchmark drafts, published versions, advising notes, and governance discussions."
                                        />
                                    </h2>
                                    <p class="text-xs text-muted-foreground">Open the configuration and staff advice available in your scope.</p>
                                </div>
                            </div>
                            {#if program && cycle}
                                <Button type="button" variant="ghost" size="sm" disabled={busy || !!pendingCommand} onclick={cancelScopeChange}>Cancel</Button>
                            {/if}
                        </div>
                    </div>

                    <Card.Content class="space-y-6 p-4 sm:p-6">
                        <form class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_auto]" onsubmit={(event) => { event.preventDefault(); selectScope(); }}>
                            <div class="min-w-0 space-y-1.5">
                                <div class="flex items-center gap-1.5">
                                    <label for="studio-program" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Academic Programme</label>
                                    <InfoPopover
                                        title="Target degree program"
                                        content="Select the degree program whose admission cutoffs, tier ranges, and advising notes you want to view or calibrate."
                                    />
                                </div>
                                <select id="studio-program" class="h-11 w-full min-w-0 rounded-lg border border-border bg-background px-3 text-sm font-medium" bind:value={programId} disabled={busy || !!pendingCommand}>
                                    <option value="">Select programme...</option>
                                    {#each governance.programs ?? [] as item (item.id)}
                                        <option value={String(item.id)}>{item.code} · {item.name}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="min-w-0 space-y-1.5">
                                <div class="flex items-center gap-1.5">
                                    <label for="studio-cycle" class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Academic Year · Semester</label>
                                    <InfoPopover
                                        title="Cycle version isolation"
                                        content="Benchmarks and shared advising notes are bound to a specific academic cycle so historical cohorts keep their exact evaluation rules."
                                    />
                                </div>
                                <select id="studio-cycle" class="h-11 w-full rounded-lg border border-border bg-background px-3 text-sm font-medium" bind:value={cycleId} disabled={busy || !!pendingCommand}>
                                    <option value="">Select academic year and semester...</option>
                                    {#each governance.cycles ?? [] as item (item.id)}
                                        <option value={String(item.id)}>{item.staff_label ?? item.name}{item.is_active ? ' (Current Active)' : ''}</option>
                                    {/each}
                                </select>
                            </div>
                            <Button type="submit" class="self-end h-11 px-5 font-semibold gap-1.5" disabled={busy || !!pendingCommand || !programId || !cycleId}>
                                <span>Open Workspace</span>
                                <ChevronRight class="size-4" />
                            </Button>
                        </form>

                        {#if (governance.programs ?? []).length > 0 && cycleId}
                            <div class="space-y-3 border-t border-border/50 pt-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Quick-Select Campus Offerings ({(governance.programs ?? []).length} Programs)</span>
                                    <span class="text-xs text-muted-foreground">Click any program to open its studio</span>
                                </div>
                                <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3">
                                    {#each governance.programs ?? [] as item (item.id)}
                                        <button
                                            type="button"
                                            disabled={busy || !!pendingCommand}
                                            onclick={() => {
                                                programId = String(item.id);
                                                selectScope();
                                            }}
                                            class={`group flex items-center justify-between gap-3 rounded-xl border p-3 text-left transition-all cursor-pointer ${String(item.id) === programId ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-border/70 bg-background/60 hover:border-primary/40 hover:bg-muted/30'}`}
                                        >
                                            <div class="flex min-w-0 items-center gap-3">
                                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 font-mono text-xs font-bold text-primary">
                                                    {item.code}
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-foreground">{item.code}</span>
                                                    <span class="block truncate text-xs text-muted-foreground">{item.name}</span>
                                                </span>
                                            </div>
                                            <ChevronRight class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </Card.Content>
                </Card.Root>
            {:else if denied}
                <p role="alert" class="rounded-lg border p-5 text-sm">Your access to this context is no longer available. Unsaved input has been kept; no further commands are enabled.</p>
            {:else}
                <!-- Active Context & Navigation Header Card -->
                <Card.Root class="overflow-hidden border-border/70 bg-card/85 shadow-sm backdrop-blur-sm" data-tour="tabfm-studio-card">
                    <div class="border-b border-border/50 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-4 sm:px-6 sm:py-4">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <nav aria-label="Selected programme context" class="flex w-full min-w-0 flex-none items-center gap-3 sm:w-auto sm:flex-1">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-primary/25 bg-primary/15 font-mono text-sm font-bold text-primary shadow-2xs">
                                    {program.code}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-base sm:text-lg font-bold tracking-tight text-foreground truncate">{program.name}</h2>
                                        <Badge variant="outline" class="font-mono text-xs border-primary/30 text-primary bg-background/80">{program.code}</Badge>
                                    </div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                        <span class="inline-flex items-center gap-1 font-medium text-foreground/80">
                                            <Calendar class="size-3.5 text-primary" />
                                            {cycle.staff_label ?? cycle.name}
                                        </span>
                                        <span aria-hidden="true">·</span>
                                        <span>{draft?.current_revision_id ? `Saved draft r${draft.current_revision_id} · v${draft.current_version}` : 'No saved draft'}</span>
                                        {#if recovery}
                                            <span aria-hidden="true">·</span>
                                            <span>{recovery.status === 'draft' ? 'Historical draft recovered' : 'Historical recovery available'}</span>
                                        {/if}
                                    </div>
                                </div>
                            </nav>

                            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:shrink-0" data-tour="benchmark-status-badge">
                                {#if activeSet}
                                    <Badge variant="default" class="gap-1.5 bg-emerald-600 text-white px-2.5 py-1 text-xs">
                                        <CheckCircle2 class="size-3.5" />
                                        <span>In use · v{activeSet.version}</span>
                                    </Badge>
                                {:else}
                                    <Badge variant="secondary" class="px-2.5 py-1 text-xs">Not in use</Badge>
                                {/if}
                                {#if governance.return_to}
                                    <Link href={governance.return_to} class="inline-flex h-9 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium hover:bg-muted">
                                        <ArrowLeft class="size-3.5" />
                                        <span>Return to Release Day</span>
                                    </Link>
                                {/if}
                                <Button type="button" variant="outline" size="sm" class="gap-1.5 h-9" disabled={busy || !!pendingCommand} onclick={beginScopeChange}>
                                    <Layers class="size-3.5" />
                                    <span>Switch Program</span>
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-muted/20 px-4 py-2.5 sm:px-6 flex flex-wrap items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2 text-muted-foreground">
                            {#if tabBadges.benchmarks}
                                <span role="status" class="inline-flex items-center gap-1.5 font-medium text-foreground">
                                    <Sparkles class="size-3.5 text-primary" />
                                    {benchmarkStatusLabel(tabBadges.benchmarks)}
                                </span>
                            {/if}
                        </div>
                        <details class="text-xs text-muted-foreground">
                            <summary class="cursor-pointer font-medium hover:text-foreground inline-flex items-center gap-1">
                                <span>Provenance &amp; Technical Digests</span>
                            </summary>
                            <dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 rounded-lg border bg-background p-3 text-[11px]">
                                <dt class="font-semibold text-foreground">Published set / activation</dt>
                                <dd class="font-mono">{activeSet ? `#${activeSet.id} · activation #${activeSet.activation_id}` : 'None'}</dd>
                                <dt class="font-semibold text-foreground">Draft revision</dt>
                                <dd class="font-mono">{draft?.current_revision_id ?? 'None'}</dd>
                                <dt class="font-semibold text-foreground">Content digest</dt>
                                <dd class="break-all font-mono">{activeSet?.content_sha256 ?? 'Unavailable'}</dd>
                                <dt class="font-semibold text-foreground">Schema digest</dt>
                                <dd class="break-all font-mono">{activeSet?.schema_sha256 ?? draft?.feature_schema_digest ?? 'Unavailable'}</dd>
                            </dl>
                        </details>
                    </div>
                </Card.Root>

                <div class="min-w-0">
                    <main id="studio-editor" tabindex="-1" class="min-w-0">
                        <Tabs.Root bind:value={activeTab} class="space-y-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/60 bg-muted/30 p-1.5 backdrop-blur-sm">
                                <Tabs.List class="flex h-auto flex-wrap justify-start gap-1 bg-transparent p-0">
                                    {#if canBenchmark}
                                    <Tabs.Trigger value="calibrate" class="gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold data-[state=active]:bg-background data-[state=active]:shadow-xs">
                                        <SlidersHorizontal class="size-3.5 text-primary" />
                                        <span>ML Engine &amp; Other Tools</span>
                                        {#if tabBadges.benchmarks}
                                            <span class="ml-0.5 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary" title={benchmarkStatusLabel(tabBadges.benchmarks)}>
                                                {tabBadges.benchmarks.label}
                                            </span>
                                        {/if}
                                    </Tabs.Trigger>
                                    {/if}
                                    {#if capabilities['admission.advising-note.view']}
                                        <Tabs.Trigger value="notes" class="gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold data-[state=active]:bg-background data-[state=active]:shadow-xs">
                                            <StickyNote class="size-3.5 text-primary" />
                                            <span>Advising Notes</span>
                                            {#if typeof tabBadges.notes?.total === 'number'}
                                                <span class="ml-0.5 rounded-full border bg-muted/60 px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                    {tabBadges.notes.total}
                                                </span>
                                            {/if}
                                        </Tabs.Trigger>
                                    {/if}
                                    {#if ['benchmark', 'quota', 'advising'].some((topic) => capabilities[`admission.governance-discussion.${topic}.read`])}
                                        <Tabs.Trigger value="discussion" class="gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold data-[state=active]:bg-background data-[state=active]:shadow-xs">
                                            <MessageSquare class="size-3.5 text-primary" />
                                            <span>Discussion</span>
                                            {#if typeof tabBadges.discussion?.unread_threads === 'number'}
                                                <span class="ml-0.5 rounded-full border bg-muted/60 px-2 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                    {tabBadges.discussion.unread_threads} unread
                                                </span>
                                            {/if}
                                        </Tabs.Trigger>
                                    {/if}
                                </Tabs.List>

                                <div class="flex items-center gap-2 pr-1">
                                    {#if capabilities['admission.benchmark.preview']}
                                        <Button variant="outline" size="sm" class="h-9 gap-1.5 font-semibold bg-background border-primary/40 text-primary hover:bg-primary/5 hover:border-primary/60" onclick={() => { source = draft?.current_revision_id ? 'draft' : 'active'; previewOpen = true; }}>
                                            <FlaskConical class="size-3.5 text-primary" />
                                            <span>Simulate Candidate</span>
                                        </Button>
                                    {/if}
                                    {#if canBenchmark}
                                    <details bind:open={moreOpen} class="relative">
                                        <summary class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-md border bg-background px-3 text-xs font-semibold hover:bg-muted/50">
                                            <MoreHorizontal class="size-3.5" />
                                            <span>More</span>
                                        </summary>
                                        <div class="absolute right-0 z-20 mt-1 grid min-w-52 gap-1 rounded-xl border bg-popover p-1.5 shadow-lg">
                                            <button type="button" class="flex min-h-9 items-center gap-2 rounded-lg px-2.5 text-left text-xs font-medium hover:bg-muted" onclick={() => { activeTab = 'sets'; moreOpen = false; }}>
                                                <CheckCircle2 class="size-3.5 text-primary" />
                                                <span>Versions &amp; Activation</span>
                                            </button>
                                            <button type="button" class="flex min-h-9 items-center gap-2 rounded-lg px-2.5 text-left text-xs font-medium hover:bg-muted" onclick={() => { activeTab = 'history'; loadBenchmarkHistory(1); moreOpen = false; }}>
                                                <History class="size-3.5 text-primary" />
                                                <span>Revision History</span>
                                            </button>
                                            {#if capabilities['admission.benchmark.draft.edit'] || capabilities['admission.benchmark.preview']}
                                                <button type="button" class="flex min-h-9 items-center gap-2 rounded-lg px-2.5 text-left text-xs font-medium hover:bg-muted" onclick={() => { activeTab = 'version-compare'; loadComparison(comparisonBaselineId, comparisonRevisionId); moreOpen = false; }}>
                                                    <GitCompare class="size-3.5 text-primary" />
                                                    <span>Compare Revisions</span>
                                                </button>
                                            {/if}
                                            {#if governance.comparison}
                                                <button type="button" class="flex min-h-9 items-center gap-2 rounded-lg px-2.5 text-left text-xs font-medium hover:bg-muted" onclick={() => { activeTab = 'comparison'; moreOpen = false; }}>
                                                    <Layers class="size-3.5 text-primary" />
                                                    <span>College Cutoffs</span>
                                                </button>
                                            {/if}
                                        </div>
                                    </details>
                                    {/if}
                                </div>
                            </div>
                        <Tabs.Content value="calibrate" class="space-y-4">
                            {#if canBenchmark}
                                <div class:hidden={!!selectedTool} class="space-y-6">
                                    <section class="space-y-3" aria-labelledby="ml-benchmarks-heading">
                                        <h2 id="ml-benchmarks-heading" class="flex items-center gap-1.5 text-base font-semibold">
                                            <span>ML benchmarks</span>
                                            <InfoPopover
                                                title="TabFM benchmark calibration"
                                                content="Configures the 5-tier reference exemplars (Honors, Regular, Cutoff, Other-strand, and Below-cutoff) used by the TabFM engine to evaluate applicant GWA and ICAT aptitude percentiles."
                                            />
                                        </h2>
                                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-card p-4">
                                            <div class="space-y-1"><h3 class="flex items-center gap-1.5 font-medium"><span>Program benchmarks</span> <Badge variant="outline">TabFM</Badge></h3><p class="text-sm text-muted-foreground">Core Aptitudes &amp; GWA</p></div>
                                            <Button variant="outline" class="min-h-11" disabled={busy || !!pendingCommand} onclick={() => { selectedTool = 'benchmarks'; }}>Adjust benchmarks <ChevronRight class="ml-1 size-4" /></Button>
                                        </div>
                                    </section>
                                    <section class="space-y-3" aria-labelledby="other-tools-heading">
                                        <h2 id="other-tools-heading" class="flex items-center gap-1.5 text-base font-semibold">
                                            <span>Other tools</span>
                                            <InfoPopover
                                                title="Advisory alignment profile"
                                                content="Configures preferred Senior High School feeder strands and CHED/Holland career alignment metadata for Release Day counseling without altering numeric ML scoring."
                                            />
                                        </h2>
                                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border bg-card p-4">
                                            <div class="space-y-1"><h3 class="font-medium">Strand Priority &amp; Career Alignment</h3><p class="text-sm text-muted-foreground">Program advisory preferences, separate from ML scoring.</p></div>
                                            <Button variant="outline" class="min-h-11" disabled={busy || !!pendingCommand} onclick={() => { selectedTool = 'profile'; }}>Adjust profile <ChevronRight class="ml-1 size-4" /></Button>
                                        </div>
                                    </section>
                                </div>
                                <div class:hidden={!selectedTool} class="space-y-4">
                                    <Button variant="ghost" class="min-h-11" disabled={busy || !!pendingCommand} onclick={() => { selectedTool = null; }}><ArrowLeft class="mr-2 size-4" />All tools</Button>
                                    {#if governance.requires_input_cutover}
                                        <section class="space-y-3 rounded-lg border border-warning/50 bg-muted/20 p-4" aria-label="Review model input cutover">
                                            <h3 class="font-medium">Review aptitude and GWA input cutover</h3>
                                            <p class="text-sm text-muted-foreground">This source includes retired model inputs. A successor removes subject grades, strand and RIASEC from ML features while keeping advisory preferences and historical versions. It does not publish or activate a set.</p>
                                            {#if capabilities['admission.benchmark.draft.edit']}
                                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" bind:checked={cutoverReviewed} />I reviewed the input boundary for this program.</label>
                                                <label for="cutover-reason" class="text-sm font-medium">Reason for schema change</label>
                                                <Textarea id="cutover-reason" bind:value={cutoverReason} maxlength={500} rows={2} disabled={busy} />
                                                <Button variant="outline" disabled={busy || dirty || (!!pendingCommand && pendingCommand.action !== 'benchmarks.draft.cutover') || !cutoverReviewed || !cutoverReason.trim()} onclick={cutoverDraft}>Create aptitude and GWA successor</Button>
                                            {/if}
                                        </section>
                                    {/if}
                                    <TabFmBenchmarkCalibrator {program} {cycle} features={modelFeatures} {inputSchema} {content} view={selectedTool === 'profile' ? 'profile' : 'core'} version={draft?.current_version ?? 0} canEdit={!!capabilities['admission.benchmark.draft.edit'] && !governance.requires_input_cutover} {busy} pendingSave={pendingDraftSave} {dirty} bind:reason bind:selectedBand onContentChange={(next) => { content = next; }} onSave={saveDraft} onReset={() => { content = tierContent(savedContent); }} {recovery} isRecoveryProposal={!!recovery && recovery.program_in_source === true && !draft?.current_revision_id} onRecover={recoverDraft} />
                                </div>
                            {:else}<p class="text-sm text-muted-foreground">Benchmark tools are not available in this context.</p>{/if}
                        </Tabs.Content>
                        <Tabs.Content value="notes" class="min-w-0">
                            <TabFmStickyNoteBoard notes={noteBoard} {selectedNoteId} {selectedNote} loading={noteLoading} bind:search={noteSearch} isShared={!!publishedNote} canEdit={!!capabilities['admission.advising-note.draft.edit']} canPublish={noteAudience === 'program' && !!capabilities['admission.advising-note.publish']} {busy} onSearch={() => refreshNotes(1, 1)} onSelect={selectNote} onNew={startNewNote} onShare={() => {
                                if (!selectedNoteId || noteDirty) { error = 'Save the exact note version before sharing.'; return; }
                                confirmation = { operation: publishedNote ? 'note-retract' : 'note-publish', content: publishedNote?.content ?? noteDraft?.content, revisionId: publishedNote?.revision_id ?? noteDraft?.current_revision_id };
                                confirmed = false; confirmationOpen = true;
                            }} onHistory={() => { refreshNotes(1, 1); focusGovernanceTarget('note-history'); }} onCopy={() => focusGovernanceTarget('copy-source')} onRevert={() => focusGovernanceTarget('note-history')} onPage={(page) => refreshNotes(page, 1)}>
                                {#if selectedNote}
                                    {#if publishedNote}<section class="space-y-1 border-b pb-3"><h3 class="text-sm font-medium">Currently shared · v{publishedNote.version}</h3><p class="whitespace-pre-wrap break-words text-sm">{publishedNote.content}</p><p class="text-xs text-muted-foreground">{publishedNote.author_name} · Shared by {publishedNote.publisher_name}</p></section>{/if}
                                    <div class="space-y-3">
                                        <label for="note-text" class="text-sm font-medium">{noteDraft ? `Draft v${noteDraft.version}` : 'New note'}{noteDirty ? ' · unsaved changes' : ''}</label>
                                        <Textarea id="note-text" bind:value={noteText} rows={5} maxlength={20000} disabled={busy || !capabilities['admission.advising-note.draft.edit']} placeholder="Write a short program note" />
                                        <fieldset class="space-y-2" disabled={!!selectedNoteId || busy || !capabilities['admission.advising-note.draft.edit']}>
                                            <legend class="inline-flex items-center gap-1.5 text-sm font-medium">
                                                <span>Visibility</span>
                                                <InfoPopover
                                                    title="Program vs. Private note visibility"
                                                    content="Authorized program staff notes can be shared to appear on Release Day advising cards. 'Only me' keeps a private working note that is never visible to other staff unless you explicitly create a program staff copy."
                                                />
                                            </legend>
                                            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="note-audience" value="program" bind:group={noteAudience} />Authorized program staff</label>
                                            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="radio" name="note-audience" value="private" bind:group={noteAudience} />Only me</label>
                                        </fieldset>
                                        <p class="text-xs text-muted-foreground">{noteAudience === 'private' ? 'Personal working note. Not available to other staff or consultation.' : 'Staff with current note access to this program and academic year. Not shown to applicants.'}</p>
                                        {#if selectedNoteId}<p class="text-xs text-muted-foreground">Visibility is fixed after saving. A personal note can be shared through a separate reviewed staff copy.</p>{/if}
                                        <div class="flex flex-wrap gap-2">
                                            <Button disabled={busy || (!!pendingCommand && !pendingNoteSaveRetry) || (!pendingNoteSaveRetry && (!capabilities['admission.advising-note.draft.edit'] || !noteText.trim() || (!noteDirty && !!selectedNoteId && !!noteDraft)))} onclick={() => noteAction('save')}>{pendingNoteSaveRetry ? 'Retry save' : selectedNoteId ? 'Save changes' : 'Save note'}</Button>
                                            <Button variant="outline" disabled={busy || (!noteDirty && !noteServerChanged)} onclick={() => { const reconciled = reconcileGovernanceNoteEditor({ latestDraft: latestNoteDraft }); noteDraft = reconciled.draft; noteText = reconciled.text; }}>{noteServerChanged ? 'Discard edits and use latest version' : 'Cancel edits'}</Button>
                                            {#if selectedNoteId && noteAudience === 'private' && capabilities['admission.advising-note.draft.edit']}
                                                <Button variant="outline" disabled={busy || noteDirty || !!pendingCommand} onclick={() => { confirmation = { operation: 'note-copy', revisionId: noteDraft.current_revision_id, content: noteDraft.content }; confirmed = false; confirmationOpen = true; }}>Create program staff copy</Button>
                                            {/if}
                                        </div>
                                    </div>
                                    {#if selectedNoteId && capabilities['admission.advising-note.draft.edit']}
                                        <details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Copy an exact version to another academic year</summary>
                                            <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                                <div><label for="copy-source" class="text-xs">Source version</label><select id="copy-source" class="h-11 w-full rounded-md border bg-background px-3 text-sm" bind:value={copySource}><option value="">Choose version</option>{#each noteHistory.items as revision (revision.id)}<option value={String(revision.id)}>v{revision.version}</option>{/each}</select></div>
                                                <div><label for="copy-cycle" class="text-xs">Target year and semester</label><select id="copy-cycle" class="h-11 w-full rounded-md border bg-background px-3 text-sm" bind:value={copyCycle}><option value="">Choose target</option>{#each governance.cycles ?? [] as item (item.id)}{#if item.id !== cycle.id}<option value={String(item.id)}>{item.staff_label ?? item.name}</option>{/if}{/each}</select></div>
                                                <Button variant="outline" class="self-end" disabled={busy || !!pendingCommand || !copyCycle || !copySource} onclick={() => noteAction('copy')}>Copy as draft</Button>
                                            </div>
                                        </details>
                                    {/if}
                                    {#if noteInspected}<section class="border-t py-3"><h3 class="text-sm font-medium">Version #{noteInspected.id}</h3><p class="whitespace-pre-wrap break-words text-sm">{noteInspected.content}</p><p class="text-xs text-muted-foreground">{noteInspected.author_name} · {noteInspected.reason}</p></section>{/if}
                                    {#if selectedNoteId}
                                        <details id="note-history" tabindex="-1" class="space-y-3 border-t py-3">
                                            <summary class="cursor-pointer text-sm font-medium">Version history</summary>
                                            {#each noteHistory.items as revision (revision.id)}
                                                <div class="flex flex-wrap items-center justify-between gap-2 border-b py-2">
                                                    <button type="button" class="min-h-11 text-left text-sm underline underline-offset-4" onclick={() => { noteInspected = revision; }}>{`v${revision.version} · ${new Date(revision.created_at).toLocaleDateString()}`}</button>
                                                    {#if capabilities['admission.advising-note.draft.edit']}<Button variant="ghost" size="sm" disabled={busy || !!pendingCommand || noteDirty} onclick={() => noteAction('restore', revision)}>Restore version</Button>{/if}
                                                </div>
                                            {/each}
                                            {#if noteHistory.pagination?.last_page > 1}<div class="flex flex-wrap gap-2"><Button variant="outline" size="sm" disabled={busy || noteHistory.pagination.current_page <= 1} onclick={() => refreshNotes(noteHistory.pagination.current_page - 1, 1)}>Previous versions</Button><Button variant="outline" size="sm" disabled={busy || noteHistory.pagination.current_page >= noteHistory.pagination.last_page} onclick={() => refreshNotes(noteHistory.pagination.current_page + 1, 1)}>More versions</Button></div>{/if}
                                            {#each publicationHistory.items as receipt (receipt.id)}<p class="py-1 text-xs text-muted-foreground">{receipt.operation === 'publish' ? 'Shared for consultation' : 'Stopped sharing'} by {receipt.actor_name}{receipt.reason ? ` · ${receipt.reason}` : ''}</p>{/each}
                                            {#if publicationHistory.pagination?.last_page > 1}<div class="flex flex-wrap gap-2"><Button variant="outline" size="sm" disabled={busy || publicationHistory.pagination.current_page <= 1} onclick={() => refreshNotes(1, publicationHistory.pagination.current_page - 1)}>Previous sharing changes</Button><Button variant="outline" size="sm" disabled={busy || publicationHistory.pagination.current_page >= publicationHistory.pagination.last_page} onclick={() => refreshNotes(1, publicationHistory.pagination.current_page + 1)}>More sharing changes</Button></div>{/if}
                                        </details>
                                    {/if}
                                {/if}
                            </TabFmStickyNoteBoard>
                        </Tabs.Content>
                        <Tabs.Content value="discussion" class="space-y-4">
                            <div><h2 class="font-semibold">Programme discussion</h2><label for="discussion-topic" class="mt-3 block text-sm font-medium">Topic</label><select id="discussion-topic" class="mt-1 h-11 w-full max-w-sm rounded-md border bg-background px-3 text-sm" bind:value={discussionTopic}>{#each ['benchmark', 'quota', 'advising'] as topic}{#if capabilities[`admission.governance-discussion.${topic}.read`]}<option value={topic}>{topic === 'benchmark' ? 'Benchmarks' : topic === 'quota' ? 'Quotas' : 'Advising notes'}</option>{/if}{/each}</select></div>
                            {#if capabilities[`admission.governance-discussion.${discussionTopic}.read`]}{#key `${scope}:${discussionTopic}`}<GovernanceDiscussionPanel {program} {cycle} topic={discussionTopic} initialList={governance.discussion?.[discussionTopic]} onGovernanceActivity={refreshGovernanceProjection} />{/key}{:else}<p class="text-sm text-muted-foreground">Discussion access is not available for this topic.</p>{/if}
                        </Tabs.Content>
                        <Tabs.Content value="sets" class="space-y-4">
                            <h2 class="flex items-center gap-1.5 font-semibold">
                                <span>Review, publish, then use</span>
                                <InfoPopover
                                    title="Save → Publish → Activate workflow"
                                    content="Saving updates your working draft only. Publishing freezes an immutable benchmark version across reviewed programs. Activating puts that published set into live use for the selected academic year."
                                />
                            </h2>
                            <p class="text-sm text-muted-foreground">Saving a draft never publishes it. Publishing and putting a set in use are separate reviewed actions.</p>
                            <p class="text-sm">Current: {activeSet ? `Set #${activeSet.id} in use` : 'No set in use'} · {draft?.current_revision_id ? `Draft revision #${draft.current_revision_id}, v${draft.current_version}` : 'No saved draft'}</p>
                            {#if governance.schema_cutover?.programs}
                                <section class="space-y-2 border-t py-3" aria-label="Retained program schema review">
                                    <h3 class="text-sm font-medium">Aptitude and GWA successor review</h3>
                                    {#each governance.schema_cutover.programs as item (item.program_id)}<p class="text-sm">{item.code} · {item.ready ? `Saved revision #${item.revision_id}` : item.cutover_required ? 'Input cutover required' : 'Values need review'}</p>{/each}
                                </section>
                            {/if}
                            {#if capabilities['admission.benchmark.publish']}<Button disabled={busy || !!pendingCommand || dirty || !draft?.current_revision_id || (governance.schema_cutover ? !governance.schema_cutover.ready : !activeSet?.content_sha256 && recovery?.status !== 'draft')} onclick={() => reviewSet('publish')}>{governance.schema_cutover ? 'Review aptitude and GWA successor manifest' : recovery ? 'Review recovered set for publication' : 'Review exact revision for publication'}</Button>{/if}
                            {#if publishedReceipt}<section class="space-y-2 border-t py-3"><h3 class="text-sm font-medium">Published set #{publishedReceipt.id} · v{publishedReceipt.version}</h3><p class="text-sm text-muted-foreground">Not in use until separately activated.</p>{#if capabilities['admission.benchmark.activate']}<Button variant="outline" disabled={busy || !!pendingCommand || activeSet?.id === publishedReceipt.id} onclick={() => reviewSet('activate')}>Review and put this set in use</Button>{/if}</section>{/if}
                            {#if capabilities['admission.benchmark.activate']}<section class="border-t py-3"><h3 class="text-sm font-medium">Published versions</h3>{#each governance.sets ?? [] as set (set.id)}<div class="flex flex-wrap items-center justify-between gap-3 border-b py-3"><p class="text-sm">v{set.version} · {set.name}{activeSet?.id === set.id ? ' · in use' : ''}</p><Button variant="outline" size="sm" disabled={busy || !!pendingCommand || activeSet?.id === set.id} onclick={() => reviewSet('activate', set)}>Review version</Button></div>{:else}<p class="py-2 text-sm text-muted-foreground">No published versions available.</p>{/each}</section>{/if}
                            {#if capabilities['admission.benchmark.draft.edit'] && draft?.current_revision_id && activeSet && String(draft.base_benchmark_set_id) !== String(activeSet.id)}<details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Rebase this draft to the current set</summary><p class="my-2 text-sm text-muted-foreground">Rebase preserves this programme's saved draft cells for review against the current set.</p><label for="rebase-reason" class="text-sm">Reason</label><Textarea id="rebase-reason" bind:value={reason} rows={2} maxlength={500} /><Button class="mt-2" variant="outline" disabled={busy || !!pendingCommand || dirty || !reason.trim()} onclick={rebaseDraft}>Rebase saved draft</Button></details>{/if}
                            <details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-xs"><dt>Active set digest</dt><dd class="break-all">{activeSet?.content_sha256 ?? 'Unavailable'}</dd><dt>Schema digest</dt><dd class="break-all">{activeSet?.schema_sha256 ?? draft?.feature_schema_digest ?? 'Unavailable'}</dd></dl></details>
                            {#if governance.can_import}<details class="border-t py-3"><summary class="cursor-pointer text-sm font-medium">Restricted benchmark import</summary><form class="mt-3 space-y-3" onsubmit={(event) => { event.preventDefault(); importBenchmarks(); }}><label for="import-json" class="text-sm">Benchmark JSON payload</label><Textarea id="import-json" bind:value={importText} required rows={8} /><label for="import-reason" class="text-sm">Import reason</label><Textarea id="import-reason" bind:value={importReason} required maxlength={500} rows={2} /><Button variant="outline" type="submit" disabled={busy || !importText.trim() || !importReason.trim()}>Import published set (not activate)</Button></form></details>{/if}
                        </Tabs.Content>
                        <Tabs.Content value="history" class="space-y-4"><div class="flex items-center justify-between gap-2"><h2 class="font-semibold">Benchmark revision history</h2><Button variant="outline" size="sm" onclick={() => loadBenchmarkHistory(benchmarkHistory.pagination?.current_page ?? 1)}>Refresh</Button></div>{#each benchmarkHistory.items as revision (revision.id)}<div class="space-y-2 border-b pb-3"><button type="button" class="text-sm font-medium underline underline-offset-4" onclick={() => { inspectedRevision = revision; }}>v{revision.version} · revision #{revision.id}</button><p class="text-xs text-muted-foreground">{revision.actor} · {new Date(revision.created_at).toLocaleString()} · {revision.reason}</p>{#if capabilities['admission.benchmark.draft.edit']}<Button variant="outline" size="sm" disabled={busy || dirty || !reason.trim()} onclick={() => restoreBenchmark(revision)}>Restore as new draft revision</Button>{/if}</div>{:else}<p class="text-sm text-muted-foreground">No draft revisions have been saved.</p>{/each}{#if capabilities['admission.benchmark.draft.edit']}<label for="restore-reason" class="text-sm font-medium">Restore reason</label><Textarea id="restore-reason" bind:value={reason} rows={2} maxlength={1000} />{/if}{#if benchmarkHistory.pagination}<div class="flex items-center gap-2"><Button variant="outline" size="sm" disabled={benchmarkHistory.pagination.current_page <= 1} onclick={() => loadBenchmarkHistory(benchmarkHistory.pagination.current_page - 1)}>Previous</Button><span class="text-xs">Page {benchmarkHistory.pagination.current_page} / {benchmarkHistory.pagination.last_page}</span><Button variant="outline" size="sm" disabled={benchmarkHistory.pagination.current_page >= benchmarkHistory.pagination.last_page} onclick={() => loadBenchmarkHistory(benchmarkHistory.pagination.current_page + 1)}>Next</Button></div>{/if}{#if inspectedRevision}<TabFmBenchmarkCalibrator {program} {cycle} {features} content={inspectedRevision.content} activeContent={governance.active_content} version={inspectedRevision.version} />{/if}</Tabs.Content>
                        <Tabs.Content value="version-compare" class="space-y-4">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-1"><label for="comparison-baseline" class="text-sm font-medium">Authorized baseline</label><select id="comparison-baseline" class="h-11 w-full rounded-md border bg-background px-3 text-sm" bind:value={comparisonBaselineId} onchange={() => loadComparison(comparisonBaselineId, comparisonRevisionId)} disabled={comparisonBusy || !(comparisonData?.baselines ?? []).length}><option value="">Choose authorized set</option>{#each comparisonData?.baselines ?? [] as baseline (baseline.id)}<option value={String(baseline.id)}>{baseline.name} · v{baseline.version}{baseline.in_use ? ' · in use' : ' · published, not in use'}</option>{/each}</select></div>
                                <div class="space-y-1"><label for="comparison-revision" class="text-sm font-medium">Exact saved revision</label><select id="comparison-revision" class="h-11 w-full rounded-md border bg-background px-3 text-sm" bind:value={comparisonRevisionId} onchange={() => loadComparison(comparisonBaselineId, comparisonRevisionId)} disabled={comparisonBusy || !benchmarkHistory.items.length}><option value="">Choose saved revision</option>{#each benchmarkHistory.items as revision (revision.id)}<option value={String(revision.id)}>r{revision.id} · v{revision.version} · {revision.reason}</option>{/each}</select></div>
                            </div>
                            <p class="text-xs text-muted-foreground">The server filters both exact versions to your authorized programme context. Compare never changes draft, publication, activation, or case state.</p>
                            {#if comparisonBusy}<p role="status" class="text-sm">Loading authorized comparison…</p>{/if}
                            {#if comparisonData}<TabFmRevisionCompare comparison={comparisonData} workingCopy={dirty ? content : null} />{:else if !comparisonBusy}<p class="rounded-lg border p-4 text-sm text-muted-foreground">Choose a saved revision to load its authorized baseline comparison.</p>{/if}
                        </Tabs.Content>
                        <Tabs.Content value="comparison" class="space-y-3">
                            {#if governance.comparison}
                                <h2 class="font-semibold">{governance.comparison.college.name} · active cutoff comparison</h2>
                                <p class="text-xs text-muted-foreground">Set #{governance.comparison.set_id} · v{governance.comparison.version}. Tier 3 cutoff exemplars only; no applicant outcomes.</p>
                                <div class="overflow-x-auto rounded-lg border">
                                    <table class="w-full text-sm">
                                        <thead><tr><th scope="col" class="min-w-48 px-3 py-3 text-left">Program</th>{#each governance.comparison.schema_features as feature (feature.key)}<th scope="col" class="min-w-32 px-3 py-3 text-left">{feature.label}<span class="block text-xs font-normal text-muted-foreground">{feature.key} · {measurementLabel(feature)}</span></th>{/each}</tr></thead>
                                        <tbody>{#each governance.comparison.rows as item (item.program.id)}<tr class="border-t"><th scope="row" class="px-3 py-3 text-left font-normal"><Link href={item.studio_url} class="underline underline-offset-4">{item.program.code} · {item.program.name}</Link><span class="block text-xs text-muted-foreground">{item.tier_level} · {item.tier_label}</span></th>{#each governance.comparison.schema_features as feature (feature.key)}<td class="px-3 py-3 tabular-nums">{item.values[feature.key] ?? 'Missing'}</td>{/each}</tr>{/each}</tbody>
                                    </table>
                                </div>
                                {#if governance.comparison.truncated}<p class="text-xs text-muted-foreground">Showing the first 50 authorized programs. Use the program selector for another context.</p>{/if}
                            {/if}
                        </Tabs.Content>
                    </Tabs.Root>
                </main>
            </div>
        {/if}
<Dialog.Root bind:open={previewOpen}>
    <Dialog.Content class="fixed inset-x-0 bottom-0 top-auto z-[80] flex max-h-[92dvh] w-full max-w-none translate-x-0 translate-y-0 flex-col overflow-hidden rounded-t-2xl border-border/80 bg-background p-0 shadow-2xl sm:inset-auto sm:top-1/2 sm:left-1/2 sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl sm:max-w-5xl lg:max-w-6xl xl:max-w-7xl sm:h-[82vh] sm:max-h-[760px]" showCloseButton={false}>
        <!-- Pinned Header -->
        <header class="flex shrink-0 items-center justify-between border-b border-border/60 bg-muted/20 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Sparkles class="size-4.5" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <Dialog.Title class="text-base font-bold text-foreground truncate">
                            Admission Decision Support Simulation
                        </Dialog.Title>
                        {#if program?.code}
                            <Badge variant="outline" class="font-mono text-[10px] shrink-0">
                                {program.code}
                            </Badge>
                        {/if}
                    </div>
                    <Dialog.Description class="text-xs text-muted-foreground truncate hidden sm:block">
                        Simulate candidate placement against calibrated program benchmarks. No applicant records are modified.
                    </Dialog.Description>
                </div>
            </div>

            <!-- Mobile View Switcher (visible on < lg) + Close Button -->
            <div class="flex items-center gap-2">
                <div class="grid grid-cols-2 gap-1 rounded-lg border border-border/60 bg-muted/60 p-0.5 text-xs font-semibold lg:hidden" role="tablist">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-1 rounded-md px-2.5 py-1 transition-all {previewMobileTab === 'inputs' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                        onclick={() => { previewMobileTab = 'inputs'; }}
                    >
                        <span>⚙️</span>
                        <span>Inputs</span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-1 rounded-md px-2.5 py-1 transition-all {previewMobileTab === 'results' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                        onclick={() => { previewMobileTab = 'results'; }}
                    >
                        <span>📊</span>
                        <span>Results</span>
                        {#if preview.result}
                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                        {/if}
                    </button>
                </div>

                <Dialog.Close class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer" aria-label="Close dialog">
                    <X class="size-4" />
                </Dialog.Close>
            </div>
        </header>

        <!-- Body / Split Content Canvas -->
        <div class="flex-1 min-h-0 overflow-hidden lg:grid lg:grid-cols-[380px_1fr]">
            <!-- LEFT PANEL: Parameter Inputs -->
            <form
                onsubmit={(event) => { event.preventDefault(); runPreview(); }}
                class="flex flex-col h-full border-r border-border/60 bg-muted/15 {previewMobileTab === 'inputs' ? 'flex' : 'hidden lg:flex'}"
            >
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Source Chooser -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="preview-source" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                Benchmark Source
                            </label>
                            <InfoPopover
                                title="Benchmark Source"
                                content="Simulate against either the currently active institutional benchmark set or your latest saved draft."
                            />
                        </div>
                        <select
                            id="preview-source"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs font-medium"
                            bind:value={source}
                        >
                            <option value="active" disabled={!activeSet}>
                                Active Benchmark (Set #{activeSet?.id ?? '—'})
                            </option>
                            <option value="draft" disabled={!draft?.current_revision_id}>
                                Saved Draft (v{draft?.current_version ?? 0})
                            </option>
                        </select>
                    </div>

                    <!-- Quick Pre-fill Profiles -->
                    <div class="rounded-xl border border-border/70 bg-card p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Quick Profiles</span>
                            <span class="text-[10px] text-muted-foreground">1-Click Fill</span>
                        </div>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button
                                type="button"
                                class="rounded-lg border border-border/70 bg-muted/30 px-2 py-1.5 text-xs font-medium hover:bg-muted hover:text-foreground text-center transition-colors cursor-pointer"
                                onclick={() => fillSampleProfile('3')}
                            >
                                Cutoff (83)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-primary/30 bg-primary/5 text-primary px-2 py-1.5 text-xs font-semibold hover:bg-primary/10 text-center transition-colors cursor-pointer"
                                onclick={() => fillSampleProfile('2')}
                            >
                                Regular (88.5)
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-border/70 bg-muted/30 px-2 py-1.5 text-xs font-medium hover:bg-muted hover:text-foreground text-center transition-colors cursor-pointer"
                                onclick={() => fillSampleProfile('1')}
                            >
                                Honors (92.5)
                            </button>
                        </div>
                    </div>

                    <!-- High School GWA -->
                    <div class="rounded-xl border border-border/70 bg-card p-3 space-y-2">
                        <div class="flex items-center justify-between">
                            <label for="preview-gwa" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                High School GWA
                            </label>
                            <span class="text-[10px] text-muted-foreground font-mono">50.0 – 100.0</span>
                        </div>
                        <Input
                            id="preview-gwa"
                            type="number"
                            min="50"
                            max="100"
                            step="0.1"
                            bind:value={gwa}
                            required
                            class="h-10 text-base font-mono font-bold"
                            placeholder="e.g. 88.5"
                        />
                    </div>

                    <!-- Aptitude Subtests Grid -->
                    <div class="rounded-xl border border-border/70 bg-card p-3 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                Aptitude Subtests
                            </span>
                            <span class="text-[10px] text-muted-foreground font-mono">0 – 100 %ile</span>
                        </div>
                        {#if !aptitudeAreas.length}
                            <p class="text-xs text-muted-foreground">Aptitude catalog unavailable.</p>
                        {:else}
                            <div class="grid grid-cols-2 gap-2">
                                {#each aptitudeAreas as area (area.code)}
                                    <div class="space-y-1">
                                        <label for={`preview-${area.code}`} class="text-[11px] font-semibold text-foreground truncate block" title={area.name}>
                                            {area.name}
                                        </label>
                                        <Input
                                            id={`preview-${area.code}`}
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="1"
                                            bind:value={aptitudeInputs[area.code]}
                                            required
                                            class="h-9 text-sm font-mono font-bold"
                                            placeholder="75"
                                        />
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    {#if previewHasIncompleteRanges}
                        <p role="alert" class="text-xs text-destructive rounded-md border border-destructive/30 bg-destructive/5 p-2.5">
                            This saved draft has a partial range. Complete both ends or clear it before preview.
                        </p>
                    {/if}
                </div>

                <!-- Pinned Action Footer in Left Panel -->
                <div class="p-3 sm:p-4 border-t border-border/60 bg-card/90 backdrop-blur-sm space-y-1.5 shrink-0">
                    <Button
                        type="submit"
                        class="w-full h-10 font-bold shadow-xs gap-2"
                        disabled={!previewComplete || preview.status === 'pending'}
                    >
                        {#if preview.status === 'pending'}
                            <Clock class="size-4 animate-spin" /> Evaluating Simulation…
                        {:else}
                            <Cpu class="size-4" /> Run Placement Simulation
                        {/if}
                    </Button>
                    {#if !previewComplete}
                        <p class="text-[10px] text-center text-muted-foreground">
                            Fill GWA and all 6 aptitude percentiles to simulate.
                        </p>
                    {/if}
                </div>
            </form>

            <!-- RIGHT PANEL: Live Results Canvas -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 bg-background {previewMobileTab === 'results' ? 'block' : 'hidden lg:block'}">
                {#if preview.status === 'pending'}
                    <!-- Animated Loading State -->
                    <div class="flex flex-col items-center justify-center py-16 text-center space-y-4">
                        <div class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary animate-pulse">
                            <Cpu class="size-7 animate-spin" />
                        </div>
                        <div class="space-y-1.5 max-w-sm">
                            <h3 class="text-base font-bold text-foreground">Evaluating Bayesian Prior Kernel</h3>
                            <p class="text-xs text-muted-foreground">
                                 Computing multi-dimensional similarity across program exemplar tiers...
                            </p>
                        </div>
                    </div>
                {:else if preview.status === 'error'}
                    <!-- Error State -->
                    <div class="rounded-xl border border-destructive/40 bg-destructive/5 p-5 text-center space-y-3">
                        <p class="text-sm font-semibold text-destructive">{preview.error}</p>
                        <Button type="button" variant="outline" size="sm" onclick={runPreview}>
                            Retry Simulation
                        </Button>
                    </div>
                {:else if ['ready', 'unavailable'].includes(preview.status) && preview.result}
                    <!-- Results Display -->
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-3">
                            <div class="flex items-center gap-2">
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                <span class="text-xs font-bold uppercase tracking-wider text-foreground">
                                    Simulation Output
                                </span>
                                <Badge variant="outline" class="font-mono text-[10px]">
                                    {source === 'draft' ? `Draft v${draft?.current_version}` : `Active Set #${activeSet?.id}`}
                                </Badge>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 text-xs font-medium text-muted-foreground hover:text-foreground lg:hidden"
                                onclick={() => { previewMobileTab = 'inputs'; }}
                            >
                                ← Edit Inputs
                            </Button>
                        </div>

                        <TabFmResultView model={preview.result} sourceKind={source} />
                    </div>
                {:else}
                    <!-- Idle / Empty State -->
                    <div class="flex flex-col items-center justify-center h-full min-h-[320px] py-12 text-center">
                        <div class="flex size-16 items-center justify-center rounded-2xl border border-border/80 bg-muted/30 text-muted-foreground mb-4">
                            <Cpu class="size-8" />
                        </div>
                        <h3 class="text-base font-bold text-foreground">Live Admission Simulation Canvas</h3>
                        <p class="text-xs text-muted-foreground max-w-sm mt-1 mb-5">
                            Test hypothetical candidate aptitude scores and GWA against program cutoff tiers to observe program rankings, cognitive radar, and curriculum alignment.
                        </p>
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2 font-semibold text-xs text-primary border-primary/30 hover:bg-primary/5 cursor-pointer"
                            onclick={() => { fillSampleProfile('2'); runPreview(); }}
                        >
                            <Sparkles class="size-4" />
                            Load Sample Candidate &amp; Simulate
                        </Button>
                    </div>
                {/if}
            </div>
        </div>
    </Dialog.Content>
</Dialog.Root>
        </div>
        
    </div>
</StaffLayout>

<Dialog.Root bind:open={confirmationOpen}>
    <Dialog.Content class="max-h-[85dvh] overflow-y-auto sm:max-w-2xl">
        <Dialog.Header><Dialog.Title>{confirmation?.operation === 'publish' ? 'Publish exact saved benchmark revision' : confirmation?.operation === 'activate' ? 'Activate exact published benchmark set' : confirmation?.operation === 'note-copy' ? 'Create program staff copy' : confirmation?.operation === 'note-publish' ? 'Share saved version for consultation' : 'Stop sharing for consultation'}</Dialog.Title><Dialog.Description>{program?.code} · {cycle?.staff_label ?? cycle?.name}. {confirmation?.operation === 'note-copy' ? 'Only this reviewed version is copied. Your personal note and its earlier versions remain private.' : 'Review the exact content; this action is separate from saving a draft.'}</Dialog.Description></Dialog.Header>
        {#if confirmation?.operation === 'publish' || confirmation?.operation === 'activate'}
            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-sm"><dt>Revision / set</dt><dd>{confirmation.operation === 'publish' ? `Revision #${confirmation.revisionId} · v${confirmation.version}` : `Set #${confirmation.set.id} · v${confirmation.set.version}`}</dd><dt>Expected activation</dt><dd>{confirmation.activationId ?? 'None'}</dd></dl>
            <details class="rounded-lg border p-3"><summary class="cursor-pointer text-xs font-medium">Technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-xs"><dt>Content digest</dt><dd class="break-all">{confirmation.operation === 'publish' ? confirmation.baseDigest : confirmation.set.content_sha256}</dd>{#if confirmation.operation === 'publish'}<dt>Feature schema digest</dt><dd class="break-all">{draft?.feature_schema_digest ?? 'Unavailable'}</dd>{/if}</dl></details>
            {#if confirmation.schemaCutover}
                <section class="space-y-2 border-t py-3" aria-label="Exact input cutover manifest">
                    <h3 class="text-sm font-medium">Reviewed aptitude and GWA successor manifest</h3>
                    <p class="text-xs text-muted-foreground">Every retained program is pinned below. Publication creates an immutable successor; activation remains separate.</p>
                    {#each confirmation.manifest as [programKey, revisionKey]}<p class="text-xs">Program #{programKey} · revision #{revisionKey}</p>{/each}
                </section>
            {/if}
            <div class="overflow-x-auto"><table class="w-full text-sm"><caption class="py-2 text-left font-medium">Selected-program differences from active content</caption><thead><tr><th class="py-2 text-left" scope="col">Tier / feature</th><th class="text-right" scope="col">Active</th><th class="text-right" scope="col">Reviewed</th></tr></thead><tbody>{#each diffs as diff}<tr class="border-t"><td class="py-2">{diff.level} · {diff.feature.label}</td><td class="text-right">{diff.before ?? 'Missing'}</td><td class="text-right">{diff.after ?? 'Missing'}</td></tr>{:else}<tr><td colspan="3" class="py-3 text-muted-foreground">No selected-program differences.</td></tr>{/each}</tbody></table></div>
            {#if confirmation.operation === 'publish'}
                <section class="space-y-3 rounded-lg border p-3" aria-label="Exact model-value review">
                    <h3 class="text-sm font-semibold">Publication review · exact model values</h3>
                    <div class="overflow-x-auto"><table class="w-full text-xs"><caption class="sr-only">Exact feature values in each model example for this program</caption><thead><tr><th class="py-2 text-left">Score or field</th>{#each [{ key: '1', label: 'Honors' }, { key: '2', label: 'Regular' }, { key: '3', label: 'Cutoff' }, { key: '4', label: 'Other-strand' }, { key: '5', label: 'Below cutoff' }] as tier}<th class="px-2 py-2 text-right">{tier.label}</th>{/each}</tr></thead><tbody>{#each features as feature (feature.key)}<tr class="border-t"><th scope="row" class="py-2 text-left font-normal">{feature.label} · {measurementLabel(feature)}</th>{#each ['1', '2', '3', '4', '5'] as level}<td class="px-2 py-2 text-right tabular-nums">{confirmation.content.tiers?.[level]?.values?.[feature.key] ?? 'Missing'}</td>{/each}</tr>{/each}</tbody></table></div>
                    <details open class="border-t pt-2"><summary class="cursor-pointer text-xs font-medium">Authored ranges and review choices</summary>
                        {#if confirmation.content.authoring}
                            {#each [{ key: 'honors', label: 'Honors' }, { key: 'regular', label: 'Regular' }, { key: 'cutoff', label: 'Cutoff' }] as band}<h4 class="mt-3 text-xs font-semibold">{band.label} ranges</h4><ul class="text-xs">{#each Object.entries(confirmation.content.authoring.ranges?.[band.key] ?? {}) as [key, pair]}<li>{features.find((feature) => feature.key === key)?.label ?? 'Unrecognized feature'}: From {pair[0] ?? 'Missing'} · To {pair[1] ?? 'Missing'}</li>{:else}<li>No ranges recorded.</li>{/each}</ul>{/each}
                            <p class="mt-3 text-xs">Preferred strands: {confirmation.content.authoring.preferred_strands === null ? 'Not recorded' : confirmation.content.authoring.preferred_strands.length ? confirmation.content.authoring.preferred_strands.join(', ') : 'No preference selected'}</p>
                            <p class="text-xs">Career interests: {confirmation.content.authoring.interests === null ? 'Not recorded' : confirmation.content.authoring.interests.length ? confirmation.content.authoring.interests.map((key) => features.find((feature) => feature.key === key)?.label ?? 'Unrecognized interest').join(', ') : 'No preference selected'}</p>
                        {:else}<p class="mt-2 text-xs text-muted-foreground">No ranges or preference choices were authored. Point values remain exact examples.</p>{/if}
                    </details>
                </section>
            {/if}
            {#if confirmation.recovery}
                <section class="space-y-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm" aria-label="Recovered source publication manifest">
                    <h3 class="font-semibold">Exact full recovered revision manifest</h3>
                    <p class="text-xs text-muted-foreground">Source set #{confirmation.recoverySourceSetId} · activation #{confirmation.sourceActivationId ?? 'None'} · frozen source digest <span class="break-all">{confirmation.sourceSnapshotDigest}</span>. These current revision IDs are supplied by governance and are sent unchanged; no sibling cells are inferred.</p>
                    {#if confirmation.manifest.length}
                        <div class="max-h-48 overflow-auto"><table class="w-full text-left text-xs"><caption class="sr-only">Exact revision IDs for every recovered source program</caption><thead><tr><th class="py-1 pr-3">Program ID</th><th>Exact revision ID</th></tr></thead><tbody>{#each confirmation.manifest as [programKey, revisionKey] (programKey)}<tr class="border-t"><td class="py-1 pr-3">{programKey}</td><td>{revisionKey}</td></tr>{/each}</tbody></table></div>
                    {/if}
                    {#if confirmation.incomplete.length}
                        <h4 class="font-medium">Programs still incomplete</h4>
                        <ul class="list-inside list-disc text-xs">{#each confirmation.incomplete as item (item.program_id)}<li>Program #{item.program_id}{item.program_code ? ` · ${item.program_code}` : ''}: {item.missing_cells?.length ? item.missing_cells.map((cell) => `Tier ${cell.tier} ${cell.feature_key}`).join(', ') : 'no missing feature cells'}{item.negative_anchor_missing ? ' · Negative anchor missing' : ''}</li>{/each}</ul>
                    {:else if confirmation.publishBlocked}<p role="alert" class="text-xs text-destructive">The complete exact manifest is unavailable for this authorized scope or does not include the selected current revision. Publication is blocked; no partial manifest will be sent.</p>{/if}
                </section>
            {/if}
            <p class="text-xs text-muted-foreground">Publication preserves other programs. Activation applies the complete published set, not unsaved cells.</p>{#if confirmation.operation === 'publish'}<label for="publish-reason" class="text-sm font-medium">Publication reason</label><Textarea id="publish-reason" bind:value={reason} rows={2} maxlength={500} />{/if}
        {:else if confirmation}<p class="text-sm font-medium">Revision #{confirmation.revisionId}</p><p class="whitespace-pre-wrap break-words text-sm">{confirmation.content}</p>{/if}
        <label class="flex items-start gap-2 text-sm"><input type="checkbox" bind:checked={confirmed} class="mt-1" />I reviewed this exact revision, full recovery manifest, or published set for {cycle?.staff_label ?? cycle?.name}.</label>
        {#if error}<p role="alert" class="text-sm text-destructive">{error}</p>{/if}
        <Dialog.Footer><Button variant="outline" disabled={busy} onclick={() => { confirmationOpen = false; }}>Cancel</Button><Button disabled={busy || !confirmed || (confirmation?.operation === 'publish' && confirmation.publishBlocked)} onclick={async () => {
            if (confirmation.operation.startsWith('note-')) {
                error = '';
                await noteAction(confirmation.operation === 'note-copy' ? 'copy' : confirmation.operation === 'note-publish' ? 'publish' : 'retract', null, confirmation.operation === 'note-copy');
                if (!error) confirmationOpen = false;
            } else await confirmSet();
        }}>{busy ? 'Submitting…' : confirmation?.operation === 'publish' ? confirmation.recovery ? 'Publish complete recovered set' : 'Publish reviewed revision' : confirmation?.operation === 'activate' ? 'Activate reviewed set' : confirmation?.operation === 'note-copy' ? 'Create reviewed staff copy' : confirmation?.operation === 'note-publish' ? 'Share reviewed version for consultation' : 'Stop sharing reviewed version'}</Button></Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
