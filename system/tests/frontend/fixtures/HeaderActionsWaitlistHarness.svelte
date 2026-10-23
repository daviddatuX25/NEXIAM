<script>
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { Users, Plus, Sparkles } from 'lucide-svelte';

    let { waitlistCount = 0 } = $props();
</script>

<HeaderActions>
    {#snippet primaryAction()}
        <a href="/exams/sessions/create">
            <Button>
                <Plus class="size-3.5" />
                <span>Create session</span>
            </Button>
        </a>
    {/snippet}

    {#snippet alertAction()}
        {#if waitlistCount > 0}
            <a href="/exams/sessions/waitlist" data-testid="waitlist-alert-button">
                <Button variant="outline">
                    <Users class="size-3.5" />
                    <span>Waitlist</span>
                    <Badge>{waitlistCount}</Badge>
                </Button>
            </a>
        {/if}
    {/snippet}

    {#snippet secondaryActions()}
        <Button variant="outline">
            <Sparkles class="size-3.5" />
            <span>Auto-Distribute</span>
        </Button>
        {#if waitlistCount === 0}
            <div data-testid="overflow-dropdown">
                <span>Overflow Menu</span>
            </div>
        {/if}
    {/snippet}
</HeaderActions>
