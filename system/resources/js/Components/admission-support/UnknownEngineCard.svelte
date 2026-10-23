<script>
    import { Badge } from '@/Components/ui/badge';
    import * as Card from '@/Components/ui/card';

    let { model } = $props();
</script>

<Card.Root class="overflow-hidden border-border/70 shadow-sm">
    <Card.Header class="border-b bg-muted/20 pb-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <Card.Title class="text-base font-semibold">{model.label ?? 'Program-fit constraints'}</Card.Title>
                <Card.Description class="mt-1 text-xs">{model.subtitle ?? 'Transparent rule profile for program-head constraints.'}</Card.Description>
            </div>
            <Badge variant="secondary" class="shrink-0 font-mono text-xs">Reserved slot</Badge>
        </div>
    </Card.Header>

    <Card.Content class="space-y-3 pt-4">
        <div
            class="relative flex h-36 items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30"
            role="img"
            aria-label="Reserved visualization slot with no provider output"
        >
            <div class="absolute inset-x-8 bottom-5 flex items-end justify-between gap-2 opacity-40" aria-hidden="true">
                <span class="h-8 w-2 rounded-full bg-primary"></span>
                <span class="h-16 w-2 rounded-full bg-primary"></span>
                <span class="h-11 w-2 rounded-full bg-primary"></span>
                <span class="h-20 w-2 rounded-full bg-primary"></span>
                <span class="h-14 w-2 rounded-full bg-primary"></span>
            </div>
            <div class="relative flex flex-col items-center gap-1 text-center">
                <Badge variant="outline" class="bg-background/90 font-mono text-[10px]">Preview fixture</Badge>
                <span class="text-xs font-medium">
                    {model.status === 'result_not_ready' ? 'Result not ready' : 'No provider configured'}
                </span>
            </div>
        </div>
        <p class="text-xs leading-relaxed text-muted-foreground">{model.message}</p>
    </Card.Content>
</Card.Root>
