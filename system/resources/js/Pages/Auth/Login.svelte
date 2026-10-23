<script>
    import { onMount } from 'svelte';
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import { useForm, usePage } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Checkbox } from '@/Components/ui/checkbox';
    import * as Card from '@/Components/ui/card';
    import * as Dialog from '@/Components/ui/dialog';
    import QrScanner from '@/Components/qr/QrScanner.svelte';
    import {
        ArrowRight,
        Clock,
        Eye,
        EyeOff,
        KeyRound,
        Lock,
        QrCode,
        ShieldCheck,
    } from 'lucide-svelte';
    import { classifyLoginQr } from '@/lib/login-qr-classifier.js';
    const page = usePage();
    const demo = page.props.demo ?? null;
    const form = useForm({
        email: demo?.email ?? '',
        password: demo?.password ?? '',
        remember: false,
    });
    const voucherForm = useForm({ reference: '', fallback: '' });
    const errors = $derived(form.errors ?? page.props.errors ?? {});
    let showPassword = $state(false);
    let passwordInput = $state(null);
    let isRememberedEmail = $state(false);
    let sessionExpired = $state(false);
    let scanOpen = $state(false);
    let manualVoucherOpen = $state(false);
    let scanError = $state('');
    let lastPortalHint = $state('');

    onMount(() => {
        try {
            lastPortalHint =
                localStorage.getItem('nexiam.last_portal') === 'applicant'
                    ? 'applicant'
                    : '';
        } catch {
            // Storage may be unavailable in private browsing; the login flow does not depend on this hint.
        }
        const params = new URLSearchParams(window.location.search);
        if (params.get('expired') === '1') sessionExpired = true;
        const accountParam = params.get('account') || params.get('email');
        if (accountParam) {
            form.email = accountParam.trim();
            isRememberedEmail = true;
            window.history.replaceState(null, '', window.location.pathname);
            passwordInput?.focus();
        } else if (!form.email) {
            try {
                const cached = localStorage.getItem(
                    'nexiam.staff_account_email',
                );
                if (cached) {
                    form.email = cached.trim();
                    isRememberedEmail = true;
                    passwordInput?.focus();
                }
            } catch {
                // Storage may be unavailable in private browsing; the login flow remains usable without a saved email.
            }
        }
    });

    function forgetEmail() {
        form.email = '';
        isRememberedEmail = false;
        try {
            localStorage.removeItem('nexiam.staff_account_email');
        } catch {
            // Storage may be unavailable in private browsing; the current form is already cleared.
        }
    }

    function submit(event) {
        event.preventDefault();
        if (typeof window !== 'undefined' && form.email) {
            try {
                localStorage.setItem(
                    'nexiam.staff_account_email',
                    form.email.trim(),
                );
            } catch {
                // Storage may be unavailable in private browsing; login still submits without saving the email.
            }
        }
        form.post('/login');
    }

    function handleScannedQr(decoded) {
        scanError = '';
        try {
            const target = classifyLoginQr(decoded, window.location.href);
            scanOpen = false;
            window.location.assign(target.href);
        } catch (error) {
            scanError =
                error instanceof Error
                    ? error.message
                    : 'This QR could not be opened.';
            scanOpen = false;
        }
    }

    function redeemManualVoucher(event) {
        event.preventDefault();
        voucherForm.reference = voucherForm.reference.trim().toUpperCase();
        voucherForm.fallback = voucherForm.fallback.trim().toUpperCase();
        voucherForm.post('/staff/activate');
    }
</script>

<svelte:head>
    <title>Staff login</title>
</svelte:head>

<GuestLayout>
    <Card.Root
        variant="glass"
        class="border-border/60 border shadow-xl backdrop-blur-xl"
    >
        <Card.Header>
            <div
                class="text-muted-foreground mb-2 flex items-center gap-2 text-xs font-semibold tracking-[0.16em] uppercase"
            >
                <ShieldCheck class="text-primary size-4" aria-hidden="true" />
                <span>Institutional Staff Access</span>
            </div>
            <Card.Title class="text-2xl font-bold tracking-tight"
                >Staff Portal</Card.Title
            >
            <Card.Description>
                Authorized examination administration, grading, and admissions
                governance
            </Card.Description>
        </Card.Header>
        <Card.Content>
            {#if sessionExpired}
                <div
                    class="text-foreground mb-4 flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-sm"
                    role="alert"
                    aria-live="assertive"
                >
                    <Clock
                        class="mt-0.5 size-4 shrink-0 text-amber-500"
                        aria-hidden="true"
                    />
                    <div class="flex-1">
                        <p class="font-semibold text-amber-500">
                            Session expired
                        </p>
                        <p class="text-muted-foreground mt-0.5 text-xs">
                            Your workstation session expired due to inactivity.
                        </p>
                    </div>
                </div>
            {/if}
            <a
                href="/auth/google/redirect"
                class="border-border/60 bg-background hover:bg-muted/60 flex h-11 w-full items-center justify-center gap-3 rounded-lg border text-sm font-semibold shadow-xs transition-colors"
            >
                <svg
                    class="size-5"
                    viewBox="0 0 48 48"
                    role="img"
                    aria-label="Google"
                >
                    <path
                        fill="#EA4335"
                        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5Z"
                    />
                    <path
                        fill="#4285F4"
                        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.76 7.18l7.73 6C44.42 37.98 46.98 31.85 46.98 24.55Z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.76-4.59l-7.98-6.19A23.9 23.9 0 0 0 0 24c0 3.88.93 7.56 2.56 10.78l7.97-6.19Z"
                    />
                    <path
                        fill="#34A853"
                        d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.73-6c-2.15 1.45-4.9 2.3-8.18 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48Z"
                    />
                </svg>
                Continue with Google
            </a>

            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center">
                    <span class="border-border/60 w-full border-t"></span>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-card text-muted-foreground px-2 font-medium"
                        >Or continue with password</span
                    >
                </div>
            </div>

            <form onsubmit={submit} class="space-y-4">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label for="email">Email</Label>
                        {#if isRememberedEmail}
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground text-xs underline"
                                onclick={forgetEmail}
                            >
                                Forget account
                            </button>
                        {/if}
                    </div>
                    <Input
                        id="email"
                        type="email"
                        bind:value={form.email}
                        autocomplete="username"
                        required
                        aria-invalid={!!errors?.email}
                        aria-describedby={errors?.email
                            ? 'email-error'
                            : undefined}
                    />
                    {#if errors?.email}
                        <p id="email-error" class="text-destructive text-sm">
                            {errors.email}
                        </p>
                    {/if}
                </div>

                <div class="space-y-2">
                    <Label for="password">Password</Label>
                    <div class="relative">
                        <Input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            bind:ref={passwordInput}
                            bind:value={form.password}
                            autocomplete="current-password"
                            required
                            aria-invalid={!!errors?.password}
                            aria-describedby={errors?.password
                                ? 'password-error'
                                : undefined}
                            class="pr-10"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="text-muted-foreground hover:text-foreground absolute top-1/2 right-1 size-8 -translate-y-1/2"
                            aria-label={showPassword
                                ? 'Hide password'
                                : 'Show password'}
                            aria-pressed={showPassword}
                            onclick={() => (showPassword = !showPassword)}
                        >
                            {#if showPassword}
                                <EyeOff class="size-4" aria-hidden="true" />
                            {:else}
                                <Eye class="size-4" aria-hidden="true" />
                            {/if}
                        </Button>
                    </div>
                    {#if errors?.password}
                        <p id="password-error" class="text-destructive text-sm">
                            {errors.password}
                        </p>
                    {/if}
                </div>

                <label
                    class="text-muted-foreground flex items-center gap-2 text-sm"
                >
                    <Checkbox bind:checked={form.remember} />
                    Remember me
                </label>

                <Button
                    type="submit"
                    class="w-full"
                    disabled={form.processing}
                    aria-busy={form.processing}
                >
                    {#if form.processing}
                        <span
                            class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"
                            aria-hidden="true"
                        ></span>
                        Signing in…
                    {:else}
                        <Lock class="size-4" aria-hidden="true" />
                        Sign in
                    {/if}
                </Button>
            </form>
            <div class="mt-3 space-y-2">
                <Button
                    type="button"
                    variant="ghost"
                    class="min-h-10 w-full text-sm"
                    onclick={() => {
                        scanError = '';
                        scanOpen = true;
                    }}
                >
                    <QrCode class="size-4" aria-hidden="true" />
                    Scan QR code
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="min-h-10 w-full text-sm"
                    onclick={() => {
                        scanError = '';
                        manualVoucherOpen = !manualVoucherOpen;
                    }}
                >
                    <KeyRound class="size-4" aria-hidden="true" />
                    Enter voucher manually
                </Button>
                {#if scanError}
                    <p
                        class="text-destructive text-center text-xs"
                        role="alert"
                    >
                        {scanError}
                    </p>
                {/if}
            </div>
            {#if manualVoucherOpen}
                <form
                    onsubmit={redeemManualVoucher}
                    class="border-border/60 bg-muted/20 space-y-3 rounded-lg border p-3"
                    aria-label="Enter activation voucher"
                >
                    <div class="space-y-1">
                        <Label for="login-voucher-reference"
                            >Voucher reference</Label
                        >
                        <Input
                            id="login-voucher-reference"
                            bind:value={voucherForm.reference}
                            required
                            maxlength="10"
                            autocomplete="off"
                            class="font-mono uppercase"
                        />
                        {#if voucherForm.errors.reference}<p
                                class="text-destructive text-xs"
                                role="alert"
                            >
                                {voucherForm.errors.reference}
                            </p>{/if}
                    </div>
                    <div class="space-y-1">
                        <Label for="login-voucher-fallback">Fallback code</Label
                        >
                        <Input
                            id="login-voucher-fallback"
                            bind:value={voucherForm.fallback}
                            required
                            minlength="8"
                            maxlength="9"
                            autocomplete="one-time-code"
                            class="font-mono uppercase"
                        />
                        {#if voucherForm.errors.fallback}<p
                                class="text-destructive text-xs"
                                role="alert"
                            >
                                {voucherForm.errors.fallback}
                            </p>{/if}
                    </div>
                    <Button
                        type="submit"
                        class="w-full"
                        disabled={voucherForm.processing}
                        >{voucherForm.processing
                            ? 'Verifying…'
                            : 'Continue with voucher'}</Button
                    >
                </form>
            {/if}
        </Card.Content>
        <Card.Footer class="block">
            <div
                class="border-border/40 mt-6 space-y-2 border-t pt-4 text-center"
            >
                {#if lastPortalHint === 'applicant'}
                    <p class="text-muted-foreground text-[11px]">
                        Last successful portal: Applicant
                    </p>
                {/if}
                <p class="text-muted-foreground text-xs">
                    Applicant checking examination status?
                    <a
                        href="/applicant/login"
                        class="text-primary inline-flex items-center gap-1 font-medium hover:underline"
                    >
                        Go to Applicant Portal <ArrowRight
                            class="size-3"
                            aria-hidden="true"
                        />
                    </a>
                </p>
                <p class="text-muted-foreground text-xs">
                    Have an activation voucher?
                    <a
                        href="/staff/activate"
                        class="text-primary hover:underline">Activate account</a
                    >
                </p>
            </div>
        </Card.Footer>
    </Card.Root>
    <Dialog.Root bind:open={scanOpen}>
        <Dialog.Content class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
            <Dialog.Header>
                <Dialog.Title>Scan QR code</Dialog.Title>
                <Dialog.Description>
                    Scan a NEXIAM applicant pass or staff setup voucher.
                </Dialog.Description>
            </Dialog.Header>
            <QrScanner active={scanOpen} onScan={handleScannedQr} />
            <Dialog.Footer>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    onclick={() => (scanOpen = false)}>Cancel scanning</Button
                >
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
</GuestLayout>
