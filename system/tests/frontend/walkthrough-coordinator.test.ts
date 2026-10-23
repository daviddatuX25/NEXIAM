import { describe, expect, it } from 'vitest';
import {
    WalkthroughCoordinator,
    type WalkthroughProgressEnvelope,
} from '../../resources/js/lib/walkthrough/coordinator';
import {
    anonymousWalkthroughScope,
    readWalkthroughProgress,
    saveWalkthroughProgress,
    type WalkthroughProgressRecord,
    type WalkthroughScope,
} from '../../resources/js/lib/walkthrough/preferences';

class MemoryStorage implements Storage {
    private readonly values = new Map<string, string>();
    throwOnWrite = false;

    get length(): number {
        return this.values.size;
    }

    clear(): void {
        this.values.clear();
    }

    getItem(key: string): string | null {
        return this.values.get(key) ?? null;
    }

    key(index: number): string | null {
        return [...this.values.keys()][index] ?? null;
    }

    removeItem(key: string): void {
        this.values.delete(key);
    }

    setItem(key: string, value: string): void {
        if (this.throwOnWrite) {
            throw new Error('storage unavailable');
        }
        this.values.set(key, value);
    }
}

function createScope(
    accountId: string | null,
    institutionId: string | null,
    workspaceId: string | null,
    surface: WalkthroughScope['surface'],
    key: string,
): WalkthroughScope {
    return { accountId, institutionId, workspaceId, surface, key };
}

function createEnvelope(scope: WalkthroughScope, persistenceAvailable = true): WalkthroughProgressEnvelope {
    return { scope, records: [], persistenceAvailable };
}

const applicantScope = createScope('account-a', 'institution-a', null, 'applicant', 'scope-a');

describe('walkthrough coordinator', () => {
    it('remembers a cursor and resumes the same semantic page on a later visit', () => {
        const storage = new MemoryStorage();
        const coordinator = new WalkthroughCoordinator({
            storage,
            writeRemote: async () => undefined,
        });
        coordinator.configure(createEnvelope(applicantScope));
        coordinator.setCurrentPage('applicant-release-day', 2);

        const started = coordinator.beginAutomatic('applicant-release-day', 2);
        expect(started?.cursor).toBeNull();
        coordinator.updateCursor('applicant-release-day', 'current-instructions');
        coordinator.interrupt('applicant-release-day', 'current-instructions');
        expect(coordinator.progress('applicant-release-day', 2)).toMatchObject({
            cursor: 'current-instructions',
            disposition: 'in_progress',
        });

        coordinator.setCurrentPage('applicant-results', 1);
        coordinator.setCurrentPage('applicant-release-day', 2);
        const resumed = coordinator.beginAutomatic('applicant-release-day', 2);

        expect(resumed?.cursor).toBe('current-instructions');
        expect(resumed?.kind).toBe('automatic');
    });

    it('keeps completion and skip terminal across stale cursor updates and manual replay', () => {
        const storage = new MemoryStorage();
        const coordinator = new WalkthroughCoordinator({ storage, writeRemote: async () => undefined });
        coordinator.configure(createEnvelope(applicantScope));
        coordinator.setCurrentPage('applicant-portal', 4);
        coordinator.beginAutomatic('applicant-portal', 4);
        coordinator.finish('applicant-portal', 'completed', 'guidance');

        saveWalkthroughProgress(applicantScope, {
            pageKey: 'applicant-portal',
            revision: 4,
            cursor: 'welcome',
            disposition: 'in_progress',
            updatedAt: Date.now() + 1,
        }, storage);
        expect(coordinator.beginManual('applicant-portal', 4)).toBeNull();
        expect(readWalkthroughProgress(applicantScope, 'applicant-portal', 4, storage)).toMatchObject({
            cursor: 'guidance',
            disposition: 'completed',
        });

        const skippedScope = createScope('account-b', 'institution-a', null, 'applicant', 'scope-b');
        coordinator.configure(createEnvelope(skippedScope));
        coordinator.setCurrentPage('applicant-program-choice', 3);
        coordinator.beginAutomatic('applicant-program-choice', 3);
        coordinator.finish('applicant-program-choice', 'skipped', 'advising-workflow');
        expect(coordinator.beginManual('applicant-program-choice', 3)).toBeNull();
        expect(coordinator.progress('applicant-program-choice', 3)?.disposition).toBe('skipped');
    });

    it('isolates account, institution, workspace, surface, and anonymous browser progress', () => {
        const storage = new MemoryStorage();
        const original: WalkthroughProgressRecord = {
            pageKey: 'staff-operations-dashboard',
            revision: 3,
            cursor: 'workspace-overview',
            disposition: 'completed',
            updatedAt: Date.now(),
        };
        const staffScope = createScope('account-a', 'institution-a', null, 'staff', 'staff-a');
        saveWalkthroughProgress(staffScope, original, storage);

        expect(readWalkthroughProgress(staffScope, original.pageKey, original.revision, storage)?.disposition)
            .toBe('completed');
        expect(readWalkthroughProgress(applicantScope, original.pageKey, original.revision, storage))
            .toBeNull();
        expect(readWalkthroughProgress(
            createScope('account-a', 'institution-b', null, 'staff', 'staff-b'),
            original.pageKey,
            original.revision,
            storage,
        )).toBeNull();
        expect(readWalkthroughProgress(
            createScope('account-a', 'institution-a', 'practice-1', 'staff', 'staff-practice'),
            original.pageKey,
            original.revision,
            storage,
        )).toBeNull();
        expect(readWalkthroughProgress(anonymousWalkthroughScope(), original.pageKey, original.revision, storage))
            .toBeNull();
    });

    it('keeps an embedded QR tour separate from the applicant Home page identity', () => {
        const coordinator = new WalkthroughCoordinator({
            storage: new MemoryStorage(),
            writeRemote: async () => undefined,
        });
        coordinator.configure(createEnvelope(applicantScope));
        coordinator.setCurrentPage('page:Applicant/Portal', 4);

        const started = coordinator.beginManual('tour:applicant-qr', 3, 'applicant-qr');
        expect(started?.pageKey).toBe('tour:applicant-qr');
        coordinator.finish('applicant-qr', 'completed', 'guidance');

        expect(coordinator.progress('tour:applicant-qr', 3)?.disposition).toBe('completed');
        expect(coordinator.progress('page:Applicant/Portal', 4)).toBeNull();
    });

    it('suppresses repeat automatic launches when storage and remote persistence fail', () => {
        const storage = new MemoryStorage();
        storage.throwOnWrite = true;
        const coordinator = new WalkthroughCoordinator({
            storage,
            writeRemote: async () => {
                throw new Error('network unavailable');
            },
        });
        coordinator.configure(createEnvelope(applicantScope));
        coordinator.setCurrentPage('applicant-admission-clearance', 1);

        expect(coordinator.beginAutomatic('applicant-admission-clearance', 1)).not.toBeNull();
        coordinator.interrupt('applicant-admission-clearance', 'documents');
        expect(coordinator.canAutomaticallyStart('applicant-admission-clearance', 1)).toBe(false);
        expect(coordinator.progress('applicant-admission-clearance', 1)?.cursor).toBe('documents');
    });

    it('aborts pending writes and cancels playback when the signed-in scope changes', () => {
        const storage = new MemoryStorage();
        const requests: AbortSignal[] = [];
        const coordinator = new WalkthroughCoordinator({
            storage,
            writeRemote: async (_scope, _record, signal) => {
                requests.push(signal);
                await new Promise<void>((resolve) => {
                    if (signal.aborted) {
                        resolve();
                    } else {
                        signal.addEventListener('abort', () => resolve(), { once: true });
                    }
                });
            },
        });
        coordinator.configure(createEnvelope(applicantScope));
        coordinator.setCurrentPage('applicant-release-day', 2);
        coordinator.beginAutomatic('applicant-release-day', 2);
        coordinator.updateCursor('applicant-release-day', 'current-instructions');

        const nextAccountScope = createScope('account-b', 'institution-a', null, 'applicant', 'scope-next');
        coordinator.configure(createEnvelope(nextAccountScope));

        expect(requests.length).toBeGreaterThan(0);
        expect(requests.every((signal) => signal.aborted)).toBe(true);
        expect(coordinator.activeForTour('applicant-release-day')).toBeNull();
        expect(coordinator.progress('applicant-release-day', 2)).toBeNull();
    });

    it('keeps first-use records separate when different page families share one tour definition', () => {
        const coordinator = new WalkthroughCoordinator({
            storage: new MemoryStorage(),
            writeRemote: async () => undefined,
        });
        coordinator.configure(createEnvelope(applicantScope));

        coordinator.setCurrentPage('page:Examination/MonitoringIndex', 2);
        coordinator.beginAutomatic('page:Examination/MonitoringIndex', 2, 'exam-monitoring');
        coordinator.finish('exam-monitoring', 'completed', 'guidance');

        coordinator.setCurrentPage('page:Examination/Monitoring', 2);
        const secondPage = coordinator.beginAutomatic(
            'page:Examination/Monitoring',
            2,
            'exam-monitoring',
        );

        expect(secondPage?.tourKey).toBe('exam-monitoring');
        expect(coordinator.progress('page:Examination/MonitoringIndex', 2)?.disposition)
            .toBe('completed');
        expect(coordinator.progress('page:Examination/Monitoring', 2)?.disposition)
            .toBe('in_progress');
    });

    it('does not send anonymous or unavailable progress writes to the server', () => {
        const calls: number[] = [];
        const coordinator = new WalkthroughCoordinator({
            storage: new MemoryStorage(),
            writeRemote: async () => {
                calls.push(1);
            },
        });
        coordinator.configure(createEnvelope(anonymousWalkthroughScope(), false));
        coordinator.setCurrentPage('page:Applicant/Results', 1);
        coordinator.beginAutomatic('page:Applicant/Results', 1);
        expect(calls).toHaveLength(0);

        coordinator.configure(createEnvelope(applicantScope, false));
        coordinator.setCurrentPage('page:Applicant/AdmissionClearance', 1);
        coordinator.beginAutomatic('page:Applicant/AdmissionClearance', 1);
        expect(calls).toHaveLength(0);
    });

    it('blocks queued automatic starts when Off while allowing deliberate manual guidance', () => {
        const coordinator = new WalkthroughCoordinator({ storage: new MemoryStorage() });
        coordinator.configure(createEnvelope(applicantScope, false));
        coordinator.setCurrentPage('page:Applicant/Results', 1);
        expect(coordinator.canAutomaticallyStart('page:Applicant/Results', 1)).toBe(true);

        coordinator.setPlaybackMode('off');
        expect(coordinator.beginAutomatic('page:Applicant/Results', 1)).toBeNull();
        expect(coordinator.progress('page:Applicant/Results', 1)).toBeNull();
        expect(coordinator.beginManual('page:Applicant/Results', 1)?.kind).toBe('manual-first');
        coordinator.setPlaybackMode('auto');
        expect(coordinator.activeForTour('page:Applicant/Results')?.kind).toBe('manual-first');
    });

    it('interrupts automatic playback without changing its remembered cursor to skipped', () => {
        const coordinator = new WalkthroughCoordinator({ storage: new MemoryStorage() });
        coordinator.configure(createEnvelope(applicantScope, false));
        coordinator.setCurrentPage('page:Applicant/Results', 1);
        coordinator.beginAutomatic('page:Applicant/Results', 1);
        coordinator.updateCursor('page:Applicant/Results', 'result-details');
        coordinator.setPlaybackMode('off');

        expect(coordinator.activeForTour('page:Applicant/Results')).toBeNull();
        expect(coordinator.progress('page:Applicant/Results', 1)).toMatchObject({
            cursor: 'result-details', disposition: 'in_progress',
        });
    });

    it.each(['completed', 'skipped'] as const)(
        'replays %s pages once per visit in Always without erasing terminal progress',
        (disposition) => {
            const storage = new MemoryStorage();
            const coordinator = new WalkthroughCoordinator({ storage });
            const record: WalkthroughProgressRecord = {
                pageKey: `page:playback-${disposition}`, revision: 1, cursor: 'last-step',
                disposition, updatedAt: Date.now(),
            };
            coordinator.configure({ ...createEnvelope(applicantScope, false), records: [record] });
            coordinator.setCurrentPage(record.pageKey, 1);
            coordinator.setPlaybackMode('always');
            expect(coordinator.beginAutomatic(record.pageKey, 1)?.cursor).toBeNull();
            coordinator.configure({ ...createEnvelope(applicantScope, false), records: [record] });
            coordinator.refreshTerminalProgress();
            expect(coordinator.activeForTour(record.pageKey)?.kind).toBe('automatic');
            coordinator.finish(record.pageKey, 'skipped', 'first-step');
            expect(coordinator.progress(record.pageKey, 1)?.disposition).toBe(disposition);
            expect(coordinator.beginAutomatic(record.pageKey, 1)).toBeNull();

            coordinator.setCurrentPage('another-page', 1);
            coordinator.setCurrentPage(record.pageKey, 1);
            expect(coordinator.beginAutomatic(record.pageKey, 1)?.cursor).toBeNull();
            coordinator.setPlaybackMode('auto');
            expect(coordinator.activeForTour(record.pageKey)).toBeNull();
            expect(coordinator.beginAutomatic(record.pageKey, 1)).toBeNull();
            expect(coordinator.progress(record.pageKey, 1)?.disposition).toBe(disposition);
        },
    );

    it('persists playback mode separately for each account/workspace and reads other-tab changes', () => {
        const storage = new MemoryStorage();
        const coordinator = new WalkthroughCoordinator({ storage });
        coordinator.configure(createEnvelope(applicantScope, false));
        coordinator.setPlaybackMode('off');
        const anotherTab = new WalkthroughCoordinator({ storage });
        anotherTab.configure(createEnvelope(applicantScope, false));
        expect(anotherTab.getPlaybackMode()).toBe('off');
        anotherTab.setPlaybackMode('always');
        coordinator.refreshPlaybackMode();
        expect(coordinator.getPlaybackMode()).toBe('always');

        const otherScope = createScope('account-b', 'institution-a', 'workspace-b', 'staff', 'scope-other');
        coordinator.configure(createEnvelope(otherScope, false));
        expect(coordinator.getPlaybackMode()).toBe('auto');
        coordinator.setPlaybackMode('off');
        coordinator.configure(createEnvelope(applicantScope, false));
        expect(coordinator.getPlaybackMode()).toBe('always');
        storage.clear();
        coordinator.refreshPlaybackMode();
        expect(coordinator.getPlaybackMode()).toBe('auto');
    });

    it('retains scoped choices in memory when browser storage cannot be written', () => {
        const storage = new MemoryStorage();
        storage.throwOnWrite = true;
        const coordinator = new WalkthroughCoordinator({ storage });
        coordinator.configure(createEnvelope(applicantScope, false));
        coordinator.setPlaybackMode('off');
        coordinator.refreshPlaybackMode();
        expect(coordinator.getPlaybackMode()).toBe('off');
        coordinator.configure(null);
        expect(coordinator.getPlaybackMode()).toBe('auto');
        coordinator.configure(createEnvelope(applicantScope, false));
        expect(coordinator.getPlaybackMode()).toBe('off');
    });
});
