<script>
    import * as Dialog from '@/Components/ui/dialog';
    import { Button } from '@/Components/ui/button';
    import QRCode from 'qrcode';
    import {
        AlertTriangle,
        Check,
        Clock,
        Copy,
        Download,
        Mail,
        Printer,
        RefreshCw,
        ShieldCheck,
        X,
    } from 'lucide-svelte';
    let {
        open = false,
        user = null,
        onOpenChange = () => {},
        initialPurpose = 'activation',
        canIssueNonExpiring = false,
    } = $props();

    let selectedPurpose = $state('activation');
    let selectedMode = $state('standard_24h');
    let customExpiry = $state('');
    let voucher = $state(null);
    let busy = $state(false);
    let error = $state('');
    let priorVoucherExists = $state(false);
    let canvasEl = $state(null);
    let iframeEl = $state(null);
    let printFrameUrl = $state(null);
    let requestedUserId = $state(null);
    let confirmingReissue = $state(false);
    let emailSent = $state(false);
    let copiedType = $state(null);
    let currentTime = $state(Date.now());

    $effect(() => {
        if (!voucher) return;
        const timer = setInterval(() => (currentTime = Date.now()), 1000);
        return () => clearInterval(timer);
    });

    const secondsRemaining = $derived(
        voucher?.expires_at
            ? Math.max(
                  0,
                  Math.floor(
                      (new Date(voucher.expires_at).getTime() - currentTime) /
                          1000,
                  ),
              )
            : 0,
    );

    const expiryCountdown = $derived(
        voucher?.expires_at
            ? `${String(Math.floor(secondsRemaining / 3600)).padStart(2, '0')}:${String(
                  Math.floor((secondsRemaining % 3600) / 60),
              ).padStart(
                  2,
                  '0',
              )}:${String(secondsRemaining % 60).padStart(2, '0')}`
            : 'No calendar expiry',
    );
    $effect(() => {
        if (open && user && requestedUserId !== user.id) {
            requestedUserId = user.id;
            selectedPurpose = initialPurpose;
            selectedMode = 'standard_24h';
            voucher = null;
            error = '';
            customExpiry = '';
            priorVoucherExists = false;
        }
    });

    $effect(() => {
        if (canvasEl && voucher?.qr_url) {
            QRCode.toCanvas(canvasEl, voucher.qr_url, {
                width: 220,
                margin: 2,
            }).catch(() => {
                error = 'The voucher QR code could not be rendered.';
            });
        }
    });

    async function copyText(text, type) {
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            copiedType = type;
            setTimeout(() => {
                if (copiedType === type) copiedType = null;
            }, 2000);
        } catch {
            error =
                'Clipboard access is unavailable. Select and copy the voucher details manually.';
        }
    }

    async function send(url, payload = { purpose: selectedPurpose }) {
        busy = true;
        error = '';
        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                },
                body: JSON.stringify(payload),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(data.message ?? 'The voucher request failed.');
            return data;
        } catch (caught) {
            error = caught.message ?? 'The voucher request failed.';
            priorVoucherExists = /prior voucher exists/i.test(error);
            return null;
        } finally {
            busy = false;
        }
    }
    function normalizeMode() {
        if (
            selectedPurpose !== 'activation' &&
            !['standard_24h', 'extended_7d'].includes(selectedMode)
        ) {
            selectedMode = 'standard_24h';
            customExpiry = '';
        }
    }

    async function issue() {
        if (!user || busy) return;
        voucher = await send(`/staff/personnel/${user.id}/vouchers`, {
            purpose: selectedPurpose,
            mode: selectedMode,
            ...(selectedMode === 'custom'
                ? { custom_expiry: customExpiry }
                : {}),
            send_email: false,
        });
    }
    async function replaceExistingVoucher() {
        if (
            !user ||
            !window.confirm(
                'Replace the previous voucher? Its QR code, fallback code, and pending setup attempt will be invalidated.',
            )
        )
            return;
        const replacement = await send(
            `/staff/personnel/${user.id}/vouchers/reissue`,
            {
                purpose: selectedPurpose,
                mode: selectedMode,
                ...(selectedMode === 'custom'
                    ? { custom_expiry: customExpiry }
                    : {}),
                confirm_replace: true,
                send_email: false,
            },
        );
        if (replacement) {
            voucher = replacement;
            priorVoucherExists = false;
        }
    }

    async function reissue() {
        if (!user) return;
        if (selectedMode === 'custom' && !customExpiry) {
            error = 'Choose a future institution-local expiry date and time.';
            return;
        }
        if (!confirmingReissue) {
            confirmingReissue = true;
            return;
        }
        confirmingReissue = false;
        emailSent = false;
        voucher = await send(`/staff/personnel/${user.id}/vouchers/reissue`, {
            purpose: selectedPurpose,
            mode: selectedMode,
            ...(selectedMode === 'custom'
                ? { custom_expiry: customExpiry }
                : {}),
            confirm_replace: true,
            send_email: false,
        });
    }

    async function sendActivationEmail() {
        if (!user) return;
        if (selectedMode === 'custom' && !customExpiry) {
            error = 'Choose a future institution-local expiry date and time.';
            return;
        }
        if (!confirmingReissue) {
            confirmingReissue = true;
            return;
        }
        const replacement = await send(
            `/staff/personnel/${user.id}/vouchers/reissue`,
            {
                purpose: selectedPurpose,
                mode: selectedMode,
                ...(selectedMode === 'custom'
                    ? { custom_expiry: customExpiry }
                    : {}),
                confirm_replace: true,
                send_email: true,
            },
        );
        if (replacement) {
            voucher = replacement;
            emailSent = replacement.email_queued === true;
            confirmingReissue = false;
        }
    }

    async function printSlip() {
        if (!voucher || busy) return;
        const expectedUserId = user?.id ?? null;
        const expectedRef = voucher.public_reference;
        busy = true;
        error = '';
        try {
            const response = await fetch(voucher.print_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/html',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                },
                body: JSON.stringify({
                    reference: voucher.public_reference,
                    token: voucher.token,
                    fallback: voucher.fallback,
                    hash: voucher.print_hash,
                    embedded: true,
                }),
            });
            if (!response.ok)
                throw new Error(
                    'The voucher print preview could not be generated.',
                );
            const blob = await response.blob();
            if (
                !open ||
                (user?.id ?? null) !== expectedUserId ||
                voucher?.public_reference !== expectedRef
            ) {
                return;
            }
            const nextUrl = URL.createObjectURL(blob);
            if (printFrameUrl) URL.revokeObjectURL(printFrameUrl);
            printFrameUrl = nextUrl;
        } catch (caught) {
            error =
                caught.message ??
                'The voucher print preview could not be generated.';
        } finally {
            busy = false;
        }
    }

    async function downloadPdf() {
        if (!voucher || busy) return;
        busy = true;
        error = '';
        try {
            const response = await fetch(voucher.print_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/pdf',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                },
                body: JSON.stringify({
                    reference: voucher.public_reference,
                    token: voucher.token,
                    fallback: voucher.fallback,
                    hash: voucher.print_hash,
                    format: 'pdf',
                }),
            });
            if (!response.ok)
                throw new Error('The voucher PDF could not be generated.');
            const objectUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = `NEXIAM-Staff-Access-Voucher-${voucher.public_reference}.pdf`;
            link.click();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 60000);
        } catch (caught) {
            error = caught.message ?? 'The voucher PDF could not be generated.';
        } finally {
            busy = false;
        }
    }

    function handlePrintFrameLoad() {
        iframeEl?.contentWindow?.focus();
        iframeEl?.contentWindow?.print();
    }
    function close() {
        voucher = null;
        selectedMode = 'standard_24h';
        requestedUserId = null;
        confirmingReissue = false;
        emailSent = false;
        error = '';
        copiedType = null;
        if (printFrameUrl) {
            URL.revokeObjectURL(printFrameUrl);
            printFrameUrl = null;
        }
        onOpenChange(false);
    }
</script>

<Dialog.Root
    {open}
    onOpenChange={(value) => (value ? onOpenChange(true) : close())}
>
    <Dialog.Portal>
        <Dialog.Overlay />
        <Dialog.Content
            class="border-border/80 max-h-[92vh] overflow-y-auto border p-6 shadow-2xl sm:max-w-3xl"
        >
            <Dialog.Header class="border-border/60 border-b pb-3">
                <div class="flex items-center justify-between">
                    <div
                        class="text-muted-foreground flex items-center gap-2 text-xs font-semibold tracking-wider uppercase"
                    >
                        <ShieldCheck class="text-primary size-4" />
                        <span>Staff Access Issuance</span>
                    </div>
                    {#if voucher?.expires_at}
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                        >
                            <Clock class="size-3" />
                            Single-use · {expiryCountdown}
                        </span>
                    {:else if voucher}
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                        >
                            <ShieldCheck class="size-3" />
                            No calendar expiry · Single-use
                        </span>
                    {:else}
                        <span
                            class="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
                        >
                            <ShieldCheck class="size-3" />
                            Configurable validity
                        </span>
                    {/if}
                </div>
                <Dialog.Title class="text-xl font-bold sm:text-2xl">
                    {voucher
                        ? 'Staff Access Voucher'
                        : selectedPurpose === 'mfa_reset'
                          ? 'Two-Factor Device Reset Voucher'
                          : selectedPurpose === 'password_reset'
                            ? 'Password Reset Voucher'
                            : 'Staff Activation Voucher'}
                </Dialog.Title>
                <Dialog.Description class="text-muted-foreground text-sm">
                    {#if selectedPurpose === 'mfa_reset'}
                        Issue an authenticator reset credential for <span
                            class="text-foreground font-medium"
                            >{user?.name ?? 'Staff member'}</span
                        >
                        ({user?.email ?? 'No email on record'}).
                    {:else if selectedPurpose === 'password_reset'}
                        Issue a password reset credential for <span
                            class="text-foreground font-medium"
                            >{user?.name ?? 'Staff member'}</span
                        >
                        ({user?.email ?? 'No email on record'}).
                    {:else}
                        Issue a secure, zero-password onboarding credential for <span
                            class="text-foreground font-medium"
                            >{user?.name ?? 'Staff member'}</span
                        >
                        ({user?.email ?? 'No email on record'}).
                    {/if}
                </Dialog.Description>
            </Dialog.Header>
            {#if error}
                <div
                    role="alert"
                    class="bg-destructive/10 text-destructive mt-4 rounded-lg p-3.5 text-sm"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4 shrink-0" /><span
                            >{error}</span
                        >
                    </div>
                    {#if priorVoucherExists}
                        <Button
                            type="button"
                            variant="destructive"
                            class="mt-3"
                            onclick={replaceExistingVoucher}
                            disabled={busy}>Confirm replacement</Button
                        >
                    {/if}
                </div>
            {/if}

            {#if !voucher && !busy}
                <div
                    class="border-border/60 bg-muted/20 mt-4 space-y-3 rounded-lg border p-4"
                >
                    <div class="space-y-2">
                        <label for="voucher-purpose" class="text-sm font-medium"
                            >Voucher purpose</label
                        >
                        <select
                            id="voucher-purpose"
                            bind:value={selectedPurpose}
                            onchange={normalizeMode}
                            class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            disabled={busy}
                        >
                            <option value="activation"
                                >Account Activation</option
                            >
                            <option value="password_reset"
                                >Password Reset</option
                            >
                            <option value="mfa_reset"
                                >Two-Factor Device Reset</option
                            >
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label for="voucher-mode" class="text-sm font-medium"
                            >Validity mode</label
                        >
                        <select
                            id="voucher-mode"
                            bind:value={selectedMode}
                            class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            disabled={busy}
                        >
                            <option value="standard_24h"
                                >Standard · 24 Hours</option
                            >
                            <option value="extended_7d"
                                >Extended · 7 Days</option
                            >
                            {#if selectedPurpose === 'activation'}
                                <option value="extended_30d">30 Days</option>
                                <option value="extended_90d">90 Days</option>
                                <option value="extended_180d"
                                    >180 Days · Recommended</option
                                >
                                <option value="custom"
                                    >Custom expiry date and time</option
                                >
                            {/if}
                            {#if selectedPurpose === 'activation' && canIssueNonExpiring}
                                <option value="never_expires_single_use"
                                    >No calendar expiry · Single-use onboarding
                                    slip</option
                                >
                            {/if}
                        </select>
                        {#if selectedMode === 'custom'}
                            <label
                                for="voucher-custom-expiry"
                                class="block text-xs font-medium"
                                >Expires at (institution time)</label
                            >
                            <input
                                id="voucher-custom-expiry"
                                type="datetime-local"
                                bind:value={customExpiry}
                                required
                                class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                            />
                        {/if}
                        {#if selectedMode === 'never_expires_single_use'}
                            <p class="text-muted-foreground text-xs">
                                Non-expiring vouchers remain valid indefinitely
                                until first redeemed or revoked. Restricted to
                                initial account activation.
                            </p>
                        {/if}
                    </div>
                    {#if selectedPurpose === 'mfa_reset'}
                        <p
                            class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                            role="note"
                        >
                            This voucher will reset the user's authenticator app
                            and allow re-enrolling on a new device.
                        </p>
                    {/if}
                    <Button
                        type="button"
                        onclick={issue}
                        disabled={busy ||
                            (selectedMode === 'custom' && !customExpiry)}
                        class="w-full"
                    >
                        {busy ? 'Generating…' : 'Generate Voucher'}
                    </Button>
                </div>
            {/if}
            {#if busy && !voucher}
                <div class="space-y-3 py-16 text-center" aria-live="polite">
                    <div
                        class="border-primary mx-auto size-8 animate-spin rounded-full border-2 border-r-transparent"
                    ></div>
                    <p class="text-muted-foreground text-sm">
                        Generating cryptographic voucher &amp; QR code…
                    </p>
                </div>
            {:else if voucher}
                <div class="my-4 grid grid-cols-1 gap-6 md:grid-cols-12">
                    <!-- Left Column: The Printable Voucher Slip (5 of 12 cols on desktop) -->
                    <div class="flex flex-col items-center md:col-span-5">
                        <div
                            class="voucher-slip border-border/80 bg-card flex w-full flex-col items-center space-y-3.5 rounded-xl border p-5 text-center shadow-xs"
                        >
                            <div
                                class="text-muted-foreground flex items-center gap-1.5 text-xs font-semibold tracking-wider uppercase"
                            >
                                <ShieldCheck class="text-primary size-3.5" />
                                <span>NEXIAM Staff Slip</span>
                            </div>

                            <!-- QR Code Frame -->
                            <div
                                class="border-border/60 rounded-lg border bg-white p-2 shadow-xs"
                            >
                                <canvas
                                    bind:this={canvasEl}
                                    class="size-44 sm:size-48"
                                    aria-label="Voucher QR code"
                                ></canvas>
                            </div>

                            <div class="w-full space-y-1">
                                <p
                                    class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase"
                                >
                                    Reference
                                </p>
                                <div
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <span
                                        class="font-mono text-base font-bold tracking-wider"
                                        >{voucher.public_reference}</span
                                    >
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground rounded p-1 transition-colors"
                                        title="Copy reference"
                                        onclick={() =>
                                            copyText(
                                                voucher.public_reference,
                                                'ref',
                                            )}
                                    >
                                        {#if copiedType === 'ref'}
                                            <Check
                                                class="size-3.5 text-emerald-600"
                                            />
                                        {:else}
                                            <Copy class="size-3.5" />
                                        {/if}
                                    </button>
                                </div>
                            </div>

                            <div class="w-full space-y-1">
                                <p
                                    class="text-muted-foreground text-[11px] font-medium tracking-wider uppercase"
                                >
                                    Fallback Code
                                </p>
                                <div
                                    class="flex items-center justify-center gap-1.5"
                                >
                                    <span
                                        class="text-primary font-mono text-xl font-bold tracking-[0.2em]"
                                        >{voucher.fallback}</span
                                    >
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground rounded p-1 transition-colors"
                                        title="Copy fallback code"
                                        onclick={() =>
                                            copyText(voucher.fallback, 'code')}
                                    >
                                        {#if copiedType === 'code'}
                                            <Check
                                                class="size-3.5 text-emerald-600"
                                            />
                                        {:else}
                                            <Copy class="size-3.5" />
                                        {/if}
                                    </button>
                                </div>
                            </div>

                            <div
                                class="border-border/50 text-muted-foreground w-full space-y-1 border-t pt-2 text-[11px]"
                            >
                                <p
                                    class="flex items-center justify-center gap-1 font-medium"
                                >
                                    {voucher.expires_at_display ??
                                        expiryCountdown}
                                </p>
                                {#if voucher.expires_at}
                                    <p
                                        class="text-muted-foreground/80 text-[10px]"
                                    >
                                        Time remaining: {expiryCountdown}
                                    </p>
                                {/if}
                                <p class="text-muted-foreground/80 text-[10px]">
                                    Hand slip directly or print for physical
                                    delivery.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Operator Controls & Delivery Channels (7 of 12 cols on desktop) -->
                    <div
                        class="flex flex-col justify-between space-y-4 md:col-span-7"
                    >
                        <!-- Recipient Profile Info -->
                        <div
                            class="border-border/60 bg-muted/30 space-y-1 rounded-lg border p-3.5"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                    >Target Recipient</span
                                >
                                <span
                                    class="text-muted-foreground text-xs font-medium capitalize"
                                    >{user?.office ?? 'Campus Office'}</span
                                >
                            </div>
                            <p class="text-foreground text-sm font-bold">
                                {user?.name ?? 'Staff member'}
                            </p>
                            <p class="text-muted-foreground font-mono text-xs">
                                {user?.email ?? 'No email on record'}
                            </p>
                        </div>

                        <!-- In-Person Print Card -->
                        <div
                            class="border-border/60 bg-card space-y-2 rounded-lg border p-3.5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4
                                        class="flex items-center gap-1.5 text-sm font-semibold"
                                    >
                                        <Printer class="text-primary size-4" />
                                        In-Person Hand-Off (Recommended)
                                    </h4>
                                    <p
                                        class="text-muted-foreground mt-0.5 text-xs"
                                    >
                                        Print a physical slip with QR code and
                                        fallback PIN to hand directly to the
                                        staff member on campus.
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={printSlip}
                                        disabled={busy}
                                        class="shrink-0 gap-1.5"
                                    >
                                        <Printer class="size-3.5" /> Print Official
                                        Slip
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={downloadPdf}
                                        disabled={busy}
                                        class="shrink-0 gap-1.5"
                                    >
                                        <Download class="size-3.5" /> Download PDF
                                    </Button>
                                </div>
                            </div>
                        </div>
                        {#if selectedPurpose === 'activation'}
                            <!-- Email Invitation Card -->
                            <div
                                class="border-border/60 bg-card space-y-2 rounded-lg border p-3.5"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="space-y-1">
                                        <h4
                                            class="flex items-center gap-1.5 text-sm font-semibold"
                                        >
                                            <Mail class="text-primary size-4" />
                                            Email Invitation
                                        </h4>
                                        <p
                                            class="text-muted-foreground text-xs"
                                        >
                                            {#if user?.email}
                                                Replacing the current credential
                                                will queue a branded invitation
                                                for <span
                                                    class="text-foreground font-medium"
                                                    >{user.email}</span
                                                >.
                                            {:else}
                                                No email address is on file. Use
                                                the private printed slip; the
                                                recipient can choose an address
                                                during setup.
                                            {/if}
                                        </p>
                                        {#if emailSent}
                                            <p
                                                role="status"
                                                aria-live="polite"
                                                class="flex items-center gap-1 pt-1 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                                            >
                                                <Check class="size-3.5" /> Activation
                                                invitation queued for delivery.
                                            </p>
                                        {/if}
                                        {#if voucher.email_queue_failed}
                                            <p
                                                role="status"
                                                class="text-xs text-amber-700"
                                            >
                                                {voucher.delivery_message}
                                            </p>
                                        {/if}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={sendActivationEmail}
                                        disabled={busy || !user?.email}
                                        class="shrink-0 gap-1.5"
                                    >
                                        <Mail class="size-3.5" />
                                        {busy
                                            ? 'Sending…'
                                            : confirmingReissue
                                              ? 'Confirm replacement & email'
                                              : 'Replace & email'}
                                    </Button>
                                </div>
                                {#if user?.email}
                                    <p
                                        class="text-muted-foreground/80 text-[11px]"
                                    >
                                        Sending replaces and invalidates the
                                        current QR, fallback code, and any
                                        in-progress setup attempt; confirm below
                                        to proceed.
                                    </p>
                                {/if}
                            </div>
                        {/if}
                        <!-- Invalidation & Reissue Danger Zone -->
                        <div
                            class="border-destructive/20 bg-destructive/5 space-y-2 rounded-lg border p-3"
                        >
                            <div class="space-y-2">
                                <label
                                    for="reissue-voucher-purpose"
                                    class="text-foreground text-xs font-semibold"
                                    >Purpose for replacement voucher</label
                                >
                                <select
                                    id="reissue-voucher-purpose"
                                    bind:value={selectedPurpose}
                                    onchange={normalizeMode}
                                    class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                                    disabled={busy}
                                >
                                    <option value="activation"
                                        >Account Activation</option
                                    >
                                    <option value="password_reset"
                                        >Password Reset</option
                                    >
                                    <option value="mfa_reset"
                                        >Two-Factor Device Reset</option
                                    >
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label
                                    for="reissue-voucher-mode"
                                    class="text-foreground text-xs font-semibold"
                                    >Validity mode</label
                                >
                                <select
                                    id="reissue-voucher-mode"
                                    bind:value={selectedMode}
                                    class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                                    disabled={busy}
                                >
                                    <option value="standard_24h"
                                        >Standard · 24 Hours</option
                                    >
                                    <option value="extended_7d"
                                        >Extended · 7 Days</option
                                    >
                                    {#if selectedPurpose === 'activation'}
                                        <option value="extended_30d"
                                            >30 Days</option
                                        >
                                        <option value="extended_90d"
                                            >90 Days</option
                                        >
                                        <option value="extended_180d"
                                            >180 Days · Recommended</option
                                        >
                                        <option value="custom"
                                            >Custom expiry date and time</option
                                        >
                                    {/if}
                                    {#if selectedPurpose === 'activation' && canIssueNonExpiring}
                                        <option value="never_expires_single_use"
                                            >No calendar expiry · Single-use
                                            onboarding slip</option
                                        >
                                    {/if}
                                </select>
                                {#if selectedMode === 'custom'}
                                    <label
                                        for="reissue-custom-expiry"
                                        class="block text-xs font-medium"
                                        >Expires at (institution time)</label
                                    >
                                    <input
                                        id="reissue-custom-expiry"
                                        type="datetime-local"
                                        bind:value={customExpiry}
                                        required
                                        class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                                    />
                                {/if}
                            </div>
                            {#if selectedPurpose === 'mfa_reset'}
                                <p
                                    class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"
                                    role="note"
                                >
                                    This voucher will reset the user's
                                    authenticator app and allow re-enrolling on
                                    a new device.
                                </p>
                            {/if}
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <div>
                                    <p
                                        class="text-destructive text-xs font-semibold"
                                    >
                                        Credential Replacement
                                    </p>
                                    <p
                                        class="text-muted-foreground text-[11px]"
                                    >
                                        If the slip was lost or compromised,
                                        issue a fresh token.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant={confirmingReissue
                                        ? 'destructive'
                                        : 'outline'}
                                    size="sm"
                                    onclick={reissue}
                                    disabled={busy}
                                    class="shrink-0 gap-1 text-xs"
                                >
                                    <RefreshCw class="size-3" />
                                    {confirmingReissue
                                        ? 'Confirm Invalidation'
                                        : 'Reissue Voucher'}
                                </Button>
                            </div>
                            {#if confirmingReissue}
                                <p
                                    role="alert"
                                    class="text-destructive text-xs font-medium"
                                >
                                    Warning: The current voucher QR and fallback
                                    code will be immediately revoked.
                                </p>
                            {/if}
                        </div>
                    </div>
                </div>
            {/if}

            <Dialog.Footer
                class="border-border/60 flex w-full flex-col items-center justify-between gap-3 border-t pt-3 sm:flex-row"
            >
                <div
                    class="text-muted-foreground flex items-center gap-2 text-center text-xs sm:text-left"
                >
                    <ShieldCheck
                        class="text-muted-foreground size-3.5 shrink-0"
                    />
                    <span
                        >Single-use credential · Automatically invalidated upon
                        activation</span
                    >
                </div>
                <Button
                    type="button"
                    variant="outline"
                    onclick={close}
                    class="w-full shrink-0 gap-1.5 sm:w-auto"
                >
                    <X class="size-4" /> Close
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Portal>
</Dialog.Root>
{#if printFrameUrl}
    <iframe
        bind:this={iframeEl}
        src={printFrameUrl}
        onload={handlePrintFrameLoad}
        title="Staff Voucher Document Template Print Frame"
        class="pointer-events-none sr-only absolute -left-[9999px] h-0 w-0 border-0"
        aria-hidden="true"
    ></iframe>
{/if}
