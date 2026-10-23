<script>
    /* global route */
    /**
     * The optional purpose is honored only when it matches a destination in
     * the server projection. QR, reference and name use the same resolver.
     * Lookup never performs a domain mutation.
     */
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { Input } from '@/Components/ui/input';
    import {
        Dialog,
        DialogContent,
        DialogTitle,
        DialogDescription,
    } from '@/Components/ui/dialog';
    import { router, usePage } from '@inertiajs/svelte';
    import { ArrowRight, Camera, QrCode, Search, AlertCircle, RefreshCw, CheckCircle2 } from 'lucide-svelte';
    import { QrScanner } from '@/Components/qr';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import ApplicantLookupSearch from '@/Components/Admission/ApplicantLookupSearch.svelte';
    import { groupApplicantDestinations } from '@/lib/applicant-actions';
    import { classifyLoginQr } from '@/lib/login-qr-classifier.js';

    let {
        open = false,
        onOpenChange = () => {},
        preselect = null,
        general = false,
        canResolveApplicant = true,
    } = $props();

    const page = usePage();
    let stage = $state('idle');
    let message = $state('');
    let scannedAttempt = $state(null);
    let destinations = $state([]);
    let defaultDestination = $state(null);
    let inputMode = $state('camera');
    let code = $state('');
    let requestVersion = 0;
    let previousLookupContext = '';
    let voucherTarget = $state('');
    const generalEntry = $derived(general && !preselect);

    const resolveUrl =
        typeof route === 'function' ? route('staff.scan.resolve') : '/staff/scan/resolve';
    const lookupContextKey = $derived([
        page.props.auth?.user?.id ?? '',
        page.props.auth?.staff_inactivity_generation ?? '',
        page.props.practice_workspace?.id ?? page.props.practice_workspace?.key ?? '',
        JSON.stringify(page.props.auth?.user?.scope ?? {}),
    ].join(':'));

    function reset() {
        requestVersion++;
        stage = 'idle';
        scannedAttempt = null;
        destinations = [];
        defaultDestination = null;
        code = '';
        message = '';
        voucherTarget = '';
        inputMode = 'camera';
    }
    function onOpenChangeInternal(value) {
        if (value) reset();
        else requestVersion++;
        onOpenChange(value);
    }


    async function resolveCode(value = code) {
        const trimmed = String(value).trim();
        if (!trimmed || stage === 'resolving') return;
        const version = ++requestVersion;
        if (generalEntry || /^[a-z][a-z0-9+.-]*:|^\//i.test(trimmed)) {
            try {
                const target = classifyLoginQr(trimmed, window.location.href);
                if (target.kind === 'activation') {
                    if (!generalEntry) {
                        stage = 'invalid';
                        message = 'Use the general Scan QR code entry for a staff setup voucher.';
                        return;
                    }
                    voucherTarget = target.href;
                    code = '';
                    stage = 'voucher';
                    return;
                }
            } catch {
                stage = 'invalid';
                code = '';
                message = 'This QR is not a supported NEXIAM entry on this site.';
                return;
            }
        }
        if (!canResolveApplicant) {
            stage = 'invalid';
            code = '';
            message = 'Applicant lookup is not available for your account. Scan a staff setup voucher instead.';
            return;
        }
        stage = 'resolving';
        try {
            const { data } = await window.axios.post(resolveUrl, { code: trimmed });
            if (version !== requestVersion) return;
            scannedAttempt = data.scanned_attempt ?? null;
            destinations = Array.isArray(data.destinations) ? data.destinations : [];
            defaultDestination = data.default_destination ?? null;

            if (data.scan_result?.state === 'resolved' && scannedAttempt) {
                const requested = preselect?.key
                    ? destinations.find((destination) => destination.key === preselect.key)
                    : null;
                const authorizedDefault = defaultDestination
                    ? destinations.find((destination) => destination.key === defaultDestination.key)
                    : null;
                if (requested) {
                    router.visit(requested.url);
                    onOpenChange(false);
                    return;
                }
                if (!data.requires_choice && authorizedDefault) {
                    router.visit(authorizedDefault.url);
                    onOpenChange(false);
                    return;
                }
                stage = 'resolved';
                return;
            }

            stage = data.scan_result?.state === 'no_destination' ? 'no_destination' : 'invalid';
            message = data.scan_result?.message ?? 'Code could not be resolved.';
        } catch (error) {
            if (version !== requestVersion && error?.response?.status !== 419) return;
            if (error?.response?.status !== 419) {
                stage = 'invalid';
                message = error?.response?.data?.scan_result?.message ?? 'Code could not be resolved.';
            }
        } finally {
            if (version === requestVersion) code = '';
        }
    }

    function selectSearchResult(result) {
        code = result.reference_code;
        void resolveCode(result.reference_code);
    }

    function goTo(destination) {
        if (!destinations.some((authorized) => authorized.key === destination.key && authorized.url === destination.url)) return;
        onOpenChange(false);
        if (destination.kind === 'document') window.open(destination.url, '_blank', 'noopener,noreferrer');
        else router.visit(destination.url);
    }

    const groupedDestinations = $derived(groupApplicantDestinations(destinations));

    $effect(() => {
        const context = lookupContextKey;
        if (previousLookupContext && context !== previousLookupContext) reset();
        previousLookupContext = context;
    });
    $effect(() => {
        if (!open) {
            requestVersion++;
            voucherTarget = '';
            stage = 'idle';
        }
    });
</script>

<Dialog {open} onOpenChange={onOpenChangeInternal}>
    <DialogContent class="flex max-h-[92dvh] flex-col p-0 overflow-hidden sm:max-w-lg border border-border/80 shadow-2xl rounded-2xl">
        <!-- MODAL HEADER WITH TACTILE SEGMENTED CONTROLS -->
        <div class="border-b border-border/60 bg-muted/20 px-5 pt-5 pb-3">
            <div class="pr-8">
                <DialogTitle class="text-base font-bold tracking-tight text-foreground flex items-center gap-2">
                    <Camera class="size-4 text-primary shrink-0" />
                    <span>{preselect?.label ?? (generalEntry ? 'Scan QR code' : 'Scan Applicant QR')}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                    {preselect
                        ? `Scan an applicant's QR — this will ${preselect.label.toLowerCase()}.`
                        : generalEntry ? 'Scan an applicant pass or staff setup voucher. Scanning does not sign you out or redeem a voucher.' : 'Scan an applicant QR to choose an authorized action. Scanning never mutates.'}
                </DialogDescription>
            </div>

            {#if stage !== 'resolved' && stage !== 'voucher'}
                <div class="mt-3.5 grid {canResolveApplicant ? 'grid-cols-3' : 'grid-cols-1'} gap-1 rounded-xl bg-muted/70 p-1 border border-border/50 text-xs font-semibold select-none" role="tablist" aria-label="QR entry mode">
                    <button type="button" role="tab" aria-selected={inputMode === 'camera'} class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {inputMode === 'camera' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}" onclick={() => { inputMode = 'camera'; message = ''; }}>
                        <Camera class="size-3.5" /><span>Scan QR</span>
                    </button>
                    {#if canResolveApplicant}
                    <button type="button" role="tab" aria-selected={inputMode === 'manual'} class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {inputMode === 'manual' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}" onclick={() => { inputMode = 'manual'; message = ''; }}>
                        <QrCode class="size-3.5" /><span>Reference</span>
                    </button>
                    <button type="button" role="tab" aria-selected={inputMode === 'search'} class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {inputMode === 'search' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}" onclick={() => { inputMode = 'search'; message = ''; }}>
                        <Search class="size-3.5" /><span>Name</span>
                    </button>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- STABLE HEIGHT MODAL BODY (min-h-[380px] max-h-[520px]) -->
        <div class="relative flex-1 overflow-y-auto px-5 py-4 min-h-[380px] max-h-[520px]">
            {#if stage === 'voucher'}
                <div class="mx-auto w-full max-w-[440px] space-y-4 py-2">
                    <h3 class="text-base font-semibold">Staff setup voucher detected</h3>
                    <p class="text-sm text-muted-foreground">Redeeming this voucher will sign you out of the current account. Finish any unsaved work first. Opening setup does not redeem it; you must explicitly continue there.</p>
                    <a href={voucherTarget} class="inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground">Open voucher setup</a>
                    <Button type="button" variant="outline" onclick={reset}>Cancel voucher</Button>
                </div>
            {:else if stage === 'resolved'}
                <!-- ACTION CHOOSER VIEW: Server-authorized destinations for this staff member -->
                <div class="w-full max-w-[440px] mx-auto space-y-4 py-2">
                    <div class="flex items-center justify-between rounded-xl border border-border/60 bg-muted/30 p-3">
                        <div class="space-y-0.5">
                            <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Applicant resolved</p>
                            <p class="text-sm font-mono font-bold text-foreground">{scannedAttempt?.reference_code}</p>
                            <p class="text-sm text-muted-foreground">{scannedAttempt?.name}</p>
                        </div>
                        <Badge variant="outline" class="border-emerald-500/40 text-emerald-600 bg-emerald-500/10 font-bold text-xs gap-1">
                            <CheckCircle2 class="size-3" /> Resolved
                        </Badge>
                    </div>
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Choose an authorized destination:</p>
                        {#if destinations.length > 0}
                            {#each groupedDestinations as [group, links] (group)}
                                <section class="space-y-1.5">
                                    <h3 class="px-1 text-xs font-semibold text-muted-foreground">{group}</h3>
                                    {#each links as destination (destination.key + destination.url)}
                                        <button type="button" class="flex w-full items-center justify-between gap-3 rounded-xl border border-border/70 p-3.5 text-left text-sm font-semibold transition-colors hover:bg-muted/60 hover:border-primary/50" onclick={() => goTo(destination)}>
                                            <span>{destination.label}</span><ArrowRight class="size-4 text-muted-foreground shrink-0" />
                                        </button>
                                    {/each}
                                </section>
                            {/each}
                        {:else}
                            <div class="rounded-xl border border-dashed border-border p-4 text-center text-xs text-muted-foreground">No applicant destinations are available for your current duty or scope.</div>
                        {/if}
                    </div>
                    <PrintDocumentsSlot slotKey="applicant_qr_scan_modal" attemptIds={scannedAttempt?.id ? [scannedAttempt.id] : []} autoStart={true} />
                    <div class="pt-3 border-t border-border/40 flex items-center justify-between gap-2">
                        <Button type="button" variant="outline" size="sm" class="text-xs font-semibold gap-1.5" onclick={reset}>
                            <RefreshCw class="size-3" /> Scan another applicant
                        </Button>
                        <Button type="button" variant="ghost" size="sm" class="text-xs font-medium" onclick={() => onOpenChange(false)}>Done</Button>
                    </div>
                </div>
            {:else}
                <!-- SCANNING / INPUT VIEW -->
                {#if inputMode === 'camera'}
                    <div class="flex flex-col items-center space-y-3.5 w-full">
                        <div class="relative w-full max-w-[440px] mx-auto">
                            <!-- CRITICAL INVARIANT: Camera is inactive during resolving and resolved stages -->
                            <QrScanner
                                active={open && stage !== 'resolving' && stage !== 'resolved' && stage !== 'voucher'}
                                paused={stage === 'resolving' || stage === 'voucher'}
                                onScan={(scannedCode) => {
                                    code = scannedCode;
                                    resolveCode(scannedCode);
                                }}
                                class="w-full shadow-inner"
                            />
                            {#if stage === 'resolving'}
                                <div class="absolute inset-0 rounded-xl bg-background/85 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-30">
                                    <RefreshCw class="size-6 animate-spin text-primary" />
                                    <span class="text-xs font-semibold text-foreground">Resolving applicant QR…</span>
                                </div>
                            {/if}
                        </div>

                        {#if stage === 'invalid' || stage === 'no_destination'}
                            <div class="w-full max-w-[440px] mx-auto rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive flex items-start gap-2">
                                <AlertCircle class="size-4 shrink-0 mt-0.5" />
                                <span class="font-medium">{message || 'Applicant code could not be resolved.'}</span>
                            </div>
                        {/if}

                        <!-- SINGLE LOCATOR CODE ENTRY FALLBACK -->
                        {#if generalEntry}
                            <Button type="button" variant="outline" onclick={() => { requestVersion++; voucherTarget = '/staff/activate'; stage = 'voucher'; }}>Enter a staff voucher manually</Button>
                        {/if}
                        {#if canResolveApplicant}
                        <div class="w-full max-w-[440px] mx-auto rounded-xl border border-border/60 bg-muted/20 p-3 space-y-1.5">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground text-center sm:text-left">
                                Can't scan QR? Enter Applicant Number
                            </div>
                            <div class="flex gap-2">
                                <Input
                                    bind:value={code}
                                    placeholder="e.g. APP-2026-00005"
                                    class="h-9 text-xs font-mono uppercase"
                                    disabled={stage === 'resolving'}
                                    onkeydown={(e) => { if (e.key === 'Enter') resolveCode(); }}
                                />
                                <Button
                                    size="sm"
                                    class="h-9 px-3 text-xs font-semibold shrink-0 gap-1"
                                    disabled={!code.trim() || stage === 'resolving'}
                                    onclick={() => resolveCode()}
                                >
                                    {#if stage === 'resolving'}
                                        <RefreshCw class="size-3 animate-spin" />
                                    {:else}
                                        Resolve <ArrowRight class="size-3" />
                                    {/if}
                                </Button>
                            </div>
                        </div>
                        {/if}
                    </div>
                {:else if inputMode === 'search'}
                    <ApplicantLookupSearch onSelect={selectSearchResult} contextKey={lookupContextKey} />
                {:else}
                    <!-- DEDICATED MANUAL INPUT VIEW -->
                    <div class="w-full max-w-[440px] mx-auto space-y-4 py-2">
                        <div class="space-y-1.5">
                            <label for="manual-locator-entry" class="block text-xs font-semibold text-foreground">
                                Applicant Locator or Reference Code
                            </label>
                            <Input
                                id="manual-locator-entry"
                                bind:value={code}
                                placeholder="e.g. APP-2026-00005"
                                class="h-10 font-mono text-sm uppercase"
                                disabled={stage === 'resolving'}
                                onkeydown={(e) => { if (e.key === 'Enter') resolveCode(); }}
                            />
                            <p class="text-[11px] text-muted-foreground">
                                Type the full applicant reference number or locator code printed on their pass.
                            </p>
                        </div>

                        {#if stage === 'invalid' || stage === 'no_destination'}
                            <div class="rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive flex items-start gap-2">
                                <AlertCircle class="size-4 shrink-0 mt-0.5" />
                                <span class="font-medium">{message || 'Applicant code could not be resolved.'}</span>
                            </div>
                        {/if}

                        <Button
                            type="button"
                            onclick={() => resolveCode()}
                            disabled={!code.trim() || stage === 'resolving'}
                            class="w-full h-10 font-semibold gap-2"
                        >
                            {#if stage === 'resolving'}
                                <RefreshCw class="size-4 animate-spin" />
                                <span>Resolving applicant…</span>
                            {:else}
                                <Search class="size-4" />
                                <span>Resolve Applicant</span>
                            {/if}
                        </Button>
                    </div>
                {/if}
            {/if}
        </div>
    </DialogContent>
</Dialog>
