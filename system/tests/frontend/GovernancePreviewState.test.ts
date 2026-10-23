import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    applyGovernanceNoteDetail,
    beginPreview,
    classifyPreviewResponse,
    GovernanceRequestError,
    governanceRequest,
    invalidatePreview,
    isUncertainCommandOutcome,
    receivePreview,
    reconcileGovernanceNoteEditor,
    reconcileStudioGovernance,
    reusePendingGovernancePayload,
    type PreviewState,
} from '../../resources/js/lib/governanceRequests';

type Evaluation = { index: number; backend: string };
const idle: PreviewState<Evaluation> = { status: 'idle', current: null, result: null, error: '' };
const first = { requestId: 'request-one', fingerprint: 'program-1:cycle-1:draft-4:input-a' };
const second = { requestId: 'request-two', fingerprint: 'program-1:cycle-1:draft-4:input-b' };
const evaluation = { index: 71, backend: 'in_process_heuristics' };

describe('governance preview result ownership', () => {
    it('accepts only the response that echoes the running request and current input', () => {
        const pending = beginPreview(idle, first);
        const completed = receivePreview(pending, first, first.fingerprint, first.requestId, evaluation, true);
        expect(completed.status).toBe('ready');
        expect(completed.result).toEqual(evaluation);
    });

    it('does not let an older network response replace a newer evaluation', () => {
        const pending = beginPreview(beginPreview(idle, first), second);
        const late = receivePreview(pending, first, second.fingerprint, first.requestId, { index: 99, backend: 'late' }, true);
        expect(late).toBe(pending);
        const current = receivePreview(late, second, second.fingerprint, second.requestId, evaluation, true);
        expect(current.result).toEqual(evaluation);
    });

    it('invalidates the request when input changes even before a replacement request starts', () => {
        const pending = beginPreview(idle, first);
        const invalidated = invalidatePreview(pending);
        const late = receivePreview(invalidated, first, second.fingerprint, first.requestId, evaluation, true);
        expect(late).toBe(invalidated);
        expect(late.result).toBeNull();
        expect(late.status).toBe('idle');
    });

    it('keeps a previous result stale after cancellation or editing instead of presenting it as current', () => {
        const completed = receivePreview(beginPreview(idle, first), first, first.fingerprint, first.requestId, evaluation, true);
        const invalidated = invalidatePreview(completed);
        expect(invalidated.status).toBe('stale');
        expect(invalidated.result).toEqual(evaluation);
        expect(invalidated.current).toBeNull();
    });

    it('rejects a missing or wrong server request identity even for the same input', () => {
        const pending = beginPreview(idle, first);
        expect(receivePreview(pending, first, first.fingerprint, null, evaluation, true)).toBe(pending);
        expect(receivePreview(pending, first, first.fingerprint, second.requestId, evaluation, true)).toBe(pending);
    });

    it('marks an actual standby evaluation unavailable rather than inventing a local score', () => {
        const standby = receivePreview(beginPreview(idle, first), first, first.fingerprint, first.requestId, evaluation, false);
        expect(standby.status).toBe('unavailable');
        expect(standby.result).toBe(evaluation);
    });
});

describe('governance recovery and hydration', () => {
    afterEach(() => vi.unstubAllGlobals());


    it('hydrates a clean note from the latest draft but retains dirty text and its original version base', () => {
        const originalDraft = { note_id: 4, version: 1, current_revision_id: 12, content: 'Original server text' };
        const latestDraft = { note_id: 4, version: 2, current_revision_id: 18, content: 'Collaborator update' };
        const response = { note: { note_id: 4, draft: latestDraft, published: null, history: { items: [latestDraft] } } };
        const state = { selectedNote: { note_id: 4 }, selectedNoteId: 4, draft: originalDraft, latestDraft: originalDraft, text: 'Original server text' };

        const clean = applyGovernanceNoteDetail(state, response);
        expect(clean.draft).toEqual(latestDraft);
        expect(clean.text).toBe('Collaborator update');

        const dirty = applyGovernanceNoteDetail({ ...state, text: 'My unsaved edit' }, response);
        expect(dirty.draft).toBe(originalDraft);
        expect(dirty.text).toBe('My unsaved edit');
        expect(dirty.latestDraft).toEqual(latestDraft);
        expect(reconcileGovernanceNoteEditor(dirty)).toEqual({ draft: latestDraft, text: 'Collaborator update' });
    });

    it('refreshes active source facts and note boards without replacing a dirty benchmark base', () => {
        const localContent = { tiers: { '3': { values: { gwa: 85 } } } };
        const savedContent = { tiers: { '3': { values: { gwa: 80 } } } };
        const originalDraft = { id: 9, current_version: 2, base_benchmark_set_id: 5, content: savedContent };
        const incomingDraft = { id: 9, current_version: 3, base_benchmark_set_id: 6, content: { tiers: { '3': { values: { gwa: 82 } } } } };
        const notes = { items: [{ note_id: 12 }], counts: { total: 1 } };
        const incoming = { active_set: { id: 6, activation_id: 14 }, draft: incomingDraft, active_content: null, recovery_content: null, recovery: null, notes, benchmark_revision_history: [] };
        const current = { activeSet: { id: 5, activation_id: 10 }, draft: originalDraft, content: localContent, savedContent, recovery: null, recoveryContent: null, noteBoard: null, benchmarkHistory: { items: [], pagination: null }, dirty: true, busy: false, pendingCommand: null };

        const dirtyRefresh = reconcileStudioGovernance(current, incoming);
        expect(dirtyRefresh.activeSet).toEqual({ id: 6, activation_id: 14 });
        expect(dirtyRefresh.draft).toBe(originalDraft);
        expect(dirtyRefresh.content).toBe(localContent);
        expect(dirtyRefresh.savedContent).toBe(savedContent);
        expect(dirtyRefresh.noteBoard).toBe(notes);

        const cleanRefresh = reconcileStudioGovernance({ ...current, dirty: false }, incoming);
        expect(cleanRefresh.draft).toBe(incomingDraft);
        expect(cleanRefresh.content).toBe(incomingDraft.content);
        expect(cleanRefresh.savedContent).toBe(incomingDraft.content);
    });

    it('reuses an uncertain note write payload even after the displayed draft changes', () => {
        const originalPayload = { note_id: 4, expected_version: 1, content: 'Retry these exact bytes', reason: 'Review' };
        const pending = { action: 'advising-note.save', payload: originalPayload };
        const refreshedPayload = { note_id: 4, expected_version: 2, content: 'Different text', reason: 'Changed reason' };

        const retry = reusePendingGovernancePayload('advising-note.save', refreshedPayload, pending);
        expect(retry).toBe(originalPayload);
        expect(retry).toEqual(originalPayload);
    });

    it('treats unreadable HTTP-success bodies as unknown outcomes and keeps definitive errors distinct', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('not-json', { status: 200 })));
        const failure = await governanceRequest('/governance/write', { reason: 'Review' }).catch((error: unknown) => error);

        expect(failure).toBeInstanceOf(GovernanceRequestError);
        expect(failure).toMatchObject({ status: 200, outcomeUnknown: true });
        expect(isUncertainCommandOutcome(failure)).toBe(true);
        expect(isUncertainCommandOutcome(new GovernanceRequestError('Validation failed', 422))).toBe(false);
    });

    it('accepts a correctly scoped active standby reason without activation IDs but rejects mismatched requests and evaluated identities', () => {
        const expected = { requestId: 'request-one', source: 'active' as const, programId: 3, cycleId: 8, activationId: 14, setId: 6 };
        const standby = {
            status: 'standby',
            message: 'The current historical benchmark requires explicit recovery and review before preview.',
            evaluation_context: { source: 'active', request_id: 'request-one', program_id: 3, academic_cycle_id: 8, benchmark_activation_id: null, benchmark_set_id: null },
        };

        expect(classifyPreviewResponse(standby, expected)).toBe('standby');
        expect(classifyPreviewResponse({ ...standby, evaluation_context: { ...standby.evaluation_context, request_id: 'older-request' } }, expected)).toBe('mismatch');
        expect(classifyPreviewResponse({ ...standby, evaluation_context: { ...standby.evaluation_context, program_id: 9 } }, expected)).toBe('mismatch');

        const evaluated = {
            status: 'evaluated',
            recommendations: [],
            evaluation_context: { ...standby.evaluation_context, benchmark_activation_id: 14, benchmark_set_id: 6 },
        };
        expect(classifyPreviewResponse(evaluated, expected)).toBe('evaluated');
        expect(classifyPreviewResponse({ ...evaluated, evaluation_context: { ...evaluated.evaluation_context, benchmark_activation_id: 13 } }, expected)).toBe('mismatch');
    });

    it('requires exact revision, draft, and base identity for evaluated draft previews', () => {
        const expected = { requestId: 'request-two', source: 'draft' as const, programId: 3, cycleId: 8, revisionId: 18, draftId: 9, baseSetId: 6 };
        const evaluation = {
            status: 'evaluated',
            recommendations: [],
            evaluation_context: { source: 'draft', request_id: 'request-two', program_id: 3, academic_cycle_id: 8, benchmark_revision_id: 18, benchmark_draft_id: 9, benchmark_set_id: 6 },
        };

        expect(classifyPreviewResponse(evaluation, expected)).toBe('evaluated');
        expect(classifyPreviewResponse({ ...evaluation, evaluation_context: { ...evaluation.evaluation_context, benchmark_draft_id: 10 } }, expected)).toBe('mismatch');
    });
});
