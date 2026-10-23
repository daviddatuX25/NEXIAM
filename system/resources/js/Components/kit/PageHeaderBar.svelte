<script lang="ts">
    import type { Snippet } from 'svelte';
    import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';

    let {
        title = '',
        description = '',
        subtitle = '',
        actions,
        actions_content,
        class: className = '',
        tourKey = null,
        context = undefined,
        icon = undefined,
        canAskAi = undefined,
    }: {
        title?: string | Snippet;
        description?: string | Snippet;
        subtitle?: string | Snippet;
        actions?: Snippet;
        actions_content?: Snippet;
        class?: string;
        tourKey?: string | null;
        context?: unknown;
        icon?: any;
        canAskAi?: boolean;
    } = $props();
    let isTourOpen = $state(false);

    const hasTitle = $derived(Boolean(title));
    const subText = $derived(
        typeof description === 'string' && description
            ? description
            : typeof subtitle === 'string' && subtitle
              ? subtitle
              : '',
    );
</script>

<div
    class="flex min-w-0 flex-col justify-between gap-3 sm:flex-row sm:items-start {className}"
>
    <div class="flex min-w-0 items-start gap-3">
        {#if icon}
            {@const IconComponent = icon}
            <div
                class="border-border/80 bg-muted/40 text-primary mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-xl border shadow-2xs"
            >
                <IconComponent class="text-primary size-5.5" />
            </div>
        {/if}
        <div class="min-w-0">
            {#if typeof title === 'function'}
                <div class="flex items-center gap-2">
                    {@render title()}
                    {#if tourKey}
                        <TourTriggerGlyph
                            {tourKey}
                            onclick={() => (isTourOpen = true)}
                        />
                    {/if}
                </div>
            {:else if title}
                <div class="flex items-center gap-2">
                    <h1
                        class="text-foreground text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        {title}
                    </h1>
                    {#if tourKey}
                        <TourTriggerGlyph
                            {tourKey}
                            onclick={() => (isTourOpen = true)}
                        />
                    {/if}
                </div>
            {/if}
            {#if typeof subtitle === 'function'}
                {@render subtitle()}
            {:else if typeof description === 'function'}
                {@render description()}
            {:else if subText}
                <p
                    class="text-muted-foreground text-sm {hasTitle
                        ? 'mt-1'
                        : ''}"
                >
                    {subText}
                </p>
            {/if}
        </div>
    </div>
    {#if actions}
        <div
            class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end sm:gap-3"
        >
            {@render actions()}
        </div>
    {:else if actions_content}
        <div
            class="flex w-full shrink-0 flex-wrap items-center gap-2 sm:w-auto sm:justify-end sm:gap-3"
        >
            {@render actions_content()}
        </div>
    {/if}
</div>

{#if tourKey}
    <XiamNpcDialog {tourKey} {context} {canAskAi} bind:open={isTourOpen} />
{/if}
