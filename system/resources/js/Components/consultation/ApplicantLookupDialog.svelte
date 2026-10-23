<script>
    import { tick } from 'svelte';
    import { router, Link } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Badge } from '@/Components/ui/badge';
    import {
        Dialog,
        DialogContent,
        DialogTitle,
        DialogDescription,
    } from '@/Components/ui/dialog';
    import {
        Camera,
        Search,
        ArrowRight,
        Clock,
        AlertCircle,
        X,
    } from 'lucide-svelte';
    import QrScanner from '@/Components/qr/QrScanner.svelte';

    /**
     * @typedef {Object} Props
     * @property {boolean} [open]
     * @property {(isOpen: boolean) => void} [onOpenChange]
     */

    let { open = false, onOpenChange = () => {} } = $props();

    // Mode: 'camera' (default) or 'manual'
    let lookupMode = $state('camera');

    // Manual search state
    let query = $state('');
    let results = $state([]);
    let loading = $state(false);
    let error = $state('');
    let searchInputEl = $state(null);

    // Camera & QR scanner state
    let manualCode = $state('');
    let resolvingCode = $state(false);
    let resolveError = $state('');

    // Reset lookup state on close
    $effect(() => {
        if (!open) {
            lookupMode = 'camera';
            query = '';
            results = [];
            error = '';
            resolveError = '';
            manualCode = '';
            resolvingCode = false;
        }
    });

    async function resolveScannedCode(codeString) {
        if (!codeString || resolvingCode) return;
        resolvingCode = true;
        resolveError = '';
        try {
            const { data } = await window.axios.post('/staff/scan/resolve', { code: codeString });
            if (data.scan_result?.state === 'resolved' && data.scanned_attempt?.id) {
                onOpenChange(false);
                router.visit(`/consultation/applicants/${data.scanned_attempt.id}`);
                return;
            }
            resolveError = data.scan_result?.message ?? 'Applicant code could not be resolved.';
        } catch {
            resolveError = 'Failed to verify applicant QR code.';
        } finally {
            resolvingCode = false;
        }
    }

    function handleManualCodeSubmit() {
        if (!manualCode.trim()) return;
        resolveScannedCode(manualCode.trim());
    }

    function switchToManual() {
        lookupMode = 'manual';
        tick().then(() => {
            searchInputEl?.focus();
        });
    }

    function returnToCamera() {
        lookupMode = 'camera';
        query = '';
        results = [];
        resolveError = '';
        manualCode = '';
    }

    // Manual debounce search effect
    $effect(() => {
        const search = query.trim();
        results = [];
        error = '';
        if (!open || lookupMode !== 'manual' || search.length < 2) {
            loading = false;
            return;
        }

        loading = true;
        const controller = new AbortController();
        const timeout = setTimeout(async () => {
            try {
                const response = await fetch(`/consultation/lookup?q=${encodeURIComponent(search)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error('Lookup unavailable');
                const data = await response.json();
                if (!Array.isArray(data.results)) throw new Error('Invalid lookup response');
                if (!controller.signal.aborted) results = data.results;
            } catch {
                if (!controller.signal.aborted) error = 'Applicant search is unavailable. Please try again.';
            } finally {
                if (!controller.signal.aborted) loading = false;
            }
        }, 250);

        return () => {
            clearTimeout(timeout);
            controller.abort();
        };
    });

    function selectApplicant(item) {
        if (!item.can_consult) return;
        onOpenChange(false);
        router.visit(item.url);
    }
</script>

<Dialog {open} {onOpenChange}>
    <DialogContent class="flex max-h-[92dvh] flex-col overflow-hidden p-0 sm:max-w-xl rounded-2xl border border-border/80 bg-background shadow-2xl">
        <!-- MODAL HEADER WITH INTEGRATED SEGMENTED CONTROL -->
        <div class="border-b border-border/60 bg-muted/20 px-5 pt-5 pb-3">
            <div class="pr-8">
                <DialogTitle class="text-base font-bold tracking-tight text-foreground flex items-center gap-2">
                    {#if lookupMode === 'camera'}
                        <Camera class="size-4 text-primary" />
                    {:else}
                        <Search class="size-4 text-primary" />
                    {/if}
                    <span>Examinee Lookup &amp; QR Scanner</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                    {#if lookupMode === 'camera'}
                        Scan physical result sheet QR code or enter candidate locator code.
                    {:else}
                        Search examinees by full name, surname, or application reference code.
                    {/if}
                </DialogDescription>
            </div>

            <!-- TACTILE SEGMENTED CONTROL BAR -->
            <div class="mt-3.5 grid grid-cols-2 gap-1 rounded-xl bg-muted/70 p-1 border border-border/50 text-xs font-semibold select-none" role="tablist" aria-label="Lookup mode">
                <button
                    type="button"
                    role="tab"
                    aria-selected={lookupMode === 'camera'}
                    class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {lookupMode === 'camera' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={returnToCamera}
                >
                    <Camera class="size-3.5" />
                    <span>Camera Scanner</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={lookupMode === 'manual'}
                    class="flex items-center justify-center gap-2 py-1.5 rounded-lg transition-all duration-200 {lookupMode === 'manual' ? 'bg-background text-foreground shadow-xs font-bold' : 'text-muted-foreground hover:text-foreground'}"
                    onclick={switchToManual}
                >
                    <Search class="size-3.5" />
                    <span>Manual Search</span>
                </button>
            </div>
        </div>

        <!-- STABLE HEIGHT MODAL BODY (Prevents jarring layout shift when toggling modes) -->
        <div class="relative flex-1 overflow-y-auto px-5 py-4 min-h-[380px] max-h-[520px]">
            {#if lookupMode === 'camera'}
                <!-- CAMERA SCANNER VIEW (Dead-center container) -->
                <div class="flex flex-col items-center space-y-3.5 w-full">
                    <div class="relative w-full max-w-[440px] mx-auto">
                        <QrScanner
                            active={open && lookupMode === 'camera'}
                            paused={resolvingCode}
                            onScan={resolveScannedCode}
                            class="w-full shadow-inner"
                        />
                        {#if resolvingCode}
                            <div class="absolute inset-0 rounded-xl bg-background/85 backdrop-blur-xs flex flex-col items-center justify-center gap-2 z-30">
                                <Clock class="size-6 animate-spin text-primary" />
                                <span class="text-xs font-semibold text-foreground">Resolving examinee QR…</span>
                            </div>
                        {/if}
                    </div>

                    {#if resolveError}
                        <div class="w-full max-w-[440px] mx-auto rounded-lg border border-destructive/40 bg-destructive/10 p-2.5 text-xs text-destructive flex items-start gap-2">
                            <AlertCircle class="size-4 shrink-0 mt-0.5" />
                            <span class="font-medium">{resolveError}</span>
                        </div>
                    {/if}

                    <!-- SINGLE LOCATOR CODE ENTRY (Fallback when hardware camera is unavailable or cannot scan) -->
                    <div class="w-full max-w-[440px] mx-auto rounded-xl border border-border/60 bg-muted/20 p-3 space-y-1.5">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground text-center sm:text-left">
                            Can't scan QR? Enter Applicant Number
                        </div>
                        <div class="flex gap-2">
                            <Input
                                bind:value={manualCode}
                                placeholder="e.g. APP-2026-00005"
                                class="h-9 text-xs font-mono"
                                onkeydown={(e) => { if (e.key === 'Enter') handleManualCodeSubmit(); }}
                            />
                            <Button
                                size="sm"
                                class="h-9 px-3 text-xs font-semibold shrink-0 gap-1"
                                disabled={!manualCode.trim() || resolvingCode}
                                onclick={handleManualCodeSubmit}
                            >
                                Resolve <ArrowRight class="size-3" />
                            </Button>
                        </div>
                    </div>
                </div>
            {:else}
                <!-- MANUAL SEARCH VIEW -->
                <div class="space-y-3">
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <Input
                            bind:ref={searchInputEl}
                            bind:value={query}
                            aria-label="Search accepted examinees"
                            placeholder="Type examinee name or reference code (e.g. Juan or APP-2026-00012)…"
                            class="h-10 pl-9 pr-9 text-xs font-medium"
                            onkeydown={(event) => {
                                if (event.key === 'Enter' && !loading && !error && results.length === 1 && results[0].can_consult) {
                                    event.preventDefault();
                                    selectApplicant(results[0]);
                                }
                            }}
                        />
                        {#if query}
                            <button
                                type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                onclick={() => { query = ''; results = []; }}
                                aria-label="Clear search input"
                            >
                                <X class="size-4" />
                            </button>
                        {/if}
                    </div>

                    <!-- SEARCH RESULTS SECTION -->
                    <div aria-live="polite" aria-busy={loading} class="space-y-2">
                        {#if loading}
                            <div class="py-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                                <Clock class="size-4 animate-spin text-primary" />
                                <span>Searching accepted examinees…</span>
                            </div>
                        {:else if error}
                            <div class="p-4 text-center space-y-2">
                                <p role="alert" class="text-xs text-destructive font-medium">{error}</p>
                                <Button size="sm" variant="outline" class="h-7 text-xs" onclick={switchToManual}>Retry search</Button>
                            </div>
                        {:else if query.trim().length < 2}
                            <div class="py-12 text-center text-xs text-muted-foreground">
                                Enter at least 2 characters to search examinees by name or reference code.
                            </div>
                        {:else if results.length === 0}
                            <div class="py-12 text-center text-xs text-muted-foreground space-y-1 rounded-xl border border-dashed">
                                <p class="font-medium text-foreground">No matching examinees found.</p>
                                <p>Check the spelling or search by reference code.</p>
                            </div>
                        {:else}
                            <ul class="divide-y rounded-xl border border-border/60 bg-card overflow-hidden">
                                {#each results as item (item.attempt_id)}
                                    <li class="p-3 space-y-2 hover:bg-muted/20 transition-colors">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-foreground">{item.name}</p>
                                                <p class="font-mono text-xs text-muted-foreground">{item.reference_code}</p>
                                            </div>
                                            {#if item.can_consult}
                                                <Button
                                                    size="sm"
                                                    class="min-h-8 px-3 text-xs font-semibold shrink-0 bg-primary text-primary-foreground gap-1"
                                                    onclick={() => selectApplicant(item)}
                                                >
                                                    Start Advising <ArrowRight class="size-3.5" />
                                                </Button>
                                            {:else}
                                                <Link href={item.url}>
                                                    <Button variant="outline" size="sm" class="min-h-8 px-3 text-xs font-semibold shrink-0">
                                                        View Record
                                                    </Button>
                                                </Link>
                                            {/if}
                                        </div>
                                        <div class="flex items-center justify-between gap-2 text-xs">
                                            <Badge variant={item.badge_variant} class="text-[10px]">
                                                {item.status_label}
                                            </Badge>
                                            {#if !item.can_consult}
                                                <span class="text-[10.5px] italic text-muted-foreground">
                                                    Prerequisite: result score or print required
                                                </span>
                                            {/if}
                                        </div>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </div>
                </div>
            {/if}
        </div>
    </DialogContent>
</Dialog>
