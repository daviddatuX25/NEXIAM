<script>
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';

    let {
        mode = 'string',
        headingText = 'Institution Profile & Logo',
        title = 'Institution Profile & Logo',
        description = 'Official corporate identity',
        subtitle = 'Fallback subtitle description',
        code = 'SYN-INST-001',
    } = $props();
</script>

{#if mode === 'snippet'}
    <PageHeaderBar>
        {#snippet title()}
            <div class="flex flex-wrap items-center gap-3">
                <h1
                    class="text-foreground text-xl font-bold tracking-tight sm:text-2xl sm:text-3xl"
                >
                    {headingText}
                </h1>
                <Badge
                    variant="outline"
                    class="font-mono text-xs font-semibold"
                >
                    {code}
                </Badge>
            </div>
        {/snippet}

        {#snippet subtitle()}
            <p
                class="text-muted-foreground mt-0.5 text-xs sm:text-sm"
                data-testid="snippet-subtitle"
            >
                {description}
            </p>
        {/snippet}

        {#snippet actions()}
            <button type="button" data-testid="save-btn">Save Changes</button>
        {/snippet}
    </PageHeaderBar>
{:else if mode === 'subtitle_string'}
    <PageHeaderBar {title} {subtitle} />
{:else if mode === 'actions_content'}
    <PageHeaderBar {title}>
        {#snippet actions_content()}
            <button type="button" data-testid="actions-content-btn"
                >Back to Test Kits</button
            >
        {/snippet}
    </PageHeaderBar>
{:else}
    <PageHeaderBar {title} {description}>
        {#snippet actions()}
            <button type="button" data-testid="default-action-btn"
                >Action</button
            >
        {/snippet}
    </PageHeaderBar>
{/if}
