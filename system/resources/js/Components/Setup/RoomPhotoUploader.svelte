<script>
    import { router } from '@inertiajs/svelte';

    let {
        roomId = null,
        currentPhotoPath = null,
        onPhotoChange = () => {},
    } = $props();

    let fileInput = $state(null);
    let selectedFile = $state(null);
    let previewUrl = $state(null);
    let dragActive = $state(false);
    let errorMessage = $state('');
    let processing = $state(false);

    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const photoUrl = $derived(
        currentPhotoPath
            ? currentPhotoPath.startsWith('http') || currentPhotoPath.startsWith('/')
                ? currentPhotoPath
                : `/storage/${currentPhotoPath}`
            : null,
    );
    const displayedImage = $derived(previewUrl ?? photoUrl);

    function chooseFile(file) {
        errorMessage = '';
        if (!file) {
            return;
        }
        if (!allowedTypes.includes(file.type)) {
            errorMessage = 'Choose a JPEG, PNG, or WebP image.';
            return;
        }

        selectedFile = file;
        previewUrl = URL.createObjectURL(file);
        onPhotoChange(file);
    }

    function handleInput(event) {
        chooseFile(event.currentTarget.files?.[0] ?? null);
        event.currentTarget.value = '';
    }

    function handleDrop(event) {
        event.preventDefault();
        dragActive = false;
        chooseFile(event.dataTransfer.files?.[0] ?? null);
    }

    function uploadPhoto() {
        if (!roomId || !selectedFile || processing) {
            return;
        }

        processing = true;
        router.post(
            `/setup/rooms/${roomId}/photo`,
            { photo: selectedFile },
            {
                forceFormData: true,
                onSuccess: () => {
                    selectedFile = null;
                    previewUrl = null;
                    onPhotoChange(null);
                },
                onError: () => {
                    errorMessage = 'The photo could not be uploaded. Please try again.';
                },
                onFinish: () => (processing = false),
            },
        );
    }

    function removePhoto() {
        if (!roomId || processing) {
            return;
        }

        processing = true;
        router.delete(`/setup/rooms/${roomId}/photo`, {
            onSuccess: () => onPhotoChange(null),
            onError: () => {
                errorMessage = 'The photo could not be removed. Please try again.';
            },
            onFinish: () => (processing = false),
        });
    }
    $effect(() => {
        const currentPreviewUrl = previewUrl;
        return () => {
            if (currentPreviewUrl) {
                URL.revokeObjectURL(currentPreviewUrl);
            }
        };
    });
</script>

<section class="space-y-3" aria-label="Entrance photo">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold text-foreground">Entrance photo</h4>
            <p class="text-xs text-muted-foreground">Portrait view of the room entrance</p>
        </div>
        {#if roomId && currentPhotoPath && !selectedFile}
            <button
                type="button"
                class="text-xs font-medium text-destructive underline-offset-4 hover:underline disabled:opacity-50"
                onclick={removePhoto}
                disabled={processing}
            >Remove Photo</button>
        {/if}
    </div>

    <input
        bind:this={fileInput}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        class="sr-only"
        onchange={handleInput}
        aria-label="Choose an entrance photo"
    />
    <button
        type="button"
        class="group relative block aspect-[3/4] w-full overflow-hidden rounded-lg border border-dashed border-border bg-muted/30 text-left transition-colors hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {dragActive ? 'border-primary bg-primary/5' : ''}"
        onclick={() => fileInput?.click()}
        ondragover={(event) => {
            event.preventDefault();
            dragActive = true;
        }}
        ondragleave={() => (dragActive = false)}
        ondrop={handleDrop}
        aria-label="Choose or drop an uncropped three by four entrance photo"
    >
        {#if displayedImage}
            <img
                src={displayedImage}
                alt="Room entrance preview"
                class="absolute inset-0 h-full w-full bg-muted object-contain"
            />
            <span class="absolute inset-x-0 bottom-0 bg-black/60 px-3 py-2 text-center text-xs font-medium text-white">Choose a different photo</span>
        {:else}
            <span class="flex h-full flex-col items-center justify-center gap-2 px-5 text-center">
                <span class="text-sm font-semibold text-foreground">Drop entrance photo here</span>
                <span class="text-xs text-muted-foreground">or click to browse · JPEG, PNG, WebP</span>
                <span class="text-[11px] text-muted-foreground">Portrait 3:4 · image will remain uncropped</span>
            </span>
        {/if}
    </button>

    {#if errorMessage}
        <p class="text-sm text-destructive" role="alert">{errorMessage}</p>
    {/if}

    {#if roomId && selectedFile}
        <button
            type="button"
            class="bg-primary text-primary-foreground hover:bg-primary/90 min-h-10 w-full rounded-md px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50"
            onclick={uploadPhoto}
            disabled={processing}
        >{processing ? 'Uploading…' : 'Upload Photo'}</button>
    {:else if !roomId && selectedFile}
        <p class="text-xs text-muted-foreground">Photo selected. Save the room before uploading.</p>
    {/if}
</section>
