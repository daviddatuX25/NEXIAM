<script>
    /* global route */
    /**
     * Shared applicant-QR scan modal (founder 2026-08-15).
     *
     * One scanner for every surface. `preselect` gives the scan a PURPOSE on
     * the page it was opened from (e.g. consultation → open admission support)
     * so the flow skips the general action chooser; without it, a successful
     * scan shows the role-filtered action list. Scanning never mutates.
     */
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import * as Card from '@/Components/ui/card';
    import { Input } from '@/Components/ui/input';
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
        DialogFooter,
    } from '@/Components/ui/dialog';
    import { router } from '@inertiajs/svelte';
    import { ArrowRight, Camera, QrCode, ShieldCheck } from 'lucide-svelte';

    let {
        open = false,
        onOpenChange = () => {},
        preselect = null, // { key, label, urlFor(attemptId) } — page-specific purpose
    } = $props();

    let stage = $state('idle'); // idle | resolving | resolved | no_destination | invalid
    let message = $state('');
    let scannedAttempt = $state(null);
    let destinations = $state([]);

    const resolveUrl =
        typeof route === 'function' ? route('staff.scan.resolve') : '/staff/scan/resolve';

    let code = $state('');

    function reset() {
        stage = 'idle';
        message = '';
        scannedAttempt = null;
        destinations = [];
        code = '';
    }

    function onOpenChangeInternal(value) {
        if (value) reset();
        onOpenChange(value);
    }

    async function resolveCode() {
        const trimmed = code.trim();
        if (!trimmed) return;
        stage = 'resolving';
        try {
            const { data } = await window.axios.post(resolveUrl, { code: trimmed });
            scannedAttempt = data.scanned_attempt ?? null;
            destinations = data.destinations ?? [];

            if (data.scan_result?.state === 'resolved' && scannedAttempt) {
                if (preselect?.urlFor) {
                    // Page-specific purpose: go straight there. Navigate FIRST so
                    // the closing render can't race the visit.
                    router.visit(preselect.urlFor(scannedAttempt.id));
                    onOpenChange(false);
                    return;
                }
                stage = 'resolved';
                return;
            }

            stage = data.scan_result?.state === 'no_destination' ? 'no_destination' : 'invalid';
            message = data.scan_result?.message ?? 'Code could not be resolved.';
        } catch (error) {
            if (error?.response?.status !== 419) {
                stage = 'invalid';
                message = 'Code could not be resolved.';
            }
        } finally {
            code = '';
        }
    }

    function goTo(destination) {
        onOpenChange(false);
        router.visit(destination.url);
    }

    const resultVariant = {
        invalid: 'danger',
        no_destination: 'warning',
        resolved: 'muted',
    };
</script>

<Dialog open={open} onOpenChange={onOpenChangeInternal}>
    <DialogContent class="sm:max-w-md">
        <DialogHeader>
            <DialogTitle>{preselect?.label ?? 'Scan applicant QR'}</DialogTitle>
            <DialogDescription>
                {preselect
                    ? `Scan an applicant's QR — this will ${preselect.label.toLowerCase()}.`
                    : 'Scan a QR to choose what to do with that applicant. Scanning never performs an action.'}
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-4">
            <div class="flex gap-2">
                <div class="flex-1">
                    <Input
                        bind:value={code}
                        placeholder="Applicant locator code"
                        onkeydown={(event) => {
                            if (event.key === 'Enter') resolveCode();
                        }}
                    />
                </div>
                <Button type="button" onclick={resolveCode} disabled={!code.trim()}>
                    <QrCode class="size-4" />
                    Resolve
                </Button>
                <Button type="button" variant="outline" onclick={() => onOpenChange(false)}>
                    <Camera class="size-4" />
                    Camera
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Camera capture is available on device browsers. Manual code entry works everywhere.
            </p>

            {#if stage === 'resolved' && !preselect}
                <Card.Root>
                    <Card.Content class="space-y-2 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-medium">{scannedAttempt?.reference_code}</p>
                                <p class="text-xs text-muted-foreground">Choose an action — nothing runs automatically.</p>
                            </div>
                            <Badge variant={resultVariant[stage] ?? 'muted'}>resolved</Badge>
                        </div>
                        <div class="space-y-2 pt-1">
                            {#each destinations as d (d.key)}
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between gap-3 rounded-md border p-3 text-left text-sm font-medium hover:bg-muted/60"
                                    onclick={() => goTo(d)}
                                >
                                    <span>{d.label}</span>
                                    <ArrowRight class="size-4 text-muted-foreground" />
                                </button>
                            {/each}
                        </div>
                    </Card.Content>
                </Card.Root>
            {/if}

            {#if stage === 'invalid' || stage === 'no_destination'}
                <Card.Root>
                    <Card.Content class="p-3">
                        <Badge variant={resultVariant[stage] ?? 'muted'}>{stage}</Badge>
                        <p class="mt-2 text-sm text-muted-foreground">{message}</p>
                    </Card.Content>
                </Card.Root>
            {/if}

            <p class="flex items-start gap-2 text-xs text-muted-foreground">
                <ShieldCheck class="mt-0.5 size-3.5 shrink-0" />
                The QR contains no name, scores, action, or permission. Resolving verifies identity only.
            </p>
        </div>

        <DialogFooter>
            <Button variant="outline" onclick={() => onOpenChange(false)}>Close</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
