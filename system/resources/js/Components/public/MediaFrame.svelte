<script>
    let {
        image = null,
        ratio = 'aspect-[4/3]',
        alt = '',
        loading = 'lazy',
        priority = false,
        showSource = false,
        class: className = '',
    } = $props();

    let failed = $state(false);

    const imageAlt = $derived(image?.alt ?? alt);
    const sourceLabel = $derived(image?.source ?? null);
</script>

<div
    class="group relative isolate overflow-hidden rounded-[1.25rem] border border-border/60 bg-muted/70 shadow-[0_18px_60px_-32px_oklch(0.22_0.04_155_/_0.42)] {ratio} {className}"
>
    {#if image?.src && !failed}
        <img
            src={image.src}
            alt={imageAlt}
            loading={priority ? 'eager' : loading}
            decoding="async"
            fetchpriority={priority ? 'high' : 'auto'}
            class="absolute inset-0 h-full w-full object-cover transition duration-700 ease-out group-hover:scale-[1.025]"
            onerror={() => (failed = true)}
        />
    {:else}
        <div
            class="absolute inset-0 flex items-end bg-[radial-gradient(circle_at_20%_20%,oklch(0.78_0.12_150_/_0.42),transparent_32%),linear-gradient(135deg,oklch(0.29_0.08_155),oklch(0.16_0.04_160))] p-6"
            role="img"
            aria-label={imageAlt || 'Image placeholder'}
        >
            <span class="max-w-[18rem] text-sm font-medium leading-relaxed text-white/80">
                Image placeholder — replace with approved campus media.
            </span>
        </div>
    {/if}

    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-white/10"></div>

    {#if showSource && sourceLabel}
        <span
            class="absolute right-3 bottom-3 rounded-full border border-white/20 bg-black/35 px-2.5 py-1 text-[0.65rem] font-semibold tracking-[0.12em] text-white/80 uppercase backdrop-blur"
        >
            {sourceLabel}
        </span>
    {/if}
</div>
