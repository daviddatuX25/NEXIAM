<script lang="ts">
    import ProgramProfileEditor from '@/Components/Communications/ProgramProfileEditor.svelte';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils.js';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    import { router } from '@inertiajs/svelte';

    type ProfilePhoto = {
        id: number | string;
        src: string;
        alt_text: string;
        caption: string;
        available?: boolean;
    };

    type Profile = {
        short_description: string;
        description: string;
        photos: ProfilePhoto[];
        revision?: number;
        is_published?: boolean;
    };

    let {
        program,
        profile: initialProfile,
        revision: initialRevision,
        published_revision: initialPublishedRevision,
        canPublish: initialCanPublish = false,
    } = $props();

    let profile = $state<Profile>(initialProfile);
    let revision = $state<number>(Number(initialRevision ?? 0));
    let publishedRevision = $state<number | null>(initialPublishedRevision ?? null);
    let canPublish = $state<boolean>(Boolean(initialCanPublish));
    let statusMessage = $state('');
    let serverErrors = $state<Record<string, string>>({});
    let isSaving = $state(false);
    let isPublishing = $state(false);
    let pendingCommand: { signature: string; requestId: string } | null = null;
    const pendingPhotoRequests = new Map<string, string>();

    $effect(() => {
        canPublish = Boolean(initialCanPublish);
    });

    const editorUrl = () => `/setup/programs/${Number(program.id)}/public-details`;
    const previewUrl = () => `${editorUrl()}/preview`;
    const uploadUrl = () => `${editorUrl()}/photos`;

    function requestHeaders(): Record<string, string> {
        return {
            ...csrfHeaders(),
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    function normalizeErrors(errors: Record<string, unknown> = {}): Record<string, string> {
        return Object.fromEntries(
            Object.entries(errors).map(([key, value]) => [
                key,
                Array.isArray(value) ? String(value[0] ?? '') : String(value ?? ''),
            ]),
        );
    }

    function commandRequestId(operation: 'save' | 'publish', data: Record<string, unknown>): string {
        const signature = JSON.stringify({ operation, revision, data });
        if (pendingCommand?.signature !== signature) {
            pendingCommand = { signature, requestId: newIdempotencyKey() };
        }
        return pendingCommand.requestId;
    }

    async function submitCommand(operation: 'save' | 'publish'): Promise<void> {
        const photoSnapshot = profile.photos.map((photo) => ({
            id: Number(photo.id),
            alt_text: photo.alt_text,
            caption: photo.caption ?? '',
        }));
        const data = operation === 'save'
            ? {
                expected_revision: revision,
                short_description: profile.short_description,
                description: profile.description,
                photos: photoSnapshot,
            }
            : { expected_revision: revision };
        const payload = {
            ...data,
            request_id: commandRequestId(operation, data),
        };

        serverErrors = {};
        statusMessage = '';
        isSaving = operation === 'save';
        isPublishing = operation === 'publish';

        try {
            const response = await fetch(
                operation === 'save' ? editorUrl() : `${editorUrl()}/publish`,
                {
                    method: operation === 'save' ? 'PUT' : 'POST',
                    credentials: 'same-origin',
                    headers: requestHeaders(),
                    body: JSON.stringify(payload),
                },
            );
            const body = await response.json().catch(() => ({}));
            if (!response.ok) {
                serverErrors = normalizeErrors(body.errors ?? {});
                statusMessage = response.status === 403
                    ? 'Your program-profile authority changed. Refresh this page and contact your administrator if access should remain.'
                    : body.message ?? 'The profile command was not saved. Your current edits are still here.';
                toastError(statusMessage);
                return;
            }

            const saved = body.profile as Profile;
            profile = {
                short_description: saved.short_description,
                description: saved.description,
                photos: saved.photos ?? [],
                revision: Number(saved.revision),
                is_published: Boolean(saved.is_published),
            };
            revision = Number(saved.revision);
            if (operation === 'publish') {
                publishedRevision = revision;
                statusMessage = `Revision ${revision} is now published. Guests see this saved version.`;
            } else {
                statusMessage = `Draft revision ${revision} saved privately. Guests still see the last published version.`;
            }
            toastSuccess(statusMessage);
            pendingCommand = null;
        } catch {
            statusMessage = 'The server could not be reached. Your edits remain here; retrying the same command will reuse its request ID.';
            toastError(statusMessage);
        } finally {
            isSaving = false;
            isPublishing = false;
        }
    }

    function removePhoto(id: string | number): void {
        profile = { ...profile, photos: profile.photos.filter((photo) => String(photo.id) !== String(id)) };
    }

    function fileRetryKey(file: File): string {
        return `${file.name}\u0000${file.size}\u0000${file.lastModified}`;
    }

    async function addPhotos(files: FileList): Promise<{ error?: string }> {
        for (const file of Array.from(files)) {
            const signature = fileRetryKey(file);
            const requestId = pendingPhotoRequests.get(signature) ?? newIdempotencyKey();
            pendingPhotoRequests.set(signature, requestId);
            const form = new FormData();
            form.append('request_id', requestId);
            form.append('photo', file);

            try {
                const response = await fetch(uploadUrl(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        ...csrfHeaders(),
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const errors = normalizeErrors(body.errors ?? {});
                    if (errors.request_id) {
                        pendingPhotoRequests.delete(signature);
                    }
                    const errMsg = errors.photo ?? errors.request_id ?? body.message ?? 'The photo could not be added. Choose it again to retry.';
                    toastError(errMsg);
                    return { error: errMsg };
                }

                profile = { ...profile, photos: [...profile.photos, body.photo as ProfilePhoto] };
                pendingPhotoRequests.delete(signature);
                toastSuccess('Photo staged successfully.');
            } catch {
                const netErr = 'The server could not be reached. The same upload file can be selected again to retry safely.';
                toastError(netErr);
                return { error: netErr };
            }

        }

        return {};
    }

    function openPrivatePreview(): void {
        router.visit(previewUrl(), { preserveScroll: true });
    }
</script>

<ProgramProfileEditor
    program={program}
    bind:profile
    {revision}
    {publishedRevision}
    {canPublish}
    {isSaving}
    {isPublishing}
    bind:serverErrors
    {statusMessage}
    onsave={() => submitCommand('save')}
    onpreview={openPrivatePreview}
    onaddphotos={addPhotos}
    onremovephoto={removePhoto}
    onpublish={() => submitCommand('publish')}
/>
