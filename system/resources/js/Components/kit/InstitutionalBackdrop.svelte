<script>
    /**
     * Institutional Backdrop component.
     * Responsive background watermarking supporting mobile (portrait) and desktop/tablet (landscape).
     * Tuned for high contrast, theme transitions (dark/light), and frosted glassmorphism.
     */
    let {
        desktopSrc = '/assets/branding/ispsc/backgrounds/campus-hero.webp',
        mobileSrc = '/assets/branding/ispsc/backgrounds/campus-hero-mobile.webp',
        alt = 'Campus background',
        scrim = true,
        opacity = 'opacity-[0.11] sm:opacity-[0.14] dark:opacity-[0.22]',
        position = 'object-center',
        fixed = false,
        class: className = '',
    } = $props();
</script>

<div
    class="pointer-events-none {fixed ? 'fixed' : 'absolute'} inset-0 z-0 overflow-hidden select-none {className}"
    aria-hidden="true"
>
    <!-- Responsive picture: browser requests only the matching device image -->
    <picture class="block h-full w-full">
        <!-- Tablet and Desktop (min-width: 640px) -->
        <source media="(min-width: 640px)" srcset={desktopSrc} type="image/webp" />
        <!-- Mobile portrait fallback -->
        <img
            src={mobileSrc}
            {alt}
            class="h-full w-full object-cover {position} contrast-95 saturate-[0.70] brightness-105 {opacity} transition-opacity duration-300 dark:brightness-80 dark:contrast-115 dark:saturate-[0.85]"
            loading="eager"
            fetchpriority="high"
            decoding="sync"
        />
    </picture>

    {#if scrim}
        <!-- Atmospheric scrim: gently fades top into page background color while keeping buildings distinct -->
        <div
            class="absolute inset-0 bg-gradient-to-t from-background/75 via-background/50 to-background/80 dark:from-background/60 dark:via-background/35 dark:to-background/70"
        ></div>
    {/if}
</div>
