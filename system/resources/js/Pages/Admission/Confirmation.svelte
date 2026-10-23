<script>
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import { onMount } from 'svelte';
    import { markApplicantSubmitted } from '@/lib/applicant-status.js';
    import { router } from '@inertiajs/svelte';
    import QRCode from 'qrcode';
    import { Button } from '@/Components/ui/button';
    import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
    import {
        ArrowRight,
        Check,
        CheckCircle2,
        ChevronDown,
        Copy,
        Download,
        Eye,
        EyeOff,
        FileText,
        Folder,
        Mail,
        Printer,
        QrCode,
        ShieldCheck,
    } from 'lucide-svelte';
    import {
        downloadApplicantQrPdf,
        printApplicantQr,
    } from '@/lib/applicant-qr-print.js';

    const STORAGE_KEY = 'nexiam.applicant_access_key';

    let {
        reference_code = null,
        access_key = null,
        credential_handoff = null,
        intake_requirements = null,
        print_url = null,
        portal_entry_error = null,
    } = $props();
    const resolvedAccessKey = $derived(
        access_key ?? credential_handoff?.access_key ?? null,
    );

    let tourOpen = $state(false);
    let keyVisible = $state(false);
    let copiedField = $state(null);
    let showQrDrawer = $state(false);
    let canvasEl = $state(null);
    let qrError = $state('');
    let redeemingVoucher = $state(false);
    let deviceSavedNotice = $state('');
    let printing = $state(false);
    let printError = $state('');

    const resolvedReferenceCode = $derived(
        reference_code ?? credential_handoff?.reference_code ?? null,
    );
    const resolvedApplicantName = $derived(
        credential_handoff?.applicant_name ?? null,
    );
    const voucherToken = $derived(credential_handoff?.voucher_token ?? null);
    const qrUrl = $derived(
        credential_handoff?.qr_url ??
            (typeof window !== 'undefined'
                ? `${window.location.origin}/applicant/login${resolvedAccessKey ? `#k=${encodeURIComponent(resolvedAccessKey)}` : ''}`
                : null),
    );
    const recipientEmail = $derived(
        credential_handoff?.recipient_email ?? null,
    );

    const safetyContext = $derived.by(() => {
        const hasKey = Boolean(resolvedAccessKey);
        const hasRef = Boolean(resolvedReferenceCode);
        const safetyKey = `${hasKey ? 'key' : 'nokey'}:${hasRef ? 'ref' : 'noref'}`;

        return {
            entityKey: resolvedReferenceCode ?? 'confirmation',
            safetyKey,
            freshness: 'current',
            hasAccessKey: hasKey,
            hasReferenceCode: hasRef,
        };
    });

    $effect(() => {
        if (showQrDrawer && canvasEl && qrUrl) {
            qrError = '';
            QRCode.toCanvas(canvasEl, qrUrl, { width: 176, margin: 2 }).catch(
                () => {
                    qrError = 'The login QR code could not be rendered.';
                },
            );
        }
    });
    onMount(() => {
        markApplicantSubmitted();
    });


    async function copyToClipboard(value, field) {
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            copiedField = field;
            setTimeout(() => {
                if (copiedField === field) copiedField = null;
            }, 2000);
        } catch {
            // ignore clipboard permissions errors
        }
    }

    function saveOnDevice() {
        markApplicantSubmitted();
        if (!resolvedAccessKey) return;
        try {
            localStorage.setItem(STORAGE_KEY, resolvedAccessKey);
            deviceSavedNotice = 'Access key saved on this browser.';
            setTimeout(() => {
                deviceSavedNotice = '';
            }, 3000);
        } catch {
            deviceSavedNotice = 'Browser blocked local storage.';
        }
    }

    function enterPortalDirectly() {
        if (redeemingVoucher) return;
        redeemingVoucher = true;
        router.post(
            '/apply/confirmation/enter',
            {},
            {
                onFinish: () => {
                    redeemingVoucher = false;
                },
            },
        );
    }

    async function handlePrintSlip() {
        if (!print_url) return;
        if (printing) return;
        printing = true;
        printError = '';

        try {
            await printApplicantQr(print_url);
        } catch (error) {
            printError =
                error instanceof Error
                    ? error.message
                    : 'The admission slip could not be loaded. Please try again.';
        } finally {
            printing = false;
        }
    }

    function handleDownloadSlipPdf() {
        if (!print_url) return;
        printError = '';
        try {
            downloadApplicantQrPdf(print_url);
        } catch (error) {
            printError =
                error instanceof Error
                    ? error.message
                    : 'The admission slip PDF could not be downloaded.';
        }
    }
</script>

<svelte:head>
    <title>Application submitted · NEXIAM</title>
</svelte:head>

<GuestLayout maxWidth="max-w-2xl">
    <div class="mx-auto w-full space-y-6 px-4 py-8 sm:px-6 sm:py-12">
        <!-- Header Banner -->
        <div class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-3.5" />
                    Application Received
                </span>
                <TourTriggerGlyph
                    tourKey="applicant-access-key-safety"
                    label="Need help keeping your access key safe? Ask Kuya Xiam!"
                    onclick={() => (tourOpen = true)}
                />
            </div>
            <h1
                class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
            >
                Your Application is Submitted
            </h1>
            <p class="text-muted-foreground text-sm sm:text-base">
                {#if resolvedApplicantName}
                    Welcome, <strong class="text-foreground font-semibold"
                        >{resolvedApplicantName}</strong
                    >.
                {/if}
                Keep your Notice of Admission (NOA) number and Access Key to enter
                your portal.
            </p>
        </div>

        <!-- Primary Credentials Card -->
        <div
            class="bg-card space-y-5 rounded-2xl border border-emerald-500/30 p-5 shadow-sm sm:p-6 dark:border-emerald-500/20"
        >
            <!-- NOA Number -->
            <div
                class="border-border/60 bg-muted/30 flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase"
                    >
                        Notice of Admission (NOA) No.
                    </p>
                    <p
                        class="text-foreground mt-0.5 font-mono text-2xl font-extrabold tracking-wider sm:text-3xl"
                    >
                        {resolvedReferenceCode ?? 'Pending assignment'}
                    </p>
                </div>
                {#if resolvedReferenceCode}
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-10 shrink-0 gap-1.5 text-xs font-semibold"
                        onclick={() =>
                            copyToClipboard(resolvedReferenceCode, 'noa')}
                    >
                        {#if copiedField === 'noa'}
                            <Check class="size-4 text-emerald-600" />
                            Copied NOA
                        {:else}
                            <Copy class="size-4" />
                            Copy NOA No.
                        {/if}
                    </Button>
                {/if}
            </div>

            <!-- Access Key -->
            {#if resolvedAccessKey}
                <div
                    class="border-border/60 bg-muted/30 flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                    data-tour="access-key-display"
                >
                    <div>
                        <p
                            class="text-muted-foreground text-[11px] font-semibold tracking-wider uppercase"
                        >
                            Portal Access Key
                        </p>
                        <p
                            class="text-primary mt-0.5 font-mono text-xl font-bold tracking-widest sm:text-2xl"
                        >
                            {keyVisible ? resolvedAccessKey : '••••-••••'}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-10 gap-1.5 text-xs font-medium"
                            onclick={() => (keyVisible = !keyVisible)}
                        >
                            {#if keyVisible}
                                <EyeOff class="size-4" />
                                Hide
                            {:else}
                                <Eye class="size-4" />
                                Show
                            {/if}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-10 gap-1.5 text-xs font-medium"
                            onclick={() =>
                                copyToClipboard(resolvedAccessKey, 'key')}
                        >
                            {#if copiedField === 'key'}
                                <Check class="size-4 text-emerald-600" />
                                Copied
                            {:else}
                                <Copy class="size-4" />
                                Copy Key
                            {/if}
                        </Button>
                    </div>
                </div>
            {/if}

            <!-- Big Call To Action (Hero Button) -->
            <div class="space-y-2 pt-1" data-tour="access-key-sign-in">
                <Button
                    type="button"
                    class="min-h-12 w-full gap-2 rounded-xl bg-emerald-600 text-base font-bold text-white shadow-md hover:bg-emerald-500 sm:min-h-14 sm:text-lg dark:bg-emerald-500 dark:hover:bg-emerald-400"
                    onclick={enterPortalDirectly}
                    disabled={redeemingVoucher}
                >
                    {#if redeemingVoucher}
                        Opening applicant portal…
                    {:else}
                        Proceed to Applicant Portal
                        <ArrowRight class="size-5" />
                    {/if}
                </Button>
                {#if portal_entry_error}
                    <p class="text-destructive text-xs" role="alert">
                        {portal_entry_error}
                    </p>
                {/if}
                <div
                    class="text-muted-foreground flex items-center justify-between px-1 text-xs"
                >
                    <span
                        >Use this secure confirmation button to open your
                        applicant portal.</span
                    >
                    {#if resolvedAccessKey}
                        <button
                            type="button"
                            class="hover:text-foreground underline"
                            data-tour="access-key-save"
                            onclick={saveOnDevice}
                        >
                            {deviceSavedNotice || 'Save key on this device'}
                        </button>
                    {/if}
                </div>
            </div>

            <!-- Email Backup Reassurance -->
            {#if recipientEmail}
                <div
                    class="border-border/50 bg-background/80 text-muted-foreground flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 text-xs"
                >
                    <Mail class="text-primary size-4 shrink-0" />
                    <span>
                        We sent a copy of your NOA details to <strong
                            class="text-foreground font-mono"
                            >{recipientEmail}</strong
                        >.
                    </span>
                </div>
            {/if}
        </div>

        <!-- Collapsible QR Code & Printable Slip -->
        <div class="border-border/80 bg-card rounded-xl border p-4">
            <button
                type="button"
                class="text-foreground flex w-full items-center justify-between text-left text-sm font-semibold focus-visible:outline-none"
                onclick={() => (showQrDrawer = !showQrDrawer)}
                aria-expanded={showQrDrawer}
            >
                <span class="flex items-center gap-2">
                    <QrCode class="text-primary size-4" />
                    View QR Code & Printable Slip
                </span>
                <ChevronDown
                    class="text-muted-foreground size-4 transition-transform duration-200 {showQrDrawer
                        ? 'rotate-180'
                        : ''}"
                />
            </button>
            <p class="text-muted-foreground mt-1 text-xs">
                Need to open your portal on a computer or keep a printed
                physical slip?
            </p>

            {#if showQrDrawer}
                <div class="border-border/60 mt-4 space-y-4 border-t pt-4">
                    {#if print_url}
                        <div class="space-y-2">
                            <div
                                class="text-muted-foreground flex items-center justify-between px-1 text-xs"
                            >
                                <span class="text-foreground font-medium"
                                    >Official Admission Slip Document</span
                                >
                                <span>2 copies · Applicant & Office</span>
                            </div>
                            <div
                                class="border-border/80 overflow-hidden rounded-xl border bg-white shadow-xs"
                            >
                                <iframe
                                    src={`${print_url}${print_url.includes('?') ? '&' : '?'}embedded=1`}
                                    title="Official Admission Slip Document Preview"
                                    class="h-80 w-full border-0 bg-white"
                                    loading="lazy"
                                ></iframe>
                            </div>
                        </div>
                    {/if}

                    <div
                        class="printable-slip border-border/80 bg-card mx-auto flex w-full max-w-xs flex-col items-center rounded-xl border p-4 text-center shadow-xs"
                    >
                        <div
                            class="text-muted-foreground flex items-center gap-1.5 text-[11px] font-semibold tracking-wider uppercase"
                        >
                            <ShieldCheck class="text-primary size-3.5" />
                            <span>Quick Scan Login QR</span>
                        </div>
                        {#if resolvedApplicantName}
                            <p class="text-foreground mt-1 text-sm font-bold">
                                {resolvedApplicantName}
                            </p>
                        {/if}

                        <div
                            class="border-border/60 my-3 rounded-lg border bg-white p-2 shadow-xs"
                        >
                            <canvas
                                bind:this={canvasEl}
                                class="size-40"
                                aria-label="Applicant login QR code"
                            ></canvas>
                        </div>
                        {#if qrError}
                            <p class="text-destructive text-xs">{qrError}</p>
                        {/if}

                        <div class="w-full space-y-1 text-left text-xs">
                            <div
                                class="border-border/50 flex justify-between border-b py-1"
                            >
                                <span class="text-muted-foreground"
                                    >NOA No.:</span
                                >
                                <span
                                    class="text-foreground font-mono font-bold"
                                    >{resolvedReferenceCode}</span
                                >
                            </div>
                            <div class="flex justify-between py-1">
                                <span class="text-muted-foreground"
                                    >Access Key:</span
                                >
                                <span class="text-primary font-mono font-bold"
                                    >{resolvedAccessKey
                                        ? keyVisible
                                            ? resolvedAccessKey
                                            : '••••-••••'
                                        : 'Not available in this session'}</span
                                >
                            </div>
                        </div>

                        <p class="text-muted-foreground mt-2 text-[10px]">
                            {#if voucherToken}
                                Scan with a camera to use your one-time sign-in
                                link.
                            {:else if resolvedAccessKey}
                                Scan with a camera, then enter your birthdate to
                                sign in.
                            {:else}
                                Scan to open applicant sign-in. Enter your saved
                                access key and birthdate; this QR does not sign
                                you in automatically.
                            {/if}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-center gap-2 pt-2"
                    >
                        <Button
                            type="button"
                            variant="default"
                            size="sm"
                            class="gap-1.5 bg-emerald-600 text-xs text-white hover:bg-emerald-500"
                            onclick={handlePrintSlip}
                            disabled={printing || !print_url}
                        >
                            <Printer class="size-3.5" />
                            {printing
                                ? 'Preparing print…'
                                : 'Print Admission Slip'}
                        </Button>
                        {#if print_url}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                                onclick={handleDownloadSlipPdf}
                                disabled={printing}
                            >
                                <Download class="size-3.5" />
                                Download PDF
                            </Button>
                        {/if}
                        {#if qrUrl}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                                onclick={() => copyToClipboard(qrUrl, 'qr_url')}
                            >
                                {#if copiedField === 'qr_url'}
                                    <Check class="size-3.5 text-emerald-600" />
                                    Copied Link
                                {:else}
                                    <Copy class="size-3.5" />
                                    Copy Login Link
                                {/if}
                            </Button>
                        {/if}
                    </div>

                    {#if printError}
                        <p
                            class="text-destructive w-full text-center text-xs"
                            role="alert"
                        >
                            {printError}
                        </p>
                    {/if}
                </div>
            {/if}
        </div>

        <!-- What to Bring Next (Checklist) -->
        <section
            aria-labelledby="next-steps-heading"
            class="border-border/80 bg-card space-y-4 rounded-xl border p-5"
        >
            <div class="space-y-1">
                <h2
                    id="next-steps-heading"
                    class="text-foreground flex items-center gap-2 text-base font-semibold"
                >
                    <FileText class="text-primary size-4" />
                    What to Bring to Registrar Window 1
                </h2>
                <p class="text-muted-foreground text-xs">
                    Please prepare these physical documents for in-person
                    review:
                </p>
            </div>

            {#if Array.isArray(intake_requirements) && intake_requirements.length > 0}
                <ul
                    class="space-y-2"
                    aria-label="Items to prepare for Registrar review"
                >
                    {#each intake_requirements as requirement (requirement.label)}
                        <li
                            class="border-border/60 bg-muted/20 flex items-center justify-between gap-3 rounded-lg border px-3.5 py-2.5 text-xs sm:text-sm"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                {#if requirement.item_kind === 'container'}
                                    <Folder
                                        class="size-4 shrink-0 text-amber-500"
                                    />
                                {:else}
                                    <FileText
                                        class="text-primary size-4 shrink-0"
                                    />
                                {/if}
                                <span
                                    class="text-foreground truncate font-medium"
                                >
                                    {requirement.label}
                                </span>
                            </div>
                            <span
                                class="bg-muted text-muted-foreground shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                            >
                                {requirement.is_mandatory
                                    ? 'Required'
                                    : 'Optional'}
                            </span>
                        </li>
                    {/each}
                </ul>
            {:else}
                <p
                    class="border-border/60 bg-muted/20 text-muted-foreground rounded-lg border px-3 py-3 text-xs"
                >
                    Confirm current submission requirements with the Registrar
                    before your visit.
                </p>
            {/if}

            <p
                class="text-muted-foreground border-border/50 border-t pt-3 text-xs"
            >
                Your examination schedule will be posted in your portal after
                Registrar document verification.
            </p>
        </section>
    </div>

    <XiamNpcDialog
        tourKey="applicant-access-key-safety"
        context={safetyContext}
        bind:open={tourOpen}
    />
</GuestLayout>
