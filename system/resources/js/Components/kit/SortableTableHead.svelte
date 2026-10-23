<script lang="ts">
    import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-svelte';
    import { Head } from '@/Components/ui/table';
    import { cn } from '@/lib/utils.js';

    type Props = {
        label: string;
        direction?: 'none' | 'ascending' | 'descending';
        onclick: () => void;
        ariaLabel?: string;
        align?: 'left' | 'right';
        class?: string;
    };

    let {
        label,
        direction = 'none',
        onclick,
        ariaLabel,
        align = 'left',
        class: className = '',
    }: Props = $props();
</script>

<Head aria-sort={direction} class={cn('px-1.5 py-1', align === 'right' ? 'text-right' : 'text-left', className)}>
    <button
        type="button"
        aria-label={ariaLabel ?? `Sort by ${label}`}
        class={cn('flex min-h-[44px] w-full items-center gap-1.5 rounded-sm px-1.5 font-sans hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring', align === 'right' ? 'justify-end text-right' : 'text-left')}
        {onclick}
    >
        {label}
        {#if direction === 'ascending'}
            <ArrowUp class="size-3.5 shrink-0" aria-hidden="true" />
        {:else if direction === 'descending'}
            <ArrowDown class="size-3.5 shrink-0" aria-hidden="true" />
        {:else}
            <ArrowUpDown class="size-3.5 shrink-0" aria-hidden="true" />
        {/if}
    </button>
</Head>
