import { untrack } from 'svelte';
import {
    anonymousWalkthroughScope,
    readWalkthroughProgress,
    saveWalkthroughProgress,
    type WalkthroughDisposition,
    type WalkthroughProgressRecord,
    type WalkthroughScope,
} from '@/lib/walkthrough/preferences';

export type WalkthroughPlaybackMode = 'auto' | 'always' | 'off';
export const WALKTHROUGH_PLAYBACK_STORAGE_PREFIX = 'nexiam:walkthrough:playback:v1:';

export interface WalkthroughTriggerRegistration {
    id: string;
    tourKey: string;
    componentName: string;
    url: string;
    element: HTMLElement | null;
    open: () => void;
}

export interface WalkthroughProgressEnvelope {
    scope: WalkthroughScope;
    records: WalkthroughProgressRecord[];
    persistenceAvailable: boolean;
}

export interface AutomaticWalkthroughSession {
    token: string;
    pageKey: string;
    tourKey: string;
    revision: number;
    scopeKey: string;
    cursor: string | null;
    kind: 'automatic' | 'manual-first';
}

interface CoordinatorOptions {
    storage?: Storage | null;
    writeRemote?: (
        scope: WalkthroughScope,
        record: WalkthroughProgressRecord,
        signal: AbortSignal,
        csrfToken: string | null,
    ) => Promise<void>;
}

let nextDialogId = 0;
let activeDialogId: string | null = null;

export function claimWalkthroughDialog(dialogId: string): boolean {
    if (activeDialogId !== null && activeDialogId !== dialogId) {
        return false;
    }
    activeDialogId = dialogId;
    return true;
}

export function releaseWalkthroughDialog(dialogId: string): void {
    if (activeDialogId === dialogId) {
        activeDialogId = null;
    }
}
export function walkthroughDialogActive(): boolean {
    return activeDialogId !== null;
}

export function createWalkthroughDialogId(): string {
    nextDialogId += 1;
    return `xiam-dialog-${nextDialogId}`;
}
function pagePath(url: string): string {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return url.split(/[?#]/, 1)[0] || '/';
    }
}

function visibleElement(element: HTMLElement | null): boolean {
    if (!element?.isConnected || typeof window === 'undefined') {
        return false;
    }
    const rectangle = element.getBoundingClientRect();
    if (rectangle.width === 0 || rectangle.height === 0) {
        return false;
    }
    const style = window.getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
}

function formHasUnsavedChanges(form: HTMLFormElement): boolean {
    if (form.dataset.walkthroughDirty === 'true') {
        return true;
    }
    return Array.from(form.elements).some((field) => {
        if (field instanceof HTMLInputElement) {
            if (field.disabled || field.type === 'hidden') {
                return false;
            }
            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked !== field.defaultChecked;
            }
            return field.value !== field.defaultValue;
        }
        if (field instanceof HTMLTextAreaElement) {
            return !field.disabled && field.value !== field.defaultValue;
        }
        if (field instanceof HTMLSelectElement) {
            return !field.disabled && Array.from(field.options).some((option) => (
                option.selected !== option.defaultSelected
            ));
        }
        return false;
    });
}

export function walkthroughLaunchBlocked(allowedDialog: HTMLElement | null = null): boolean {
    if (typeof document === 'undefined' || walkthroughDialogActive()) {
        return true;
    }

    const activeElement = document.activeElement;
    if (
        activeElement instanceof HTMLInputElement
        || activeElement instanceof HTMLTextAreaElement
        || activeElement instanceof HTMLSelectElement
        || (activeElement instanceof HTMLElement && activeElement.isContentEditable)
    ) {
        return true;
    }

    for (const element of document.querySelectorAll<HTMLElement>(
        '[role="dialog"][aria-modal="true"], dialog[open], [data-walkthrough-blocker="true"], [data-camera-active="true"]',
    )) {
        if (
            element !== allowedDialog
            && element.dataset.xiamWalkthrough !== 'true'
            && visibleElement(element)
        ) {
            return true;
        }
    }

    for (const video of document.querySelectorAll<HTMLVideoElement>('video')) {
        if (video.srcObject !== null && visibleElement(video)) {
            return true;
        }
    }

    return Array.from(document.forms).some((form) => (
        visibleElement(form) && formHasUnsavedChanges(form)
    ));
}

export class WalkthroughCoordinator {
    private scope: WalkthroughScope = anonymousWalkthroughScope();
    private generation = 0;
    private currentPageKey: string | null = null;
    private currentPageRevision = 1;
    private visit = 0;
    private activeSession: AutomaticWalkthroughSession | null = null;
    private attemptedVisit: string | null = null;
    private persistenceAvailable = false;
    private triggers = new Map<string, WalkthroughTriggerRegistration>();
    private listeners = new Set<() => void>();
    private notificationQueued = false;
    private pendingWrites = new Set<AbortController>();
    private readonly storage: Storage | null;
    private readonly writeRemote: NonNullable<CoordinatorOptions['writeRemote']>;
    private csrfToken: string | null = null;
    private playbackMode: WalkthroughPlaybackMode = 'auto';
    private readonly memoryPlaybackModes = new Map<string, WalkthroughPlaybackMode>();

    constructor(options: CoordinatorOptions = {}) {
        this.storage = options.storage === undefined
            ? this.browserStorage()
            : options.storage;
        this.writeRemote = options.writeRemote ?? postProgress;
        this.refreshPlaybackMode();
    }

    private browserStorage(): Storage | null {
        try {
            return typeof localStorage === 'undefined' ? null : localStorage;
        } catch {
            return null;
        }
    }
    setCsrfToken(token: string | null): void {
        this.csrfToken = token;
    }

    subscribe(listener: () => void): () => void {
        this.listeners.add(listener);
        return () => {
            this.listeners.delete(listener);
        };
    }

    private notify(): void {
        if (this.notificationQueued) return;
        this.notificationQueued = true;
        queueMicrotask(() => {
            this.notificationQueued = false;
            for (const listener of this.listeners) untrack(listener);
        });
    }

    getPlaybackMode(): WalkthroughPlaybackMode {
        return this.playbackMode;
    }

    setPlaybackMode(mode: WalkthroughPlaybackMode): void {
        try {
            this.storage?.setItem(WALKTHROUGH_PLAYBACK_STORAGE_PREFIX + this.scope.key, mode);
            if (this.storage) this.memoryPlaybackModes.delete(this.scope.key);
            else this.memoryPlaybackModes.set(this.scope.key, mode);
        } catch {
            this.memoryPlaybackModes.set(this.scope.key, mode);
            // Keep the explicit choice effective for this session if storage is unavailable.
        }
        this.applyPlaybackMode(mode);
    }

    refreshPlaybackMode(): void {
        let mode = this.memoryPlaybackModes.get(this.scope.key) ?? 'auto';
        try {
            if (this.storage && !this.memoryPlaybackModes.has(this.scope.key)) {
                const stored = this.storage.getItem(WALKTHROUGH_PLAYBACK_STORAGE_PREFIX + this.scope.key);
                mode = stored === 'always' || stored === 'off' ? stored : 'auto';
            }
        } catch {
            // The scoped in-memory preference remains usable.
        }
        this.applyPlaybackMode(mode);
    }

    private applyPlaybackMode(mode: WalkthroughPlaybackMode): void {
        if (mode === this.playbackMode) return;
        this.playbackMode = mode;
        if (this.activeSession?.kind === 'automatic') {
            this.cancelActiveSession();
        }
        this.notify();
    }

    configure(envelope: WalkthroughProgressEnvelope | null): void {
        const nextScope = envelope?.scope ?? anonymousWalkthroughScope();
        if (nextScope.key !== this.scope.key) {
            this.generation += 1;
            this.attemptedVisit = null;
            for (const pendingWrite of this.pendingWrites) {
                pendingWrite.abort();
            }
            this.pendingWrites.clear();
            this.cancelActiveSession();
            this.scope = nextScope;
            this.refreshPlaybackMode();
        }
        this.persistenceAvailable = envelope?.persistenceAvailable === true
            && nextScope.accountId !== null
            && nextScope.surface !== 'anonymous';
        for (const record of envelope?.records ?? []) {
            const merged = saveWalkthroughProgress(this.scope, record, this.storage);
            const session = this.activeSession;
            if (
                session?.pageKey === merged.pageKey
                && session.revision === merged.revision
                && (merged.disposition === 'completed' || merged.disposition === 'skipped')
                && !(session.kind === 'automatic' && this.playbackMode === 'always')
            ) {
                this.cancelActiveSession();
            }
        }
    }

    getScope(): WalkthroughScope {
        return this.scope;
    }

    setCurrentPage(pageKey: string | null, revision = 1): void {
        if (pageKey === this.currentPageKey && revision === this.currentPageRevision) {
            return;
        }
        const identityChanged = pageKey !== this.currentPageKey
            || revision !== this.currentPageRevision;
        this.currentPageKey = pageKey;
        this.currentPageRevision = revision;
        if (identityChanged) {
            this.visit += 1;
            this.attemptedVisit = null;
            if (
                this.activeSession
                && (this.activeSession.pageKey !== pageKey || this.activeSession.revision !== revision)
            ) {
                this.cancelActiveSession();
            }
        }
        this.notify();
    }

    currentRevision(pageKey: string, fallback = 1): number {
        return pageKey === this.currentPageKey ? this.currentPageRevision : fallback;
    }

    registerTrigger(registration: WalkthroughTriggerRegistration): () => void {
        this.triggers.set(registration.id, registration);
        this.notify();
        return () => {
            if (this.triggers.get(registration.id) === registration) {
                this.triggers.delete(registration.id);
                this.notify();
            }
        };
    }

    matchingTrigger(
        tourKey: string,
        componentName: string,
        url: string,
    ): WalkthroughTriggerRegistration | null {
        const path = pagePath(url);
        const matches = [...this.triggers.values()].filter((registration) => (
            registration.tourKey === tourKey
            && registration.componentName === componentName
            && pagePath(registration.url) === path
            && visibleElement(registration.element)
        ));
        return matches.at(-1) ?? null;
    }

    progress(pageKey: string, revision: number): WalkthroughProgressRecord | null {
        return readWalkthroughProgress(this.scope, pageKey, revision, this.storage);
    }

    canAutomaticallyStart(pageKey: string, revision: number): boolean {
        const record = this.progress(pageKey, revision);
        const attemptKey = `${this.scope.key}:${this.visit}:${pageKey}:r${revision}`;
        return this.playbackMode !== 'off'
            && (this.playbackMode === 'always'
                || (record?.disposition !== 'completed' && record?.disposition !== 'skipped'))
            && this.attemptedVisit !== attemptKey
            && activeDialogId === null
            && this.activeSession === null;
    }

    beginAutomatic(
        pageKey: string,
        revision: number,
        tourKey = pageKey,
    ): AutomaticWalkthroughSession | null {
        if (!this.canAutomaticallyStart(pageKey, revision)) {
            return null;
        }
        this.attemptedVisit = `${this.scope.key}:${this.visit}:${pageKey}:r${revision}`;
        return this.beginSession(pageKey, revision, tourKey, 'automatic');
    }

    beginManual(
        pageKey: string,
        revision: number,
        tourKey = pageKey,
    ): AutomaticWalkthroughSession | null {
        const record = this.progress(pageKey, revision);
        if (record?.disposition === 'completed' || record?.disposition === 'skipped') {
            return null;
        }
        if (activeDialogId !== null || this.activeSession !== null) {
            return null;
        }
        this.attemptedVisit = `${this.scope.key}:${this.visit}:${pageKey}:r${revision}`;
        return this.beginSession(pageKey, revision, tourKey, 'manual-first');
    }

    private beginSession(
        pageKey: string,
        revision: number,
        tourKey: string,
        kind: AutomaticWalkthroughSession['kind'],
    ): AutomaticWalkthroughSession {
        const previous = this.progress(pageKey, revision);
        const token = `${this.generation}:${this.visit}:${pageKey}:${revision}:${Date.now()}`;
        this.activeSession = {
            token,
            pageKey,
            tourKey,
            revision,
            scopeKey: this.scope.key,
            cursor: previous?.disposition === 'in_progress' ? previous.cursor : null,
            kind,
        };
        this.save({
            pageKey,
            revision,
            cursor: previous?.cursor ?? null,
            disposition: 'in_progress',
            updatedAt: Date.now(),
        });
        this.notify();
        return this.activeSession;
    }
    activeForTour(tourKey: string): AutomaticWalkthroughSession | null {
        return this.activeSession?.tourKey === tourKey ? this.activeSession : null;
    }

    updateCursor(tourKey: string, cursor: string): void {
        const session = this.activeForTour(tourKey);
        if (!session || session.scopeKey !== this.scope.key) {
            return;
        }
        session.cursor = cursor;
        this.save({
            pageKey: session.pageKey,
            revision: session.revision,
            cursor,
            disposition: 'in_progress',
            updatedAt: Date.now(),
        });
        this.notify();
    }

    interrupt(tourKey: string, cursor: string | null): void {
        const session = this.activeForTour(tourKey);
        if (!session) {
            return;
        }
        if (cursor) {
            this.updateCursor(tourKey, cursor);
        }
        this.activeSession = null;
        this.notify();
    }

    finish(
        tourKey: string,
        disposition: Extract<WalkthroughDisposition, 'completed' | 'skipped'>,
        cursor: string | null,
    ): void {
        const session = this.activeForTour(tourKey);
        if (!session) {
            return;
        }
        this.save({
            pageKey: session.pageKey,
            revision: session.revision,
            cursor,
            disposition,
            updatedAt: Date.now(),
        });
        this.activeSession = null;
        this.notify();
    }


    cancelActiveSession(): void {
        const session = this.activeSession;
        this.activeSession = null;
        if (session && typeof window !== 'undefined') {
            window.dispatchEvent(new CustomEvent('nexiam:walkthrough:cancel', {
                detail: { token: session.token },
            }));
        }
        this.notify();
    }
    refreshTerminalProgress(): void {
        const session = this.activeSession;
        if (!session) {
            return;
        }
        const progress = this.progress(session.pageKey, session.revision);
        if (progress?.disposition === 'completed' || progress?.disposition === 'skipped') {
            if (session.kind === 'automatic' && this.playbackMode === 'always') return;
            this.cancelActiveSession();
        }
    }

    private save(record: WalkthroughProgressRecord): void {
        const merged = saveWalkthroughProgress(this.scope, record, this.storage);
        if (!this.persistenceAvailable) {
            return;
        }
        const controller = new AbortController();
        this.pendingWrites.add(controller);
        void this.writeRemote(this.scope, merged, controller.signal, this.csrfToken)
            .catch(() => undefined)
            .finally(() => this.pendingWrites.delete(controller));
    }
}
async function postProgress(
    scope: WalkthroughScope,
    record: WalkthroughProgressRecord,
    signal: AbortSignal,
    csrfToken: string | null,
): Promise<void> {
    const response = await fetch('/walkthrough/progress', {
        method: 'POST',
        credentials: 'same-origin',
        signal,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify({
            scope_key: scope.key,
            page_key: record.pageKey,
            revision: record.revision,
            cursor: record.cursor,
            disposition: record.disposition,
        }),
    });
    if (!response.ok) {
        throw new Error('Walkthrough progress was not accepted.');
    }
}


export const walkthroughCoordinator = new WalkthroughCoordinator();
