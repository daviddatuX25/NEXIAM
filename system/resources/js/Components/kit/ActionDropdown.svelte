<script>
    import { Button } from '@/Components/ui/button';
    import { EllipsisVertical } from 'lucide-svelte';
    import { router } from '@inertiajs/svelte';
    import { getContext } from 'svelte';
    import * as DropdownMenu from '@/Components/ui/dropdown-menu';

    /*
     * ActionDropdown shell: reusable row-level actions with a portaled menu.
     *
     * Item options:
     * - label: string (required)
     * - icon: Svelte component (optional)
     * - href: string (optional) - for GET navigation
     * - method: string (optional) - 'post', 'put', 'delete' for form submission
     * - danger: boolean (optional) - if true, shows in red
     * - onclick: function (optional) - custom handler
     * - disabled: boolean (optional)
     */

    let {
        items = [],
        triggerIcon: TriggerIcon = EllipsisVertical,
        align = 'right',
        confirmDelete = false,
        unrolled = false,
    } = $props();

    const inActionMenu = getContext('inActionMenu');
    const isUnrolled = $derived(unrolled || Boolean(inActionMenu));
</script>

{#if isUnrolled}
    <div class="w-full space-y-0.5" role="none">
        {#each items as item, idx (idx)}
            {#if item.method === 'delete'}
                <button
                    type="button"
                    class="hover:bg-destructive/10 text-destructive flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                        ''}"
                    onclick={() => {
                        if (
                            confirmDelete &&
                            !confirm(
                                `Are you sure you want to delete "${item.label}"? This action cannot be undone.`,
                            )
                        )
                            return;
                        if (item.onclick) item.onclick();
                        else if (item.href) router.delete(item.href);
                    }}
                    disabled={item.disabled}
                >
                    {#if item.icon}
                        <item.icon class="text-destructive h-4 w-4 shrink-0" />
                    {/if}
                    <span class="truncate">{item.label}</span>
                </button>
            {:else if item.method && item.method !== 'get' && item.method !== 'delete' && item.href}
                <form action={item.href} method="post" class="contents">
                    <input type="hidden" name="_method" value={item.method} />
                    <button
                        type="submit"
                        class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                            ''}"
                        onclick={() => {
                            if (item.onclick) item.onclick();
                        }}
                        disabled={item.disabled}
                    >
                        {#if item.icon}
                            <item.icon
                                class="text-muted-foreground h-4 w-4 shrink-0"
                            />
                        {/if}
                        <span class="truncate">{item.label}</span>
                    </button>
                </form>
            {:else if item.href && item.external}
                <a
                    href={item.href}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors {item.class ??
                        ''}"
                    onclick={() => {
                        if (item.onclick) item.onclick();
                    }}
                >
                    {#if item.icon}
                        <item.icon
                            class="text-muted-foreground h-4 w-4 shrink-0"
                        />
                    {/if}
                    <span class="truncate">{item.label}</span>
                    {#if item.badge !== undefined && item.badge !== null && item.badge !== '' && item.badge !== 0}
                        <span
                            class="bg-primary/10 text-primary ml-auto rounded-full px-1.5 py-0.5 font-mono text-xs font-semibold"
                            >{item.badge}</span
                        >
                    {/if}
                </a>
            {:else if item.href}
                <button
                    type="button"
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                        ''}"
                    onclick={() => {
                        if (item.onclick) item.onclick();
                        router.visit(item.href);
                    }}
                    disabled={item.disabled}
                >
                    {#if item.icon}
                        <item.icon
                            class="text-muted-foreground h-4 w-4 shrink-0"
                        />
                    {/if}
                    <span class="truncate">{item.label}</span>
                    {#if item.badge !== undefined && item.badge !== null && item.badge !== '' && item.badge !== 0}
                        <span
                            class="bg-primary/10 text-primary ml-auto rounded-full px-1.5 py-0.5 font-mono text-xs font-semibold"
                            >{item.badge}</span
                        >
                    {/if}
                </button>
            {:else if item.onclick}
                <button
                    type="button"
                    class="text-foreground hover:bg-muted flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                        ''}"
                    onclick={() => {
                        item.onclick();
                    }}
                    disabled={item.disabled}
                >
                    {#if item.icon}
                        <item.icon
                            class="text-muted-foreground h-4 w-4 shrink-0"
                        />
                    {/if}
                    <span class="truncate">{item.label}</span>
                </button>
            {/if}
        {/each}
    </div>
{:else}
    <div class="inline-block text-left">
        {#if items.length > 0}
            <DropdownMenu.Root>
                <DropdownMenu.Trigger>
                    {#snippet child({ props })}
                        <Button
                            {...props}
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            aria-label="More actions"
                        >
                            <TriggerIcon class="h-4 w-4" />
                        </Button>
                    {/snippet}
                </DropdownMenu.Trigger>

                <DropdownMenu.Content
                    sideOffset={4}
                    align={align === 'right' ? 'end' : 'start'}
                    collisionPadding={8}
                    preventScroll={false}
                    strategy="fixed"
                    class="w-48"
                >
                    {#each items as item, idx (idx)}
                        {#if item.method === 'delete'}
                            <DropdownMenu.Item
                                disabled={item.disabled}
                                onSelect={(event) => {
                                    if (
                                        confirmDelete &&
                                        !confirm(
                                            `Are you sure you want to delete "${item.label}"? This action cannot be undone.`,
                                        )
                                    ) {
                                        event.preventDefault();
                                        return;
                                    }

                                    if (item.onclick) {
                                        item.onclick();
                                    } else if (item.href) {
                                        router.delete(item.href);
                                    }
                                }}
                            >
                                {#snippet child({ props })}
                                    <button
                                        {...props}
                                        type="button"
                                        class="group hover:bg-accent flex w-full items-center px-3 py-2 text-left text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.danger
                                            ? 'text-destructive hover:text-destructive'
                                            : 'text-foreground'} {item.class ??
                                            ''}"
                                        disabled={item.disabled}
                                    >
                                        {#if item.icon}
                                            <item.icon
                                                class="mr-2.5 h-4 w-4 {item.danger
                                                    ? 'text-destructive'
                                                    : 'text-muted-foreground group-hover:text-foreground'}"
                                            />
                                        {/if}
                                        <span class="truncate"
                                            >{item.label}</span
                                        >
                                        {#if item.badge !== undefined && item.badge !== null && item.badge !== '' && item.badge !== 0}
                                            <span
                                                class="bg-primary/10 text-primary ml-auto rounded-full px-1.5 py-0.5 font-mono text-xs font-semibold"
                                                >{item.badge}</span
                                            >
                                        {/if}
                                    </button>
                                {/snippet}
                            </DropdownMenu.Item>
                        {:else if item.method && item.method !== 'get' && item.href}
                            <DropdownMenu.Item
                                disabled={item.disabled}
                                onSelect={() => item.onclick?.()}
                            >
                                {#snippet child({ props })}
                                    <form
                                        action={item.href}
                                        method="post"
                                        class="contents"
                                    >
                                        <input
                                            type="hidden"
                                            name="_method"
                                            value={item.method}
                                        />
                                        <button
                                            {...props}
                                            type="submit"
                                            class="group text-foreground hover:bg-accent flex w-full items-center px-3 py-2 text-left text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                                                ''}"
                                            disabled={item.disabled}
                                        >
                                            {#if item.icon}
                                                <item.icon
                                                    class="text-muted-foreground group-hover:text-foreground mr-2.5 h-4 w-4 transition-colors"
                                                />
                                            {/if}
                                            {item.label}
                                        </button>
                                    </form>
                                {/snippet}
                            </DropdownMenu.Item>
                        {:else if item.href && item.external}
                            <DropdownMenu.Item
                                onSelect={() => item.onclick?.()}
                            >
                                {#snippet child({ props })}
                                    <a
                                        {...props}
                                        href={item.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="group text-foreground hover:bg-accent flex w-full items-center px-3 py-2 text-left text-sm transition-colors {item.class ??
                                            ''}"
                                    >
                                        {#if item.icon}
                                            <item.icon
                                                class="text-muted-foreground group-hover:text-foreground mr-2.5 h-4 w-4"
                                            />
                                        {/if}
                                        <span class="truncate"
                                            >{item.label}</span
                                        >
                                        {#if item.badge !== undefined && item.badge !== null && item.badge !== '' && item.badge !== 0}
                                            <span
                                                class="bg-primary/10 text-primary ml-auto rounded-full px-1.5 py-0.5 font-mono text-xs font-semibold"
                                                >{item.badge}</span
                                            >
                                        {/if}
                                    </a>
                                {/snippet}
                            </DropdownMenu.Item>
                        {:else if item.href}
                            <DropdownMenu.Item
                                disabled={item.disabled}
                                onSelect={() => {
                                    if (item.onclick) {
                                        item.onclick();
                                    }
                                    router.visit(item.href);
                                }}
                            >
                                {#snippet child({ props })}
                                    <button
                                        {...props}
                                        type="button"
                                        class="group text-foreground hover:bg-accent flex w-full items-center px-3 py-2 text-left text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                                            ''}"
                                        disabled={item.disabled}
                                    >
                                        {#if item.icon}
                                            <item.icon
                                                class="text-muted-foreground group-hover:text-foreground mr-2.5 h-4 w-4"
                                            />
                                        {/if}
                                        <span class="truncate"
                                            >{item.label}</span
                                        >
                                        {#if item.badge !== undefined && item.badge !== null && item.badge !== '' && item.badge !== 0}
                                            <span
                                                class="bg-primary/10 text-primary ml-auto rounded-full px-1.5 py-0.5 font-mono text-xs font-semibold"
                                                >{item.badge}</span
                                            >
                                        {/if}
                                    </button>
                                {/snippet}
                            </DropdownMenu.Item>
                        {:else if item.onclick}
                            <DropdownMenu.Item
                                disabled={item.disabled}
                                onSelect={() => item.onclick()}
                            >
                                {#snippet child({ props })}
                                    <button
                                        {...props}
                                        type="button"
                                        class="group text-foreground hover:bg-accent flex w-full items-center px-3 py-2 text-left text-sm transition-colors disabled:cursor-not-allowed disabled:opacity-50 {item.class ??
                                            ''}"
                                        disabled={item.disabled}
                                    >
                                        {#if item.icon}
                                            <item.icon
                                                class="text-muted-foreground group-hover:text-foreground mr-2.5 h-4 w-4"
                                            />
                                        {/if}
                                        <span class="truncate"
                                            >{item.label}</span
                                        >
                                    </button>
                                {/snippet}
                            </DropdownMenu.Item>
                        {/if}
                    {/each}
                </DropdownMenu.Content>
            </DropdownMenu.Root>
        {/if}
    </div>
{/if}
