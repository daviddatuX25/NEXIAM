<script>
    let {
        FileIcon = null,
        icon = 'upload',
        label = 'Upload file',
        sublabel = 'Drag and drop or click to browse',
        accept = '*',
        multiple = false,
        maxSize = null,
        maxFiles = null,
        disabled = false,
        files = $bindable([]),
        error = $bindable(''),
        onfiles = null,
        onerror = null,
    } = $props();

    let isDragging = $state(false);

    let acceptTypes = $derived(
        accept === '*' || !accept ? [] : accept.split(',').map((a) => a.trim()),
    );

    function parseSize(size) {
        if (!size) return null;
        const match = size.match(/^(\d+)([kmg]?b?)$/i);
        if (!match) return null;
        const [, num, unit] = match;
        const multiplier =
            {
                kb: 1024,
                mb: 1024 * 1024,
                gb: 1024 * 1024 * 1024,
            }[unit.toLowerCase()] || 1;
        return parseInt(num) * multiplier;
    }

    function formatSize(bytes) {
        if (!bytes) return '';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }
        return `${bytes.toFixed(1)} ${units[i]}`;
    }

    function validateFiles(fileList) {
        const issues = [];

        if (maxFiles && fileList.length > maxFiles) {
            issues.push(`Maximum ${maxFiles} file(s) allowed`);
        }

        const maxSizeBytes = parseSize(maxSize);
        if (maxSizeBytes) {
            for (const f of fileList) {
                if (f.size > maxSizeBytes) {
                    issues.push(`${f.name} exceeds ${formatSize(maxSizeBytes)} limit`);
                }
            }
        }

        if (accept !== '*' && accept !== '*/*' && acceptTypes.length) {
            const allowed = acceptTypes.map((a) => a.replace('.', '').toLowerCase());
            for (const f of fileList) {
                const ext = f.name.split('.').pop()?.toLowerCase();
                if (ext && !allowed.includes(ext)) {
                    issues.push(`${f.name} has invalid file type`);
                }
            }
        }

        return issues;
    }

    function handleDragEnter(e) {
        e.preventDefault();
        if (!disabled) isDragging = true;
    }

    function handleDragLeave(e) {
        e.preventDefault();
        isDragging = false;
    }

    function handleDragOver(e) {
        e.preventDefault();
    }

    function handleDrop(e) {
        e.preventDefault();
        isDragging = false;
        if (disabled) return;

        const droppedFiles = Array.from(e.dataTransfer?.files || []);
        if (droppedFiles.length) {
            processFiles(droppedFiles);
        }
    }

    function handleFileChange(e) {
        const selectedFiles = Array.from(e.target?.files || []);
        if (selectedFiles.length) {
            processFiles(selectedFiles);
        }
        e.target.value = '';
    }

    function processFiles(fileList) {
        const validationErrors = validateFiles(fileList);
        if (validationErrors.length) {
            error = validationErrors[0];
            onerror?.({ message: validationErrors[0], files: validationErrors });
            return;
        }
        error = '';
        files = multiple ? [...files, ...fileList] : fileList;
        onfiles?.({ files: fileList });
    }
</script>

<label
    class="file-upload"
    class:dragging={isDragging}
    class:disabled
    class:has-error={!!error}
    tabindex={disabled ? -1 : 0}
    ondragenter={handleDragEnter}
    ondragleave={handleDragLeave}
    ondragover={handleDragOver}
    ondrop={handleDrop}
    onkeydown={(e) =>
        e.key === ' ' || e.key === 'Enter'
            ? e.currentTarget.querySelector('input')?.click()
            : null}
>
    <input
        type="file"
        {accept}
        {multiple}
        {disabled}
        onchange={handleFileChange}
        class="sr-only"
    />

    <div class="file-upload-content">
        <div class="icon-wrapper">
            {#if icon === 'upload'}
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
            {:else if FileIcon}
                <FileIcon class="size-5" />
            {:else}
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"
                    />
                    <polyline points="14 2 14 8 20 8" />
                </svg>
            {/if}
        </div>

        <div class="text-content">
            <span class="label">{label}</span>
            {#if sublabel}
                <span class="sublabel">{sublabel}</span>
            {/if}
            {#if accept !== '*'}
                <span class="accept-hint">Accepted: {accept}</span>
            {/if}
            {#if maxSize}
                <span class="size-hint">Max: {maxSize}</span>
            {/if}
        </div>

        {#if error}
            <p class="error-message">{error}</p>
        {/if}
    </div>
</label>

<style>
    .file-upload {
        border: 2px dashed hsl(var(--border));
        border-radius: var(--radius, 0.5rem);
        padding: 1.5rem 1rem;
        text-align: center;
        transition: all 0.2s ease;
        cursor: pointer;
        background: hsl(var(--card));
        color: hsl(var(--card-foreground));
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.75rem;
    }

    .file-upload:hover:not(.disabled) {
        border-color: hsl(var(--primary));
        background: hsl(var(--accent));
    }

    .file-upload:focus-visible {
        outline: 2px solid hsl(var(--ring));
        outline-offset: 2px;
    }

    .file-upload.dragging {
        border-color: hsl(var(--primary));
        background: hsl(var(--accent));
        transform: scale(1.01);
    }

    .file-upload.disabled {
        cursor: not-allowed;
        opacity: 0.5;
    }

    .file-upload.has-error {
        border-color: hsl(var(--destructive));
    }

    .icon-wrapper {
        color: hsl(var(--primary));
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .text-content {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        align-items: center;
    }

    .label {
        font-size: 0.9375rem;
        font-weight: 500;
        color: hsl(var(--foreground));
    }

    .sublabel {
        font-size: 0.8125rem;
        color: hsl(var(--muted-foreground));
    }

    .accept-hint,
    .size-hint {
        font-size: 0.75rem;
        color: hsl(var(--muted-foreground));
        margin-top: 0.25rem;
    }

    .error-message {
        font-size: 0.8125rem;
        color: hsl(var(--destructive));
        margin-top: 0.25rem;
    }
</style>
