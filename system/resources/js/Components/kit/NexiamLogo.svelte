<script lang="ts">
    /**
     * NexiamLogo.svelte
     * Canonical brand component for NEXIAM platform identity.
     * Supports emblem-only ('mark') and emblem + wordmark ('full') variants.
     */
    interface Props {
        variant?: 'mark' | 'full';
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'hero';
        class?: string;
        alt?: string;
        priority?: boolean;
        interactive?: boolean;
        darkInvertText?: boolean;
    }

    let {
        variant = 'mark',
        size = 'md',
        class: className = '',
        alt = 'NEXIAM',
        priority = false,
        interactive = false,
        darkInvertText = true,
    }: Props = $props();

    const sizeClasses = {
        xs: 'h-4 w-auto',
        sm: 'h-6 w-auto',
        md: 'h-8 w-auto',
        lg: 'h-10 w-auto',
        xl: 'h-12 w-auto',
        '2xl': 'h-16 w-auto',
        hero: 'h-24 sm:h-28 w-auto',
    };

    const targetHeight = $derived(sizeClasses[size] ?? sizeClasses.md);

    const assetSources = $derived.by(() => {
        if (variant === 'full') {
            return {
                webp: '/assets/branding/nexiam/nexiam-logo-with-text.webp',
                png: '/assets/branding/nexiam/nexiam-logo-with-text.png',
                aspect: 'aspect-[1146/431]',
            };
        }
        return {
            webp: '/assets/branding/nexiam/nexiam-logo-only.webp',
            png: '/assets/branding/nexiam/nexiam-logo-only.png',
            aspect: 'aspect-[543/509]',
        };
    });

    const filterClasses = $derived.by(() => {
        if (variant === 'full' && darkInvertText) {
            // Brightens and adjusts dark navy text in wordmark on dark backgrounds
            return 'dark:brightness-150 dark:contrast-125';
        }
        return '';
    });
</script>

<picture class="inline-flex items-center shrink-0 {className}">
    <source srcset={assetSources.webp} type="image/webp" />
    <img
        src={assetSources.png}
        {alt}
        loading={priority ? 'eager' : 'lazy'}
        fetchpriority={priority ? 'high' : 'auto'}
        class="{targetHeight} max-h-full max-w-full {assetSources.aspect} object-contain select-none {filterClasses} {interactive ? 'transition-transform duration-200 hover:scale-105 active:scale-95' : ''}"
        draggable="false"
    />
</picture>
