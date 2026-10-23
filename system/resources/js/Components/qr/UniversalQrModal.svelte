<script>
    import { onDestroy } from 'svelte';
    import { router } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Badge } from '@/Components/ui/badge';
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
    } from '@/Components/ui/dialog';
    import { Camera, Search, ArrowRight, AlertCircle, CheckCircle2, RefreshCw } from 'lucide-svelte';
    import QrScanner from './QrScanner.svelte';

    /**
     * @typedef {Object} Props
     * @property {boolean} [open]
     * @property {(isOpen: boolean) => void} [onOpenChange]
     * @property {string} [title] - Modal title.
     * @property {string} [description] - Modal subtitle description.
     * @property {'redirect' | 'callback'} [mode] - 'redirect' resolves via endpoint; 'callback' invokes onScan.
     * @property {string} [resolveUrl] - Endpoint for resolving QR codes in redirect mode.
     * @property {boolean} [continuous] - If true, does not close on scan (useful for rapid batch attendance).
     * @property {(code: string, context: { close: () => void, reset: () => void }) => Promise<void> | void} [onScan]
     * @property {(resolvedData: any) => void} [onResolved] - Callback after successful resolve.
     * @property {string} [manualPlaceholder] - Placeholder for manual code input.
     */

    let {
        open = false,
        onOpenChange = () => {},
        title = 'Scan QR Code',
        description = 'Point camera at QR code or enter code manually.',
        mode = 'redirect',
        resolveUrl = '/staff/scan/resolve',
        continuous = false,
        onScan = () => {},
        onResolved = () => {},
        manualPlaceholder = 'e.g. APP-2026-00012 or locator code',
    } = $props();

    // Mode: 'camera' | 'manual'
    let inputMode = $state('camera');
    let manualCode = $state('');
    let isProcessing = $state(false);
    let feedbackStatus = $state(null); // { type: 'success' | 'error', message: string } | null
    let destinationChoices = $state([]);
    let scannerPaused = $state(false);

    // Session and timer handles to prevent post-close navigation leaks
    let modalSession = 0;
    let redirectTimer = null;
    let feedbackTimer = null;

    function clearTimers() {
        if (redirectTimer !== null) {
            clearTimeout(redirectTimer);
            redirectTimer = null;
        }
        if (feedbackTimer !== null) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }
    }

    // Reset feedback and states whenever modal opens or closes
    $effect(() => {
        modalSession++;
        clearTimers();
        if (!open) {
            inputMode = 'camera';
            manualCode = '';
            isProcessing = false;
            feedbackStatus = null;
            scannerPaused = false;
            destinationChoices = [];
        }
    });

    onDestroy(() => {
        modalSession++;
        clearTimers();
    });
    function goToDestination(destination) {
        if (!destinationChoices.some((authorized) => authorized.key === destination.key && authorized.url === destination.url)) return;
        onOpenChange(false);
        if (destination.kind === 'document') window.open(destination.url, '_blank', 'noopener,noreferrer');
        else router.visit(destination.url);
    }

    function chooseAnotherCode() {
        destinationChoices = [];
        feedbackStatus = null;
        scannerPaused = false;
        isProcessing = false;
        manualCode = '';
    }

    /**
     * Handle raw code detected from camera or entered manually.
     * @param {string} rawCode
     */
    async function handleCode(rawCode) {
        const code = rawCode.trim();
        if (!code || isProcessing) return;

        const currentSession = modalSession;
        isProcessing = true;
        scannerPaused = true;
        feedbackStatus = null;
        clearTimers();

        const context = {
            close: () => onOpenChange(false),
            reset: () => {
                if (currentSession !== modalSession) return;
                isProcessing = false;
                scannerPaused = false;
                feedbackStatus = null;
                manualCode = '';
            },
        };

        if (mode === 'callback') {
            try {
                await onScan(code, context);
                if (!open || currentSession !== modalSession) return;

                feedbackStatus = { type: 'success', message: 'Code processed successfully.' };
                if (!continuous) {
                    redirectTimer = setTimeout(() => {
                        if (open && currentSession === modalSession) {
                            onOpenChange(false);
                        }
                    }, 500);
                } else {
                    feedbackTimer = setTimeout(() => {
                        if (open && currentSession === modalSession) {
                            feedbackStatus = null;
                            scannerPaused = false;
                            isProcessing = false;
                            manualCode = '';
                        }
                    }, 1200);
                }
            } catch (err) {
                if (!open || currentSession !== modalSession) return;
                feedbackStatus = { type: 'error', message: err.message || 'Failed to process QR code.' };
                scannerPaused = false;
                isProcessing = false;
            }
            return;
        }

        // Mode: 'redirect'
        try {
            const { data } = await window.axios.post(resolveUrl, { code });
            if (!open || currentSession !== modalSession) return;

            if (data.scan_result?.state === 'resolved') {
                onResolved(data);
                const destinations = Array.isArray(data.destinations) ? data.destinations : [];
                const authorizedDefault = data.default_destination
                    ? destinations.find((destination) =>
                        destination.key === data.default_destination.key
                        && destination.url === data.default_destination.url)
                    : null;

                if (data.requires_choice || !authorizedDefault) {
                    destinationChoices = destinations;
                    feedbackStatus = {
                        type: destinations.length > 0 ? 'success' : 'error',
                        message: destinations.length > 0
                            ? 'Applicant verified. Choose an authorized destination.'
                            : 'No applicant destination is available for this account.',
                    };
                    isProcessing = false;
                    scannerPaused = true;
                    return;
                }

                feedbackStatus = { type: 'success', message: 'Applicant verified.' };
                if (!continuous) {
                    redirectTimer = setTimeout(() => {
                        if (open && currentSession === modalSession) {
                            onOpenChange(false);
                            router.visit(authorizedDefault.url);
                        }
                    }, 350);
                } else {
                    feedbackTimer = setTimeout(() => {
                        if (open && currentSession === modalSession) {
                            feedbackStatus = null;
                            scannerPaused = false;
                            isProcessing = false;
                            manualCode = '';
                        }
                    }, 1500);
                }
                return;
            }

            feedbackStatus = {
                type: 'error',
                message: data.scan_result?.message ?? 'Locator code could not be resolved.',
            };
        } catch (err) {
            if (!open || currentSession !== modalSession) return;
            feedbackStatus = {
                type: 'error',
                message: err.response?.data?.message ?? err.message ?? 'Failed to verify QR code.',
            };
        } finally {
            if (open && currentSession === modalSession && feedbackStatus?.type === 'error') {
                feedbackTimer = setTimeout(() => {
                    if (open && currentSession === modalSession) {
                        scannerPaused = false;
                        isProcessing = false;
                    }
                }, 2000);
            }
        }
    }
</script>

<Dialog {open} {onOpenChange}>
    <DialogContent class="flex max-h-[92dvh] flex-col p-0 overflow-hidden sm:max-w-lg border border-border/80 shadow-2xl rounded-2xl">
        <!-- MODAL HEADER WITH TACTILE SEGMENTED CONTROL BAR -->
        <div class="border-b border-border/60 bg-muted/20 px-5 pt-5 pb-3">
            <div class="pr-8">
                <DialogTitle class="text-base font-bold tracking-tight text-foreground flex items-center gap-2">
                    <Camera class="size-4 text-primary shrink-0" />
                    <span>{title}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                    {description}
                </DialogDescription>
            </div>

            <!-- TACTILE SEGMENTED CONTROL BAR (Decoupled from top-right close button) -->
            <div class="mt-3.5 grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1 border border-border/50 text-xs font-semibold select-none" role="tablist" aria-label="Input mode">
                <button
                    type="button"
                    role="tab"
                    aria-selected={inputMode === 'camera'}
                    class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {inputMode === 'camera' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { inputMode = 'camera'; feedbackStatus = null; }}
                >
                    <Camera class="size-3.5" />
                    <span>Camera Scanner</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={inputMode === 'manual'}
                    class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {inputMode === 'manual' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => { inputMode = 'manual'; feedbackStatus = null; }}
                >
                    <Search class="size-3.5" />
                    <span>Manual Code</span>
                </button>
            </div>
        </div>

        <!-- STABLE HEIGHT MODAL BODY (Prevents layout shift when toggling between camera and manual) -->
        <div class="relative flex-1 overflow-y-auto px-5 py-4 min-h-[380px] max-h-[520px]">
            {#if destinationChoices.length > 0}
                <div class="mx-auto w-full max-w-[440px] space-y-3">
                    <h2 class="text-sm font-semibold text-foreground">Choose an authorized destination</h2>
                    {#each destinationChoices as destination (destination.key + destination.url)}
                        <button type="button" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-left text-sm font-medium hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={() => goToDestination(destination)}>
                            <span>{destination.label}</span><ArrowRight class="size-4 shrink-0 text-muted-foreground" />
                        </button>
                    {/each}
                    <Button type="button" variant="outline" class="w-full" onclick={chooseAnotherCode}>Scan another code</Button>
                </div>
            {:else if inputMode === 'camera'}
                <!-- CAMERA SCANNER VIEW (Dead-center container) -->
                <div class="flex flex-col items-center space-y-3.5 w-full">
                    <div class="relative w-full max-w-[440px] mx-auto">
                        <QrScanner
                            active={open && inputMode === 'camera'}
                            paused={scannerPaused}
                            onScan={handleCode}
                            class="w-full shadow-inner"
                        />
                        {#if isProcessing}
                            <div class="absolute inset-0 rounded-xl bg-background/85 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-30">
                                <RefreshCw class="size-6 animate-spin text-primary" />
                                <span class="text-xs font-semibold text-foreground">Verifying code…</span>
                            </div>
                        {/if}
                    </div>

                    <!-- Feedback Alert Banner -->
                    {#if feedbackStatus}
                        <div
                            class="w-full max-w-[440px] mx-auto rounded-lg border px-3 py-2 text-xs flex items-center gap-2 {feedbackStatus.type === 'success'
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'border-destructive/30 bg-destructive/10 text-destructive'}"
                        >
                            {#if feedbackStatus.type === 'success'}
                                <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                            {:else}
                                <AlertCircle class="size-4 shrink-0 text-destructive" />
                            {/if}
                            <span class="font-medium flex-1">{feedbackStatus.message}</span>
                        </div>
                    {/if}

                    <!-- SINGLE LOCATOR CODE ENTRY FALLBACK -->
                    <div class="w-full max-w-[440px] mx-auto rounded-xl border border-border/60 bg-muted/20 p-3 space-y-1.5">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground text-center sm:text-left">
                            Can't scan QR? Enter Locator Code
                        </div>
                        <div class="flex gap-2">
                            <Input
                                bind:value={manualCode}
                                placeholder={manualPlaceholder}
                                class="h-9 text-xs font-mono uppercase"
                                disabled={isProcessing}
                                onkeydown={(e) => {
                                    if (e.key === 'Enter') handleCode(manualCode);
                                }}
                            />
                            <Button
                                size="sm"
                                class="h-9 px-3 text-xs font-semibold shrink-0 gap-1"
                                disabled={!manualCode.trim() || isProcessing}
                                onclick={() => handleCode(manualCode)}
                            >
                                {#if isProcessing}
                                    <RefreshCw class="size-3 animate-spin" />
                                {:else}
                                    Verify <ArrowRight class="size-3" />
                                {/if}
                            </Button>
                        </div>
                    </div>
                </div>
            {:else}
                <!-- DEDICATED MANUAL INPUT VIEW -->
                <div class="w-full max-w-[440px] mx-auto space-y-4 py-2">
                    <div class="space-y-1.5">
                        <label for="manual-qr-entry" class="block text-xs font-semibold text-foreground">
                            Applicant Locator or Reference Code
                        </label>
                        <Input
                            id="manual-qr-entry"
                            bind:value={manualCode}
                            placeholder={manualPlaceholder}
                            class="h-10 font-mono text-sm uppercase"
                            disabled={isProcessing}
                            onkeydown={(e) => {
                                if (e.key === 'Enter') handleCode(manualCode);
                            }}
                        />
                        <p class="text-[11px] text-muted-foreground">
                            Type the full applicant reference number (e.g. APP-2026-00012) or signed pass locator code.
                        </p>
                    </div>

                    {#if feedbackStatus}
                        <div
                            class="rounded-lg border px-3 py-2 text-xs flex items-center gap-2 {feedbackStatus.type === 'success'
                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'border-destructive/30 bg-destructive/10 text-destructive'}"
                        >
                            {#if feedbackStatus.type === 'success'}
                                <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                            {:else}
                                <AlertCircle class="size-4 shrink-0 text-destructive" />
                            {/if}
                            <span class="font-medium flex-1">{feedbackStatus.message}</span>
                        </div>
                    {/if}

                    <Button
                        type="button"
                        onclick={() => handleCode(manualCode)}
                        disabled={!manualCode.trim() || isProcessing}
                        class="w-full h-10 font-semibold gap-2"
                    >
                        {#if isProcessing}
                            <RefreshCw class="size-4 animate-spin" />
                            <span>Verifying code…</span>
                        {:else}
                            <Search class="size-4" />
                            <span>Resolve Applicant</span>
                        {/if}
                    </Button>
                </div>
            {/if}
        </div>
    </DialogContent>
</Dialog>
