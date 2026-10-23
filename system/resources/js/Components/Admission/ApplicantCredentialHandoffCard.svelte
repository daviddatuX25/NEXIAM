<script>
    import QRCode from 'qrcode';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Badge } from '@/Components/ui/badge';
    import {
        Check,
        Clock,
        Copy,
        Eye,
        EyeOff,
        KeyRound,
        Mail,
        Printer,
        QrCode,
        ShieldCheck,
    } from 'lucide-svelte';
    import { markApplicantSubmitted } from '@/lib/applicant-status.js';

    const STORAGE_KEY = 'nexiam.applicant_access_key';

    let {
        accessKey = null,
        handoff = null,
        referenceCode = null,
        applicantName = null,
        birthdate = null,
        mode = 'staff', // 'staff' | 'applicant'
        onSaveDevice = null,
        savedToDevice = false,
    } = $props();

    let canvasEl = $state(null);
    let qrError = $state('');
    let keyVisible = $state(mode === 'staff');
    let copiedField = $state(null);
    let localSavedNotice = $state('');

    const resolvedAccessKey = $derived(accessKey ?? handoff?.access_key ?? null);
    const resolvedReferenceCode = $derived(
        referenceCode ?? handoff?.reference_code ?? null,
    );
    const resolvedApplicantName = $derived(
        applicantName ?? handoff?.applicant_name ?? null,
    );
    const resolvedBirthdate = $derived(birthdate ?? handoff?.birthdate ?? null);
    const qrUrl = $derived(
        handoff?.qr_url ??
            (resolvedAccessKey && typeof window !== 'undefined'
                ? `${window.location.origin}/applicant/login#k=${encodeURIComponent(resolvedAccessKey)}`
                : null),
    );
    const emailStatus = $derived(handoff?.email_status ?? null);
    const recipientEmail = $derived(handoff?.recipient_email ?? null);

    $effect(() => {
        if (canvasEl && qrUrl) {
            qrError = '';
            QRCode.toCanvas(canvasEl, qrUrl, { width: 184, margin: 2 }).catch(
                () => {
                    qrError = 'The login QR code could not be rendered.';
                },
            );
        }
    });

    async function copyValue(value, field) {
        if (!value) return;
        try {
            await navigator.clipboard.writeText(value);
            copiedField = field;
            setTimeout(() => {
                if (copiedField === field) copiedField = null;
            }, 2000);
        } catch {
            // ignore clipboard failures
        }
    }

    function handleSaveDevice() {
        if (typeof onSaveDevice === 'function') {
            onSaveDevice(resolvedAccessKey);
            return;
        }
        if (!resolvedAccessKey) {
            localSavedNotice =
                'No access key is available on this page. Contact the Registrar to request a key reissue.';
            return;
        }
        try {
            localStorage.setItem(STORAGE_KEY, resolvedAccessKey);
            markApplicantSubmitted();
            localSavedNotice =
                'Access key saved on this device. You will still need your birthdate to sign in.';
        } catch {
            localSavedNotice =
                'This browser blocked saving. Copy the key somewhere safe instead.';
        }
    }

    function printSlip() {
        window.print();
    }
</script>

{#if resolvedAccessKey}
    <div
        class="space-y-4 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 sm:p-5"
    >
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="space-y-1">
                <div
                    class="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                >
                    <ShieldCheck class="text-primary size-4" />
                    <span>
                        {mode === 'staff'
                            ? 'Applicant Credential Handoff'
                            : 'Your Applicant Portal Credentials'}
                    </span>
                </div>
                <p class="text-muted-foreground text-xs sm:text-sm">
                    {mode === 'staff'
                        ? 'Deliver this credential slip to the applicant. The QR code provides a 1-tap 24-hour login voucher and pre-fills their durable 8-character access key.'
                        : 'Shown once after submission. Scan the QR voucher on your phone for immediate sign-in, or save your 8-character access key to sign in anytime with your birthdate.'}
                </p>
            </div>
            <span
                class="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300"
            >
                <Clock class="size-3" />
                QR Voucher · 24h single-use
            </span>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
            <!-- Printable Slip with QR Canvas -->
            <div class="flex flex-col items-center md:col-span-5">
                <div
                    class="voucher-slip border-border/80 bg-card flex w-full flex-col items-center space-y-3 rounded-xl border p-4 text-center shadow-xs"
                >
                    <div
                        class="text-muted-foreground flex items-center gap-1.5 text-[11px] font-semibold tracking-wider uppercase"
                    >
                        <ShieldCheck class="text-primary size-3.5" />
                        <span>NEXIAM Applicant Slip</span>
                    </div>

                    {#if resolvedApplicantName}
                        <p class="text-foreground text-sm font-semibold">
                            {resolvedApplicantName}
                        </p>
                    {/if}

                    <div
                        class="border-border/60 rounded-lg border bg-white p-2 shadow-xs"
                    >
                        <canvas
                            bind:this={canvasEl}
                            class="size-44"
                            aria-label="Applicant login QR code"
                        ></canvas>
                    </div>
                    {#if qrError}
                        <p class="text-destructive text-xs">{qrError}</p>
                    {/if}

                    {#if resolvedReferenceCode}
                        <div class="w-full space-y-0.5">
                            <p
                                class="text-muted-foreground text-[10px] font-medium tracking-wider uppercase"
                            >
                                Reference Code
                            </p>
                            <p
                                class="font-mono text-sm font-bold tracking-wider"
                            >
                                {resolvedReferenceCode}
                            </p>
                        </div>
                    {/if}

                    <div class="w-full space-y-0.5">
                        <p
                            class="text-muted-foreground text-[10px] font-medium tracking-wider uppercase"
                        >
                            Durable Access Key
                        </p>
                        <p
                            class="text-primary font-mono text-lg font-bold tracking-[0.18em]"
                        >
                            {keyVisible ? resolvedAccessKey : '••••-••••'}
                        </p>
                    </div>

                    <div
                        class="border-border/50 text-muted-foreground w-full border-t pt-2 text-[11px]"
                    >
                        <p>
                            Scan QR within 24h for 1-tap login, or sign in at
                            <span class="font-mono">/applicant/login</span> with
                            Access Key + Birthdate.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 3 Delivery Channels -->
            <div class="flex flex-col justify-between space-y-3 md:col-span-7">
                <!-- Channel 1: Email Delivery -->
                <div
                    class="border-border/60 bg-card space-y-1.5 rounded-lg border p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                        >
                            <Mail class="text-primary size-3.5" />
                            Channel 1 · Email Delivery
                        </span>
                        {#if emailStatus === 'queued'}
                            <Badge variant="secondary" class="text-[11px]">
                                Dispatched
                            </Badge>
                        {:else if emailStatus === 'suppressed_practice'}
                            <Badge variant="outline" class="text-[11px]">
                                Suppressed (Practice)
                            </Badge>
                        {:else if emailStatus === 'failed'}
                            <Badge variant="destructive" class="text-[11px]">
                                Delivery Failed
                            </Badge>
                        {:else}
                            <Badge variant="outline" class="text-[11px]">
                                {recipientEmail ? 'Ready' : 'No Email on File'}
                            </Badge>
                        {/if}
                    </div>
                    <p class="text-muted-foreground text-xs">
                        {#if emailStatus === 'queued' && recipientEmail}
                            Credentials and a 24-hour one-tap sign-in link were
                            sent to
                            <span class="text-foreground font-mono font-medium"
                                >{recipientEmail}</span
                            >.
                        {:else if emailStatus === 'suppressed_practice'}
                            Practice workspace active — outbound email to
                            <span class="font-mono"
                                >{recipientEmail ?? 'recipient'}</span
                            > was safely suppressed.
                        {:else if emailStatus === 'failed'}
                            Email dispatch could not be completed. Use the QR
                            voucher slip or manual access key below.
                        {:else}
                            No applicant email address was provided. Use the
                            printed QR slip or manual access key below.
                        {/if}
                    </p>
                </div>

                <!-- Channel 2: Temporary QR Login Voucher -->
                <div
                    class="border-border/60 bg-card space-y-2 rounded-lg border p-3"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-0.5">
                            <span
                                class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                            >
                                <QrCode class="text-primary size-3.5" />
                                Channel 2 · Temporary QR Login Voucher (24h)
                            </span>
                            <p class="text-muted-foreground text-xs">
                                Scanning the QR code signs the applicant in
                                once without typing and saves the access key on
                                their phone.
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            {#if qrUrl}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="gap-1 text-xs"
                                    onclick={() => copyValue(qrUrl, 'qr')}
                                >
                                    {#if copiedField === 'qr'}
                                        <Check
                                            class="size-3.5 text-emerald-600"
                                        />
                                        Copied Link
                                    {:else}
                                        <Copy class="size-3.5" />
                                        Copy Link
                                    {/if}
                                </Button>
                            {/if}
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="gap-1 text-xs"
                                onclick={printSlip}
                            >
                                <Printer class="size-3.5" />
                                Print Slip
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Channel 3: Manual Access Key + Birthdate -->
                <div
                    class="border-border/60 bg-card space-y-2.5 rounded-lg border p-3"
                >
                    <div class="space-y-0.5">
                        <span
                            class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                        >
                            <KeyRound class="text-primary size-3.5" />
                            Channel 3 · Durable Access Key + Birthdate
                        </span>
                        <p class="text-muted-foreground text-xs">
                            Valid throughout the admission cycle with the
                            applicant’s birthdate{resolvedBirthdate
                                ? ` (${resolvedBirthdate})`
                                : ''} unless reissued by the Registrar.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <Input
                            data-tour="access-key-display"
                            type={keyVisible ? 'text' : 'password'}
                            value={resolvedAccessKey}
                            readonly
                            autocomplete="off"
                            class="min-h-10 max-w-[11rem] font-mono tracking-wider"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="min-h-10 gap-1"
                            onclick={() => (keyVisible = !keyVisible)}
                        >
                            {#if keyVisible}
                                <EyeOff class="size-3.5" />
                                Hide
                            {:else}
                                <Eye class="size-3.5" />
                                Show
                            {/if}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="min-h-10 gap-1"
                            onclick={() => copyValue(resolvedAccessKey, 'key')}
                        >
                            {#if copiedField === 'key'}
                                <Check class="size-3.5 text-emerald-600" />
                                Copied
                            {:else}
                                <Copy class="size-3.5" />
                                Copy
                            {/if}
                        </Button>
                        {#if mode === 'applicant' || typeof onSaveDevice === 'function'}
                            <Button
                                data-tour="access-key-save"
                                type="button"
                                variant="secondary"
                                size="sm"
                                class="min-h-10"
                                onclick={handleSaveDevice}
                            >
                                {savedToDevice
                                    ? 'Saved on this device'
                                    : 'Save on this device'}
                            </Button>
                        {/if}
                    </div>

                    {#if localSavedNotice}
                        <p class="text-muted-foreground text-xs" role="status">
                            {localSavedNotice}
                        </p>
                    {/if}
                </div>
            </div>
        </div>
    </div>
{/if}

<style>
    @media print {
        :global(body) {
            visibility: hidden !important;
            background: white !important;
        }
        :global(.voucher-slip),
        :global(.voucher-slip *) {
            visibility: visible !important;
        }
        :global(.voucher-slip) {
            position: fixed !important;
            left: 50% !important;
            top: 40px !important;
            transform: translateX(-50%) !important;
            width: 360px !important;
            border: 2px dashed #000 !important;
            padding: 24px !important;
            margin: 0 !important;
            background: white !important;
            color: black !important;
            box-shadow: none !important;
            z-index: 999999 !important;
        }
        :global(.voucher-slip canvas) {
            display: block !important;
            margin: 0 auto !important;
        }
    }
</style>
