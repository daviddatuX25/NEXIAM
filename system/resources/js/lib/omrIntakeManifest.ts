export type OmrIntakeMethod = 'auto' | 'manual';
export type OmrManifestState =
    | 'queued'
    | 'checking'
    | 'uploading'
    | 'crop_check'
    | 'reconciling'
    | 'awaiting_target'
    | 'queued_for_analysis'
    | 'accepted'
    | 'blocked'
    | 'failed';

export interface OmrManifestEntry {
    id: string;
    operationId: string;
    idempotencyKey: string;
    fingerprint: string;
    file: File;
    name: string;
    size: number;
    pageNo: number;
    clientCaptureId: string;
    captureMode: OmrIntakeMethod;
    state: OmrManifestState;
    progress: number;
    error: string | null;
}

export interface EnqueueOmrManifestResult {
    entries: OmrManifestEntry[];
    added: OmrManifestEntry[];
    duplicateCount: number;
}

export function omrUploadFingerprint(file: File): string {
    return [file.name, file.size, file.lastModified, file.type].join(':');
}

export function enqueueOmrManifestFiles(
    entries: OmrManifestEntry[],
    files: File[],
    pageCount: number,
    captureMode: OmrIntakeMethod,
    createId: () => string,
): EnqueueOmrManifestResult {
    const existing = new Set(entries.map((entry) => entry.fingerprint));
    const added: OmrManifestEntry[] = [];
    let duplicateCount = 0;
    const pagesPerSheet = Math.max(1, pageCount);
    const lastEntry = entries.at(-1);
    let clientCaptureId = lastEntry?.clientCaptureId ?? '';
    const lastGroupPageCount = clientCaptureId
        ? entries.reduce(
              (highest, entry) =>
                  entry.clientCaptureId === clientCaptureId
                      ? Math.max(highest, entry.pageNo)
                      : highest,
              0,
          )
        : pagesPerSheet;
    let nextPageNo =
        lastGroupPageCount > 0 && lastGroupPageCount < pagesPerSheet
            ? lastGroupPageCount + 1
            : 1;

    for (const file of files) {
        const fingerprint = omrUploadFingerprint(file);
        if (existing.has(fingerprint)) {
            duplicateCount += 1;
            continue;
        }
        existing.add(fingerprint);
        if (nextPageNo === 1) clientCaptureId = createId();
        const id = createId();
        added.push({
            id,
            operationId: createId(),
            idempotencyKey: createId(),
            fingerprint,
            file,
            name: file.name,
            size: file.size,
            pageNo: nextPageNo,
            clientCaptureId,
            captureMode,
            state: 'queued',
            progress: 0,
            error: null,
        });
        nextPageNo = nextPageNo >= pagesPerSheet ? 1 : nextPageNo + 1;
    }

    return {
        entries: [...entries, ...added],
        added,
        duplicateCount,
    };
}

export function updateOmrManifestEntry(
    entries: OmrManifestEntry[],
    id: string,
    patch: Partial<OmrManifestEntry>,
): OmrManifestEntry[] {
    return entries.map((entry) =>
        entry.id === id ? { ...entry, ...patch } : entry,
    );
}
