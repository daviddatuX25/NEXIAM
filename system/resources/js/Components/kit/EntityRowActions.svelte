<script>
    import { Button } from '@/Components/ui/button';
    import { Link } from '@inertiajs/svelte';
    import { Ellipsis, ArrowRight } from 'lucide-svelte';
    import { getContext } from 'svelte';
    import * as DropdownMenu from '@/Components/ui/dropdown-menu';

    /**
     * EntityRowActions: The canonical 1 Primary CTA + ··· Overflow Action Pattern
     *
     * Props:
     * - primaryAction: { label: string, href?: string, onclick?: () => void, icon?: any, variant?: string, disabled?: boolean }
     * - overflowItems: Array<{ label: string, href?: string, onclick?: () => void, icon?: any, destructive?: boolean, disabled?: boolean, hidden?: boolean }>
     * - dense: boolean - whether to use compact h-7 / h-8 styling
     * - comfortable: boolean
     * - align: 'right' | 'left'
     * - triggerLabel: string
     * - unrolled: boolean
     */
    let {
        primaryAction = null,
        overflowItems = [],
        dense = false,
        comfortable = false,
        align = 'right',
        triggerLabel = 'More actions',
        unrolled = false,
        class: className = '',
    } = $props();

    const inActionMenu = getContext('inActionMenu');
    const isUnrolled = $derived(unrolled || Boolean(inActionMenu));

    let isOpen = $state(false);

    const visibleOverflowItems = $derived(
        overflowItems.filter((item) => item && !item.hidden),
    );

    const allOverflowDisabled = $derived(
        visibleOverflowItems.length > 0 &&
            visibleOverflowItems.every((i) => i.disabled),
    );

    const standardItems = $derived(
        visibleOverflowItems.filter((item) => !item.destructive),
    );

    const destructiveItems = $derived(
        visibleOverflowItems.filter((item) => item.destructive),
    );
</script>

{#if isUnrolled}
    <div class="w-full space-y-0.5 {className}" data-action-cell role="none">
        {#if primaryAction && primaryAction.label}
            {@const PrimaryIcon = primaryAction.icon ?? ArrowRight}
            {#if primaryAction.href && !primaryAction.disabled}
                <Link
                    href={primaryAction.href}
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                    role="menuitem"
                >
                    {#if primaryAction.icon !== false}
                        <PrimaryIcon
                            class="text-muted-foreground size-3.5 shrink-0"
                        />
                    {/if}
                    <span class="truncate">{primaryAction.label}</span>
                </Link>
            {:else}
                <button
                    type="button"
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    role="menuitem"
                    disabled={Boolean(primaryAction.disabled)}
                    onclick={() => primaryAction.onclick?.()}
                >
                    {#if primaryAction.icon !== false}
                        <PrimaryIcon
                            class="text-muted-foreground size-3.5 shrink-0"
                        />
                    {/if}
                    <span class="truncate">{primaryAction.label}</span>
                </button>
            {/if}
        {/if}
        {#each standardItems as item, idx (idx)}
            {#if item.href}
                <Link
                    href={item.href}
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                    role="menuitem"
                    aria-disabled={Boolean(item.disabled)}
                >
                    {#if item.icon}
                        {@const Icon = item.icon}
                        <Icon class="text-muted-foreground size-3.5 shrink-0" />
                    {/if}
                    <span class="truncate">{item.label}</span>
                </Link>
            {:else}
                <button
                    type="button"
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    role="menuitem"
                    disabled={Boolean(item.disabled)}
                    onclick={() => item.onclick?.()}
                >
                    {#if item.icon}
                        {@const Icon = item.icon}
                        <Icon class="text-muted-foreground size-3.5 shrink-0" />
                    {/if}
                    <span class="truncate">{item.label}</span>
                </button>
            {/if}
        {/each}
        {#if destructiveItems.length > 0}
            {#if standardItems.length > 0 || (primaryAction && primaryAction.label)}
                <div class="border-border/60 my-1 border-t"></div>
            {/if}
            {#each destructiveItems as item, idx (idx)}
                {#if item.href}
                    <Link
                        href={item.href}
                        class="text-destructive hover:bg-destructive/10 flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                        role="menuitem"
                        aria-disabled={Boolean(item.disabled)}
                    >
                        {#if item.icon}
                            {@const Icon = item.icon}
                            <Icon class="text-destructive size-3.5 shrink-0" />
                        {/if}
                        <span class="truncate">{item.label}</span>
                    </Link>
                {:else}
                    <button
                        type="button"
                        class="text-destructive hover:bg-destructive/10 flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                        role="menuitem"
                        disabled={Boolean(item.disabled)}
                        onclick={() => item.onclick?.()}
                    >
                        {#if item.icon}
                            {@const Icon = item.icon}
                            <Icon class="text-destructive size-3.5 shrink-0" />
                        {/if}
                        <span class="truncate">{item.label}</span>
                    </button>
                {/if}
            {/each}
        {/if}
    </div>
{:else}
    <div
        class="inline-flex shrink-0 flex-nowrap items-center gap-1.5 whitespace-nowrap {className}"
        data-action-cell
        onclick={(e) => e.stopPropagation()}
        role="presentation"
    >
        <!-- 1. The Single Primary CTA -->
        {#if primaryAction && primaryAction.label}
            {@const PrimaryIcon = primaryAction.icon ?? ArrowRight}
            {#if primaryAction.href && !primaryAction.disabled}
                <Link
                    href={primaryAction.href}
                    class="inline-block shrink-0 whitespace-nowrap"
                >
                    <Button
                        size={dense ? 'xs' : 'sm'}
                        variant={primaryAction.variant ?? 'outline'}
                        class="shrink-0 whitespace-nowrap {comfortable
                            ? 'min-h-11'
                            : ''}"
                        disabled={Boolean(primaryAction.disabled)}
                    >
                        <span>{primaryAction.label}</span>
                        {#if primaryAction.icon !== false}
                            <PrimaryIcon class="ml-1 size-3.5" />
                        {/if}
                    </Button>
                </Link>
            {:else}
                <Button
                    size={dense ? 'xs' : 'sm'}
                    variant={primaryAction.variant ?? 'outline'}
                    class="shrink-0 whitespace-nowrap {comfortable
                        ? 'min-h-11'
                        : ''}"
                    disabled={Boolean(primaryAction.disabled)}
                    onclick={(e) => {
                        e.stopPropagation();
                        primaryAction.onclick?.();
                    }}
                >
                    <span>{primaryAction.label}</span>
                    {#if primaryAction.icon !== false}
                        <PrimaryIcon class="ml-1 size-3.5" />
                    {/if}
                </Button>
            {/if}
        {/if}

        <!-- 2. The Contextual Overflow Menu (···) -->
        {#if visibleOverflowItems.length > 0}
            <DropdownMenu.Root bind:open={isOpen}>
                <DropdownMenu.Trigger disabled={allOverflowDisabled}>
                    {#snippet child({ props })}
                        <Button
                            {...props}
                            type="button"
                            size={dense ? 'icon-xs' : 'icon-sm'}
                            variant="ghost"
                            class="shrink-0 whitespace-nowrap {comfortable
                                ? 'min-h-11 min-w-11'
                                : ''}"
                            aria-label={triggerLabel}
                            disabled={allOverflowDisabled}
                        >
                            <Ellipsis class="size-4" />
                        </Button>
                    {/snippet}
                </DropdownMenu.Trigger>

                <DropdownMenu.Content
                    sideOffset={4}
                    align={align === 'left' ? 'start' : 'end'}
                    collisionPadding={8}
                    preventScroll={false}
                    class="min-w-48 whitespace-nowrap"
                >
                    <!-- Standard Operations -->
                    {#each standardItems as item, idx (idx)}
                        <DropdownMenu.Item
                            disabled={Boolean(item.disabled)}
                            onSelect={() => {
                                item.onclick?.();
                            }}
                        >
                            {#snippet child({ props })}
                                {#if item.href}
                                    <Link
                                        {...props}
                                        href={item.href}
                                        class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left font-medium whitespace-nowrap transition-colors"
                                        aria-disabled={Boolean(item.disabled)}
                                    >
                                        {#if item.icon}
                                            {@const Icon = item.icon}
                                            <Icon
                                                class="text-muted-foreground size-3.5 shrink-0"
                                            />
                                        {/if}
                                        <span class="truncate"
                                            >{item.label}</span
                                        >
                                    </Link>
                                {:else}
                                    <button
                                        {...props}
                                        type="button"
                                        class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                        disabled={Boolean(item.disabled)}
                                    >
                                        {#if item.icon}
                                            {@const Icon = item.icon}
                                            <Icon
                                                class="text-muted-foreground size-3.5 shrink-0"
                                            />
                                        {/if}
                                        <span class="truncate"
                                            >{item.label}</span
                                        >
                                    </button>
                                {/if}
                            {/snippet}
                        </DropdownMenu.Item>
                    {/each}

                    <!-- Destructive Actions (Safely Separated) -->
                    {#if destructiveItems.length > 0}
                        {#if standardItems.length > 0}
                            <DropdownMenu.Separator />
                        {/if}
                        {#each destructiveItems as item, idx (idx)}
                            <DropdownMenu.Item
                                disabled={Boolean(item.disabled)}
                                onSelect={() => {
                                    item.onclick?.();
                                }}
                            >
                                {#snippet child({ props })}
                                    {#if item.href}
                                        <Link
                                            {...props}
                                            href={item.href}
                                            class="text-destructive hover:bg-destructive/10 flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left font-medium whitespace-nowrap transition-colors"
                                            aria-disabled={Boolean(
                                                item.disabled,
                                            )}
                                        >
                                            {#if item.icon}
                                                {@const Icon = item.icon}
                                                <Icon
                                                    class="text-destructive size-3.5 shrink-0"
                                                />
                                            {/if}
                                            <span class="truncate"
                                                >{item.label}</span
                                            >
                                        </Link>
                                    {:else}
                                        <button
                                            {...props}
                                            type="button"
                                            class="text-destructive hover:bg-destructive/10 flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                            disabled={Boolean(item.disabled)}
                                        >
                                            {#if item.icon}
                                                {@const Icon = item.icon}
                                                <Icon
                                                    class="text-destructive size-3.5 shrink-0"
                                                />
                                            {/if}
                                            <span class="truncate"
                                                >{item.label}</span
                                            >
                                        </button>
                                    {/if}
                                {/snippet}
                            </DropdownMenu.Item>
                        {/each}
                    {/if}
                </DropdownMenu.Content>
            </DropdownMenu.Root>
        {/if}
    </div>
{/if}
