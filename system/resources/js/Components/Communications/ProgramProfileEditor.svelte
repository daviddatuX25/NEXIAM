<script lang="ts">
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Textarea } from '@/Components/ui/textarea';
    import { ArrowDown, ArrowUp, ImagePlus, X } from 'lucide-svelte';
    type ProfilePhoto = {
        id: string | number;
        src: string;
        alt_text: string;
        caption: string;
        available?: boolean;
    };

    type Profile = {
        short_description: string;
        description: string;
        photos: ProfilePhoto[];
    };

    let {
        program,
        profile = $bindable(),
        onsave,
        onpreview,
        onaddphotos,
        onremovephoto,
        onpublish,
        revision,
        publishedRevision,
        canPublish,
        isSaving = false,
        isPublishing = false,
        serverErrors = $bindable({}),
        statusMessage = '',
    }: {
        program: { id?: number; code: string; name: string };
        profile: Profile;
        onsave: () => void;
        onpreview: () => void;
        onaddphotos: (files: FileList) => Promise<{ error?: string }>;
        onremovephoto: (id: string | number) => void;
        onpublish: () => void;
        revision: number;
        publishedRevision: number | null;
        canPublish: boolean;
        isSaving?: boolean;
        isPublishing?: boolean;
        serverErrors?: Record<string, string>;
        statusMessage?: string;
    } = $props();

    const maxPhotos = 6;
    const maxFileBytes = 5 * 1024 * 1024;
    const allowedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Edit public program profile' },
    ];

    let pendingAdding = $state(false);
    let addError = $state('');
    let actionAttempted = $state(false);
    let baselineRevision = $state(Number(revision));
    let baselineProfile = $state(JSON.stringify(profile));

    $effect(() => {
        if (Number(revision) !== baselineRevision) {
            baselineRevision = Number(revision);
            baselineProfile = JSON.stringify(profile);
            actionAttempted = false;
        }
    });

    const isDirty = $derived(
        JSON.stringify(profile) !== baselineProfile,
    );
    const shortDescriptionLength = $derived(
        String(profile.short_description ?? '').length,
    );
    const descriptionLength = $derived(
        String(profile.description ?? '').length,
    );
    const fieldErrors = $derived.by(() => {
        const errors: {
            short_description?: string;
            description?: string;
            photos: Record<string, { alt?: string; caption?: string; media?: string }>;
        } = {
            photos: {},
        };

        if (shortDescriptionLength > 240) {
            errors.short_description =
                'Keep the short description within 240 characters.';
        } else if (serverErrors.short_description) {
            errors.short_description = serverErrors.short_description;
        }
        if (!String(profile.description ?? '').trim()) {
            errors.description =
                'Add a full program description before saving or previewing.';
        } else if (descriptionLength > 10000) {
            errors.description =
                'Keep the full description within 10,000 characters.';
        } else if (serverErrors.description) {
            errors.description = serverErrors.description;
        }
        for (const [index, photo] of (profile.photos ?? []).entries()) {
            const photoErrors: { alt?: string; caption?: string; media?: string } = {};
            if (!String(photo.alt_text ?? '').trim()) {
                photoErrors.alt = 'Add meaningful alternative text for this photo.';
            } else if (String(photo.alt_text).length > 160) {
                photoErrors.alt = 'Alternative text must be 160 characters or fewer.';
            } else if (serverErrors[`photos.${index}.alt_text`]) {
                photoErrors.alt = serverErrors[`photos.${index}.alt_text`];
            }
            if (String(photo.caption ?? '').length > 240) {
                photoErrors.caption = 'Captions must be 240 characters or fewer.';
            } else if (serverErrors[`photos.${index}.caption`]) {
                photoErrors.caption = serverErrors[`photos.${index}.caption`];
            }
            if (photo.available === false) {
                photoErrors.media = 'This private photo is unavailable. Remove or replace it before saving.';
            } else if (serverErrors[`photos.${index}.id`]) {
                photoErrors.media = serverErrors[`photos.${index}.id`];
            }
            if (Object.keys(photoErrors).length > 0) {
                errors.photos[photo.id] = photoErrors;
            }
        }
        if (serverErrors.photos) {
            errors.photos._general = { media: serverErrors.photos };
        }
        return errors;
    });
    const hasValidationErrors = $derived(
        Boolean(
            fieldErrors.short_description ||
            fieldErrors.description ||
            Object.keys(fieldErrors.photos).length > 0 ||
            Object.keys(serverErrors).length > 0 ||
            (profile.photos ?? []).length > maxPhotos,
        ),
    );
    const canUseDraft = $derived(
        !pendingAdding && !isSaving && !isPublishing && !hasValidationErrors,
    );
    const canPreviewSaved = $derived(canUseDraft && !isDirty);
    const previewPhoto = $derived(profile.photos?.[0] ?? null);
    const commandPending = $derived(isSaving || isPublishing);

    function updateProfile<K extends keyof Profile>(
        key: K,
        value: Profile[K],
    ): void {
        if (commandPending) return;
        serverErrors = {};
        profile = { ...profile, [key]: value };
    }

    function clearServerErrors(): void {
        serverErrors = {};
    }

    function updatePhoto(
        id: string | number,
        key: 'alt_text' | 'caption',
        value: string,
    ): void {
        updateProfile(
            'photos',
            profile.photos.map((photo) =>
                photo.id === id ? { ...photo, [key]: value } : photo,
            ),
        );
    }

    function movePhoto(index: number, offset: -1 | 1): void {
        const destination = index + offset;
        if (destination < 0 || destination >= profile.photos.length) return;

        const photos = [...profile.photos];
        [photos[index], photos[destination]] = [
            photos[destination],
            photos[index],
        ];
        updateProfile('photos', photos);
    }
    function movePhotoToCover(index: number): void {
        if (index <= 0 || index >= profile.photos.length) return;

        const photos = [...profile.photos];
        photos.unshift(...photos.splice(index, 1));
        updateProfile('photos', photos);
    }

    function validateSelectedFiles(files: FileList): string | null {
        if (profile.photos.length + files.length > maxPhotos) {
            return `Choose no more than ${maxPhotos - profile.photos.length} additional photo${maxPhotos - profile.photos.length === 1 ? '' : 's'}.`;
        }

        for (const file of Array.from(files)) {
            if (!allowedTypes.has(file.type)) {
                return `${file.name} is not a supported image. Choose JPEG, PNG, or WebP.`;
            }
            if (file.size > maxFileBytes) {
                return `${file.name} is larger than 5 MiB. Choose a smaller image.`;
            }
        }
        return null;
    }

    async function addPhotos(event: Event): Promise<void> {
        const input = event.currentTarget;
        if (!(input instanceof HTMLInputElement) || !input.files?.length) {
            return;
        }

        const files = input.files;
        addError = validateSelectedFiles(files) ?? '';
        if (addError) {
            input.value = '';
            return;
        }

        pendingAdding = true;
        try {
            const result = await onaddphotos(files);
            addError = result.error ?? '';
            if (!addError) {
                serverErrors = {};
            }
        } catch {
            addError = 'The photo could not be uploaded. Choose it again to retry safely.';
        } finally {
            pendingAdding = false;
            input.value = '';
        }
    }

    function removePhoto(id: string | number): void {
        serverErrors = {};
        onremovephoto(id);
    }
    function saveDraft(): void {
        actionAttempted = true;
        if (!canUseDraft) return;
        onsave();
    }

    function previewProgram(): void {
        actionAttempted = true;
        if (!canPreviewSaved) return;
        onpreview();
    }

    function publishProfile(): void {
        actionAttempted = true;
        if (!canUseDraft || isDirty || !canPublish) return;
        onpublish();
    }

</script>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Edit public program profile"
            description="Update the saved description and photo gallery shown in a published program profile."
        />
        <p class="border-border bg-card/70 text-muted-foreground rounded-lg border p-3 text-sm" role="status" data-testid="profile-revision-status">
            {#if revision > 0}Saved revision {revision}.{:else}No saved revision yet.{/if} {#if publishedRevision}Guests currently see published revision {publishedRevision}.{:else}No profile has been published yet.{/if}
            {#if isDirty} Save the draft before opening a private preview or publishing.{:else} This editor is private; visitors never see a draft.{/if}
        </p>

        {#if statusMessage}
            <p
                class="border-border bg-card/70 text-foreground rounded-lg border p-3 text-sm"
                role="status"
                data-testid="profile-status"
            >
                {statusMessage}
            </p>
        {/if}

        <form
            class="grid min-w-0 gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(18rem,0.85fr)]"
            onsubmit={(event) => {
                event.preventDefault();
                saveDraft();
            }}
            novalidate
        >
            <div class="min-w-0 space-y-6">
                <section
                    class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6"
                    aria-labelledby="program-identity-title"
                >
                    <div>
                        <h2
                            id="program-identity-title"
                            class="text-lg font-semibold"
                        >
                            Program identity
                        </h2>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Catalog details are read-only here.
                        </p>
                    </div>
                    <div
                        class="grid gap-4 sm:grid-cols-[minmax(8rem,0.45fr)_minmax(0,1fr)]"
                    >
                        <div class="space-y-2">
                            <Label for="profile-program-code" class=""
                                >Program code</Label
                            >
                            <Input
                                id="profile-program-code"
                                type="text"
                                value={program.code}
                                readonly
                                class="bg-muted/40 min-h-11"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="profile-program-name" class=""
                                >Program name</Label
                            >
                            <Input
                                id="profile-program-name"
                                type="text"
                                value={program.name}
                                readonly
                                class="bg-muted/40 min-h-11"
                            />
                        </div>
                    </div>
                </section>

                <section
                    class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6"
                    aria-labelledby="profile-description-title"
                >
                    <div>
                        <h2
                            id="profile-description-title"
                            class="text-lg font-semibold"
                        >
                            Program description
                        </h2>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Use clear, welcoming language that helps prospective
                            students understand the program.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="short-description" class=""
                                >Short description</Label
                            >
                            <span
                                class="text-muted-foreground text-xs tabular-nums"
                                aria-live="polite"
                                >{shortDescriptionLength}/240</span
                            >
                        </div>
                        <Textarea
                            id="short-description"
                            bind:value={profile.short_description}
                            disabled={commandPending}
                            oninput={clearServerErrors}
                            rows="3"
                            maxlength="240"
                            aria-invalid={Boolean(
                                fieldErrors.short_description,
                            )}
                            aria-describedby="short-description-help short-description-error"
                            class="min-h-24 resize-y leading-6"
                            data-testid="short-description"
                        />
                        <p
                            id="short-description-help"
                            class="text-muted-foreground text-xs"
                        >
                            A concise introduction shown near the program title.
                        </p>
                        {#if fieldErrors.short_description}
                            <p
                                id="short-description-error"
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {fieldErrors.short_description}
                            </p>
                        {/if}
                    </div>
                    <div class="space-y-2">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <Label for="full-description" class=""
                                >Full description</Label
                            >
                            <span
                                class="text-muted-foreground text-xs tabular-nums"
                                aria-live="polite"
                                >{descriptionLength.toLocaleString()}/10,000</span
                            >
                        </div>
                        <Textarea
                            id="full-description"
                            bind:value={profile.description}
                            disabled={commandPending}
                            oninput={clearServerErrors}
                            rows="9"
                            maxlength="10000"
                            aria-invalid={Boolean(fieldErrors.description)}
                            aria-describedby="full-description-help full-description-error"
                            class="min-h-48 resize-y leading-6"
                            data-testid="full-description"
                        />
                        <p
                            id="full-description-help"
                            class="text-muted-foreground text-xs"
                        >
                            Describe the learning experience, areas of study,
                            and opportunities without making unverified
                            promises.
                        </p>
                        {#if fieldErrors.description}
                            <p
                                id="full-description-error"
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {fieldErrors.description}
                            </p>
                        {/if}
                    </div>
                </section>

                <section
                    class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6"
                    aria-labelledby="profile-photos-title"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <h2
                                id="profile-photos-title"
                                class="text-lg font-semibold"
                            >
                                Program photos
                            </h2>
                            <p class="text-muted-foreground mt-1 max-w-2xl text-sm leading-6">
                                Stage up to six JPEG, PNG, or WebP images, each no larger than 5 MiB and 4096 by 4096 pixels. The server strips source metadata and keeps sanitized photos private until a profile revision is published.
                            </p>
                        </div>
                        <span
                            class="bg-muted text-muted-foreground shrink-0 rounded-md px-2.5 py-1.5 text-xs font-medium tabular-nums"
                            >{profile.photos.length}/{maxPhotos} photos</span
                        >
                    </div>

                    <div class="space-y-2">
                        <Label for="program-photo-files" class="">Choose photos</Label>
                        <Input
                            id="program-photo-files"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            disabled={commandPending || pendingAdding ||
                                profile.photos.length >= maxPhotos}
                            onchange={addPhotos}
                            aria-describedby="photo-upload-help photo-add-error"
                            data-testid="photo-file-input"
                            class="min-h-11 py-1.5"
                        />
                        <p
                            id="photo-upload-help"
                            class="text-muted-foreground text-xs leading-5"
                        >
                            Supported formats: JPEG, PNG, WebP. Maximum 5 MiB and 4096 by 4096 pixels per image; six photos in this profile.
                        </p>
                        {#if pendingAdding}
                            <p
                                class="text-muted-foreground text-sm"
                                role="status"
                                data-testid="photo-pending"
                            >
                                Uploading and sanitizing selected photos…
                            </p>
                        {/if}
                        {#if addError}
                            <p
                                id="photo-add-error"
                                class="text-destructive text-sm"
                                role="alert"
                                data-testid="photo-add-error"
                            >
                                {addError}
                            </p>
                        {/if}
                        {#if profile.photos.length > maxPhotos}
                            <p class="text-destructive text-sm" role="alert">
                                This preview can show no more than {maxPhotos} photos.
                            </p>
                        {/if}
                    </div>

                    {#if profile.photos.length === 0}
                        <div
                            class="border-border bg-muted/20 grid min-h-40 place-items-center rounded-lg border border-dashed px-5 py-8 text-center"
                            data-testid="photo-empty-state"
                        >
                            <div class="space-y-2">
                                <ImagePlus
                                    class="text-muted-foreground mx-auto size-7"
                                    aria-hidden="true"
                                />
                                <p class="text-foreground font-medium">
                                    No program photos added
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    Choose an image above to start the gallery.
                                </p>
                            </div>
                        </div>
                    {:else}
                        <ol
                            class="space-y-4"
                            aria-label="Program photo order"
                            data-testid="photo-gallery-editor"
                        >
                            {#each profile.photos as photo, index (photo.id)}
                                <li
                                    class="border-border/80 bg-background/50 min-w-0 rounded-lg border p-3 sm:p-4"
                                    data-testid="photo-item-{photo.id}"
                                >
                                    <div
                                        class="grid min-w-0 gap-4 sm:grid-cols-[minmax(8rem,0.72fr)_minmax(0,1.5fr)]"
                                    >
                                        <div
                                            class="bg-muted relative aspect-[4/3] overflow-hidden rounded-md"
                                        >
                                            {#if photo.available === false}
                                                <div class="text-muted-foreground grid size-full place-items-center p-3 text-center text-xs" role="note">
                                                    This private photo is unavailable. Remove or replace it before saving.
                                                </div>
                                            {:else}
                                                <img
                                                    src={photo.src}
                                                    alt={photo.alt_text || 'Photo awaiting alternative text'}
                                                    class="size-full object-cover"
                                                />
                                            {/if}
                                            {#if index === 0}
                                                <span
                                                    class="bg-background/95 text-foreground absolute bottom-2 left-2 rounded-md px-2 py-1 text-xs font-semibold shadow-sm"
                                                    data-testid="cover-badge"
                                                    >Cover photo</span
                                                >
                                            {/if}
                                        </div>
                                        <div class="min-w-0 space-y-3">
                                            <div
                                                class="flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <p
                                                    class="text-foreground text-sm font-semibold"
                                                >
                                                    Photo {index + 1}
                                                </p>
                                                <div
                                                    class="flex flex-wrap gap-2"
                                                    aria-label="Photo {index +
                                                        1} actions"
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        class="min-h-11 min-w-11 px-2.5"
                                                        disabled={commandPending || index === 0}
                                                        onclick={() =>
                                                            movePhoto(
                                                                index,
                                                                -1,
                                                            )}
                                                        aria-label="Move photo {index +
                                                            1} up"
                                                        data-testid="photo-move-up-{photo.id}"
                                                    >
                                                        <ArrowUp
                                                            class="size-4"
                                                            aria-hidden="true"
                                                        /><span class="sr-only"
                                                            >Move up</span
                                                        >
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        class="min-h-11 min-w-11 px-2.5"
                                                        disabled={commandPending || index ===
                                                            profile.photos
                                                                .length -
                                                                1}
                                                        onclick={() =>
                                                            movePhoto(index, 1)}
                                                        aria-label="Move photo {index +
                                                            1} down"
                                                        data-testid="photo-move-down-{photo.id}"
                                                    >
                                                        <ArrowDown
                                                            class="size-4"
                                                            aria-hidden="true"
                                                        /><span class="sr-only"
                                                            >Move down</span
                                                        >
                                                    </Button>
                                                    {#if index > 0}
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            class="min-h-11"
                                                            disabled={commandPending}
                                                            onclick={() =>
                                                                movePhotoToCover(
                                                                    index,
                                                                )}
                                                            data-testid="photo-make-cover-{photo.id}"
                                                            >Make cover</Button
                                                        >
                                                    {/if}
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        class="text-destructive min-h-11 min-w-11 px-2.5"
                                                        disabled={commandPending}
                                                        onclick={() =>
                                                            removePhoto(
                                                                photo.id,
                                                            )}
                                                        aria-label="Remove photo {index +
                                                            1}"
                                                        data-testid="photo-remove-{photo.id}"
                                                    >
                                                        <X
                                                            class="size-4"
                                                            aria-hidden="true"
                                                        /><span class="sr-only"
                                                            >Remove</span
                                                        >
                                                    </Button>
                                                </div>
                                            </div>
                                            <div class="space-y-2">
                                                <div
                                                    class="flex items-center justify-between gap-2"
                                                >
                                                    <Label
                                                        for="photo-alt-{photo.id}"
                                                        class=""
                                                        >Alternative text</Label
                                                    >
                                                    <span
                                                        class="text-muted-foreground text-xs tabular-nums"
                                                        >{photo.alt_text
                                                            .length}/160</span
                                                    >
                                                </div>
                                                <Input
                                                    id="photo-alt-{photo.id}"
                                                    type="text"
                                                    disabled={commandPending}
                                                    value={photo.alt_text}
                                                    maxlength="160"
                                                    oninput={(event: Event) => {
                                                        if (event.currentTarget instanceof HTMLInputElement) {
                                                            updatePhoto(photo.id, 'alt_text', event.currentTarget.value);
                                                        }
                                                    }}
                                                    aria-invalid={Boolean(
                                                        fieldErrors.photos[photo.id]?.alt,
                                                    )}
                                                    aria-describedby="photo-alt-error-{photo.id}"
                                                    class="min-h-11"
                                                    data-testid="photo-alt-{photo.id}"
                                                />
                                                {#if fieldErrors.photos[photo.id]?.alt}
                                                    <p
                                                        id="photo-alt-error-{photo.id}"
                                                        class="text-destructive text-sm"
                                                        role="alert"
                                                    >
                                                        {fieldErrors.photos[photo.id]?.alt}
                                                    </p>
                                                {/if}
                                            </div>
                                            <div class="space-y-2">
                                                <div
                                                    class="flex items-center justify-between gap-2"
                                                >
                                                    <Label
                                                        for="photo-caption-{photo.id}"
                                                        class=""
                                                        >Caption <span
                                                            class="text-muted-foreground font-normal"
                                                            >(optional)</span
                                                        ></Label
                                                    >
                                                    <span
                                                        class="text-muted-foreground text-xs tabular-nums"
                                                        >{photo.caption
                                                            .length}/240</span
                                                    >
                                                </div>
                                                <Input
                                                    id="photo-caption-{photo.id}"
                                                    type="text"
                                                    disabled={commandPending}
                                                    value={photo.caption}
                                                    maxlength="240"
                                                    oninput={(event: Event) => {
                                                        if (event.currentTarget instanceof HTMLInputElement) {
                                                            updatePhoto(photo.id, 'caption', event.currentTarget.value);
                                                        }
                                                    }}
                                                    aria-invalid={Boolean(
                                                        fieldErrors.photos[photo.id]?.caption,
                                                    )}
                                                    aria-describedby="photo-caption-error-{photo.id}"
                                                    class="min-h-11"
                                                    data-testid="photo-caption-{photo.id}"
                                                />
                                                {#if fieldErrors.photos[photo.id]?.caption}
                                                    <p
                                                        id="photo-caption-error-{photo.id}"
                                                        class="text-destructive text-sm"
                                                        role="alert"
                                                    >
                                                        {fieldErrors.photos[photo.id]?.caption}
                                                    </p>
                                                {/if}
                                            </div>
                                            {#if fieldErrors.photos[photo.id]?.media}
                                                <p class="text-destructive text-sm" role="alert">{fieldErrors.photos[photo.id]?.media}</p>
                                            {/if}
                                        </div>
                                    </div>
                                </li>
                            {/each}
                        </ol>
                    {/if}
                </section>
            </div>

            <aside
                class="min-w-0 space-y-5 xl:sticky xl:top-6 xl:self-start"
                aria-label="Program profile actions and preview"
            >
                <section
                    class="border-border bg-card/80 min-w-0 space-y-4 rounded-lg border p-4 sm:p-5"
                    aria-labelledby="program-preview-title"
                >
                    <div>
                        <h2
                            id="program-preview-title"
                            class="text-lg font-semibold"
                        >
                            Private editor preview
                        </h2>
                        <p class="text-muted-foreground mt-1 text-sm">
                            This preview follows your current editor state. Guests see only the last explicitly published revision.
                    </div>
                    <article
                        class="border-border bg-background min-w-0 overflow-hidden rounded-lg border"
                    >
                        {#if previewPhoto}
                            <div class="bg-muted aspect-[4/3]">
                                {#if previewPhoto.available === false}
                                    <div class="text-muted-foreground grid size-full place-items-center p-4 text-center text-sm">
                                        Private cover photo unavailable. Remove or replace it before publishing.
                                    </div>
                                {:else}
                                    <img
                                        src={previewPhoto.src}
                                        alt={previewPhoto.alt_text || 'Program cover photo preview'}
                                        class="size-full object-cover"
                                        data-testid="profile-preview-image"
                                    />
                                {/if}
                            </div>
                        {:else}
                            <div
                                class="bg-muted/40 text-muted-foreground grid aspect-[4/3] place-items-center px-4 text-center text-sm"
                                data-testid="profile-preview-image-empty"
                            >
                                No cover photo selected
                            </div>
                        {/if}
                        <div class="space-y-3 p-4">
                            <p
                                class="text-muted-foreground text-xs font-semibold tracking-wide"
                            >
                                {program.code}
                            </p>
                            <h3
                                class="text-foreground text-xl leading-snug font-semibold"
                            >
                                {program.name}
                            </h3>
                            <p class="text-foreground text-sm leading-6">
                                {profile.short_description.trim() ||
                                    'Your short description will appear here.'}
                            </p>
                            {#if previewPhoto?.caption}
                                <p
                                    class="text-muted-foreground text-xs leading-5"
                                >
                                    {previewPhoto.caption}
                                </p>
                            {/if}
                            <p
                                class="text-muted-foreground line-clamp-4 text-sm leading-6 whitespace-pre-line"
                            >
                                {profile.description.trim() ||
                                    'Your full program description will appear here.'}
                            </p>
                            <p
                                class="border-border text-muted-foreground border-t pt-3 text-xs"
                            >
                                {profile.photos.length}
                                {profile.photos.length === 1
                                    ? 'photo'
                                    : 'photos'} in this gallery
                            </p>
                        </div>
                    </article>
                    {#if (actionAttempted || Object.keys(fieldErrors.photos).length > 0 || !String(profile.description ?? '').trim()) && hasValidationErrors}
                        <div
                            class="border-destructive/30 bg-destructive/5 text-destructive rounded-md border p-3 text-sm"
                            role="alert"
                            data-testid="profile-validation-summary"
                        >
                            <p class="font-medium">
                                Review the profile before saving or previewing.
                            </p>
                            {#if serverErrors.expected_revision}
                                <p class="mt-1">{serverErrors.expected_revision} Your edits are preserved. Reload the current draft version before trying again.</p>
                                <Button type="button" variant="outline" class="mt-2 min-h-11" disabled={commandPending} onclick={() => window.location.reload()} data-testid="reload-latest-profile">Load latest profile revision</Button>
                            {/if}
                            {#if serverErrors.photos}
                                <p class="mt-1">{serverErrors.photos}</p>
                            {/if}
                            {#if fieldErrors.description}<p class="mt-1">
                                    {fieldErrors.description}
                                </p>{/if}
                            {#if Object.keys(fieldErrors.photos).length > 0}<p
                                    class="mt-1"
                                >
                                    Add valid alternative text and keep each
                                    caption within its limit.
                                </p>{/if}
                            {#if fieldErrors.short_description}<p class="mt-1">
                                    {fieldErrors.short_description}
                                </p>{/if}
                        </div>
                    {/if}
                </section>

                <section
                    class="border-border bg-card/80 space-y-4 rounded-lg border p-4 sm:p-5"
                    aria-labelledby="profile-actions-title"
                >
                    <div>
                        <h2
                            id="profile-actions-title"
                            class="text-lg font-semibold"
                        >
                            Save and publish
                        </h2>
                        <p class="text-muted-foreground mt-1 text-sm leading-5">
                            Save a private draft first. Publishing explicitly makes only that saved revision visible to visitors.
                        </p>
                    </div>
                    {#if statusMessage}
                        <p
                            class="rounded-lg border border-primary/30 bg-primary/10 p-3 text-xs font-medium text-foreground"
                            role="status"
                        >
                            {statusMessage}
                        </p>
                    {/if}
                    <div class="grid gap-2">
                        <Button
                            type="button"
                            class="min-h-11 w-full"
                            disabled={!canUseDraft}
                            onclick={saveDraft}
                            data-testid="save-program-draft"
                        >
                            {#if isSaving}Saving draft…{:else}Save draft{/if}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-11 w-full"
                            disabled={!canPreviewSaved}
                            onclick={previewProgram}
                            data-testid="preview-program"
                        >
                            Preview saved draft privately
                        </Button>
                    <Button
                        type="button"
                        class="min-h-11 w-full"
                        disabled={!canPublish || !canUseDraft || isDirty || revision < 1 || publishedRevision === revision}
                        onclick={publishProfile}
                        aria-describedby="publish-program-guidance"
                        data-testid="publish-program"
                    >
                        {#if isPublishing}Publishing saved revision…{:else}Publish saved draft revision{/if}
                    </Button>
                    <p id="publish-program-guidance" class="text-muted-foreground text-xs leading-5">
                        {#if isDirty}
                            Save the current edits as a draft before publishing. Unsaved text and photo changes are never published.
                        {:else}
                            Publishing makes only this saved revision public.
                        {/if}
                    </p>
                    </div>
                </section>
            </aside>
        </form>
    </div>
</StaffLayout>
