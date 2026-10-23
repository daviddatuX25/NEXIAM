<script>
    import { Badge } from '@/Components/ui/badge';
    import * as Card from '@/Components/ui/card';
    import { Radar, ShieldAlert } from 'lucide-svelte';

    let { model } = $props();
</script>

<Card.Root class="overflow-hidden border-border/70 shadow-sm">
    <Card.Header class="border-b bg-muted/20 pb-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <Card.Title class="text-base font-semibold truncate">{model.label ?? 'YuKee / K-Means compatibility'}</Card.Title>
                <Card.Description class="mt-1 text-xs">
                    {model.subtitle ?? 'Artifact-compatible cluster archetype and radar profile evidence.'}
                </Card.Description>
            </div>
            <Badge variant="secondary" class="shrink-0 text-xs">
                Standby · Model Verification
            </Badge>
        </div>
    </Card.Header>

    <Card.Content class="space-y-4 pt-4">
        {#if model.status === 'standby'}
            <div class="rounded-xl border border-dashed border-amber-500/30 bg-amber-500/[0.03] p-5 text-center">
                <Radar class="mx-auto size-7 text-amber-500/70" />
                <p class="mt-2 text-sm font-semibold text-foreground">The K-Means Radar Slot is on Formal Standby</p>
                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">
                    Awaiting the verified research model file (<code>.joblib</code>) and cluster parameter manifest.
                </p>
            </div>
        {:else}
            <!-- Evaluated YuKee profile if artifact is loaded in future -->
            <div class="rounded-lg border bg-muted/20 p-4 text-xs">
                <p class="font-medium text-foreground">Cluster: {model.cluster_name ?? 'Evaluated Profile'}</p>
            </div>
        {/if}

        <p class="text-xs leading-relaxed text-muted-foreground">{model.message}</p>
    </Card.Content>
</Card.Root>
