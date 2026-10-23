<script>
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Card from '@/Components/ui/card';
    import { PinInput } from '@/Components/ui/pin-input';
    import { router, useForm } from '@inertiajs/svelte';
    import {
        Check,
        Clipboard,
        Copy,
        Download,
        KeyRound,
        RefreshCw,
        ShieldCheck,
    } from 'lucide-svelte';

    let {
        staff = {},
        purpose = 'activation',
        phase = 'password_required',
        two_factor_confirmed = false,
        two_factor_required = false,
        can_enroll_factor = false,
        requires_existing_mfa = false,
        mfa_verified = false,
        ready_to_complete = false,
        password_set = false,
        google_linked = false,
    } = $props();
    let setupReady = $state(ready_to_complete);
    let enrollmentConfirmed = $state(false);

    let step = $state(
        phase === 'password_required'
            ? 1
            : can_enroll_factor || requires_existing_mfa
              ? 2
              : ready_to_complete && mfa_verified && two_factor_confirmed
                ? 3
                : ready_to_complete
                  ? 4
                  : 1,
    );
    let password = $state('');
    let confirmation = $state('');
    let setup = $state(null);
    let code = $state('');
    let totpFormElement = $state(null);
    let codes = $state([]);
    let copied = $state(false);
    let secretCopied = $state(false);
    let enrolling = $state(false);
    let error = $state('');

    const criteria = $derived([
        { label: 'At least 8 characters', met: password.length >= 8 },
        {
            label: 'Uppercase and lowercase letters',
            met: /[A-Z]/.test(password) && /[a-z]/.test(password),
        },
        { label: 'At least one number', met: /\d/.test(password) },
        { label: 'At least one symbol', met: /[^A-Za-z0-9]/.test(password) },
    ]);

    const passwordForm = useForm({ password: '', password_confirmation: '' });
    const isDraftPlaceholder = (email) =>
        typeof email === 'string' &&
        (email.toLowerCase().endsWith('@nexiam.test') ||
            email.toLowerCase().endsWith('.nexiam.test'));

    const initialEmail =
        staff.is_draft_email || isDraftPlaceholder(staff.email_address)
            ? ''
            : (staff.email_address ?? '');
    const emailForm = useForm({ email: initialEmail });
    let emailValidationError = $state('');

    function chooseEmail(event) {
        event.preventDefault();
        emailValidationError = '';
        const trimmed = (emailForm.email || '').trim().toLowerCase();
        if (isDraftPlaceholder(trimmed)) {
            emailValidationError =
                'The @nexiam.test domain is reserved for draft accounts. Please enter your real email address or continue with Google.';
            return;
        }
        emailForm.post('/staff/account-setup/email');
    }
    function resendEmailVerification() {
        router.post('/staff/account-setup/email/verification');
    }

    function savePassword(event) {
        event.preventDefault();
        passwordForm.password = password;
        passwordForm.password_confirmation = confirmation;
        passwordForm.post('/staff/account-setup/password');
    }

    async function postJson(url, body = {}) {
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
            body: JSON.stringify(body),
        });
        const data = await response.json();
        if (!response.ok)
            throw new Error(
                data.message ??
                    Object.values(data.errors ?? {})[0]?.[0] ??
                    'The request could not be completed.',
            );
        return data;
    }

    async function beginEnrollment() {
        if (enrolling) return;
        enrolling = true;
        error = '';
        try {
            setup = await postJson('/staff/account-setup/two-factor');
            step = 2;
        } catch (caught) {
            console.error('[NEXIAM Setup Error]', caught);
            error =
                caught.message || 'Failed to initialize authenticator QR code.';
        } finally {
            enrolling = false;
        }
    }

    $effect(() => {
        setupReady = ready_to_complete;
        if (phase === 'password_required') {
            step = 1;
        } else if (can_enroll_factor) {
            step = 2;
            if (!setup && !enrolling && !error) beginEnrollment();
        } else if (requires_existing_mfa) {
            step = 2;
        } else if (ready_to_complete && mfa_verified && two_factor_confirmed) {
            step = 3;
        } else if (ready_to_complete) {
            step = 4;
        }
    });

    async function confirmEnrollment(event) {
        event.preventDefault();
        error = '';
        try {
            const result = await postJson(
                '/staff/account-setup/two-factor/confirm',
                { code },
            );
            codes = result.recovery_codes;
            setupReady = true;
            enrollmentConfirmed = true;
            step = 3;
        } catch (caught) {
            error = caught.message;
        }
    }

    async function copySecret() {
        if (!setup?.secret) return;
        try {
            await navigator.clipboard.writeText(setup.secret);
            secretCopied = true;
            setTimeout(() => (secretCopied = false), 2000);
        } catch {
            error =
                'Clipboard access is unavailable. Use the authenticator key shown on this page.';
        }
    }

    async function copyCodes() {
        try {
            await navigator.clipboard.writeText(codes.join('\n'));
            copied = true;
        } catch {
            error =
                'Clipboard access is unavailable. Download your recovery codes instead.';
        }
    }
    function downloadLoginCard() {
        const blob = new Blob(
            [
                `NEXIAM staff login\nEmail: ${staff.email_address ?? 'Email not set'}\nHelp: Contact your authorized staff account issuer.\n`,
            ],
            { type: 'text/plain' },
        );
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'nexiam-staff-login.txt';
        anchor.click();
        URL.revokeObjectURL(url);
    }

    function downloadCodes() {
        const blob = new Blob(
            [
                `NEXIAM staff recovery codes\nSave these codes somewhere safe. Each code works once.\n\n${codes.join('\n')}\n`,
            ],
            { type: 'text/plain' },
        );
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = 'nexiam-staff-recovery-codes.txt';
        anchor.click();
        URL.revokeObjectURL(url);
    }

    function completeSetup() {
        if (!setupReady) return;
        router.post('/staff/account-setup/complete');
    }
</script>

<svelte:head>
    <title>Staff account setup</title>
</svelte:head>

<GuestLayout maxWidth="max-w-xl">
    <Card.Root
        variant="glass"
        class="border-border/60 border shadow-xl backdrop-blur-xl"
    >
        <Card.Header>
            <div class="flex items-center gap-3">
                <div
                    class="bg-primary/10 text-primary flex size-11 items-center justify-center rounded-full"
                >
                    <ShieldCheck class="size-6" />
                </div>
                <div>
                    <Card.Title class="text-xl font-bold sm:text-2xl">
                        {purpose === 'mfa_reset'
                            ? 'Reconfigure Multi-Factor Authentication'
                            : 'Secure Your Staff Account'}
                    </Card.Title>
                    <Card.Description>
                        {#if purpose === 'mfa_reset'}
                            Your previous authenticator was reset. Scan this
                            code on your new device.
                        {:else}
                            {staff.name} ·
                            <span class="font-mono text-xs">{staff.email}</span>
                        {/if}
                    </Card.Description>
                </div>
            </div>
            {#if phase === 'email_required'}
                <p
                    class="text-muted-foreground mt-5 text-center text-xs"
                    aria-label="Setup progress: Step 1 of 5"
                >
                    Step 1 of 5 · Choose your email address
                </p>
            {:else}
                <div
                    class="mt-5 grid grid-cols-4 gap-2"
                    role="group"
                    aria-label={`Setup progress: Step ${step + 1} of 5`}
                >
                    {#each ['Email', 'Password', 'Authenticator', 'Recovery', 'Finish'] as label, index (label)}
                        <div class="space-y-1">
                            <div
                                class="h-1.5 rounded-full transition-colors {step +
                                    1 >=
                                index + 1
                                    ? 'bg-primary'
                                    : 'bg-muted'}"
                            ></div>
                            <p
                                class="text-center text-[11px] {step + 1 >=
                                index + 1
                                    ? 'text-foreground font-medium'
                                    : 'text-muted-foreground'}"
                            >
                                {label}
                            </p>
                        </div>
                    {/each}
                </div>
            {/if}
        </Card.Header>

        <Card.Content>
            {#if error}
                <p
                    class="bg-destructive/10 text-destructive mb-4 rounded-md p-3 text-sm"
                    role="alert"
                >
                    {error}
                </p>
            {/if}
            {#if phase !== 'email_required' && purpose === 'activation'}
                <div class="mb-4 space-y-2 rounded-md border p-3 text-sm">
                    <p>
                        Email address: {staff.email_address ?? 'Not set'} · {staff.email_verified
                            ? 'verified'
                            : 'not verified; mailbox recovery unavailable'}
                    </p>
                    {#if !staff.email_verified && staff.email_address}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onclick={resendEmailVerification}
                            >Send verification email</Button
                        >
                    {/if}
                </div>
            {/if}

            {#if phase === 'email_required'}
                <form onsubmit={chooseEmail} class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">
                            Choose your login email
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            Enter an address you control. You can finish setup
                            without verifying it, but it cannot be used for
                            mailbox recovery until confirmed.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="setup-email">Email address</Label>
                        <Input
                            id="setup-email"
                            type="email"
                            autocomplete="email"
                            bind:value={emailForm.email}
                            required
                        />
                        {#if emailValidationError}
                            <p class="text-destructive text-sm" role="alert">
                                {emailValidationError}
                            </p>
                        {:else if emailForm.errors.email}
                            <p class="text-destructive text-sm" role="alert">
                                {emailForm.errors.email}
                            </p>
                        {/if}
                    </div>
                    <Button
                        class="w-full"
                        type="submit"
                        disabled={emailForm.processing}
                        >Continue to password</Button
                    >
                    <div class="relative my-3">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-border/60"></div>
                        </div>
                        <div class="relative flex justify-center text-xs uppercase">
                            <span class="bg-background px-2 text-muted-foreground">or</span>
                        </div>
                    </div>
                    <a
                        href="/staff/account-setup/google"
                        class="border-border/60 bg-background hover:bg-muted/60 flex h-10 w-full items-center justify-center gap-2.5 rounded-md border text-sm font-medium shadow-xs transition-colors"
                    >
                        <svg
                            class="size-4"
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
                </form>
            {:else if step === 1}
                <form onsubmit={savePassword} class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">
                            Choose a new password
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            Use a unique password you do not use for any other
                            account.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="new-password">New password</Label>
                        <Input
                            id="new-password"
                            type="password"
                            bind:value={password}
                            autocomplete="new-password"
                            required
                            minlength="8"
                            aria-invalid={!!passwordForm.errors.password}
                            aria-describedby={passwordForm.errors.password
                                ? 'new-password-error'
                                : undefined}
                        />
                        {#if passwordForm.errors.password}
                            <p
                                id="new-password-error"
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {passwordForm.errors.password}
                            </p>
                        {/if}
                    </div>
                    <ul
                        class="grid gap-1 sm:grid-cols-2"
                        aria-label="Password strength checks"
                    >
                        {#each criteria as criterion (criterion.label)}
                            <li
                                class="flex items-center gap-2 text-xs {criterion.met
                                    ? 'font-medium text-emerald-700 dark:text-emerald-400'
                                    : 'text-muted-foreground'}"
                            >
                                <Check class="size-3.5" />
                                {criterion.label}
                            </li>
                        {/each}
                    </ul>
                    <div class="space-y-2">
                        <Label for="confirm-password">Confirm password</Label>
                        <Input
                            id="confirm-password"
                            type="password"
                            bind:value={confirmation}
                            autocomplete="new-password"
                            required
                            minlength="8"
                            aria-invalid={Boolean(
                                confirmation && confirmation !== password,
                            )}
                            aria-describedby={confirmation &&
                            confirmation !== password
                                ? 'confirm-password-error'
                                : undefined}
                        />
                        {#if confirmation && confirmation !== password}
                            <p
                                id="confirm-password-error"
                                class="text-destructive text-xs"
                                role="alert"
                            >
                                Passwords do not match.
                            </p>
                        {/if}
                    </div>
                    <Button
                        class="w-full"
                        type="submit"
                        disabled={passwordForm.processing ||
                            password.length < 8 ||
                            confirmation !== password}
                    >
                        {passwordForm.processing ? 'Saving…' : 'Save password'}
                    </Button>
                </form>
            {:else if step === 2}
                <div class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">
                            Set up your authenticator
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            Scan this QR code with your authenticator app
                            (Google Authenticator, Microsoft Authenticator, or
                            1Password), then enter its 6-digit code.
                        </p>
                    </div>
                    {#if requires_existing_mfa}
                        <div
                            class="border-primary/30 bg-primary/5 space-y-3 rounded-lg border p-4"
                        >
                            <p class="text-sm">
                                Your current authenticator remains active.
                                Verify it to continue; it will not be replaced
                                here.
                            </p>
                            <Button
                                type="button"
                                class="w-full"
                                onclick={() =>
                                    router.visit('/two-factor-challenge')}
                            >
                                Verify current authenticator
                            </Button>
                        </div>
                    {:else if setup}
                        <div
                            class="border-border/60 flex flex-col items-center justify-center rounded-xl border bg-white p-3 shadow-xs"
                        >
                            <img
                                src={setup.qr_svg}
                                alt="Authenticator enrollment QR code"
                                class="size-56"
                            />
                        </div>
                        <div
                            class="border-border/60 bg-muted/30 space-y-2 rounded-lg border p-3"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-muted-foreground text-xs font-medium"
                                    >Can't scan the QR code?</span
                                >
                                <button
                                    type="button"
                                    class="text-primary inline-flex items-center gap-1 text-xs hover:underline"
                                    onclick={copySecret}
                                >
                                    {#if secretCopied}
                                        <Check
                                            class="size-3 text-emerald-600"
                                        /> Copied key
                                    {:else}
                                        <Copy class="size-3" /> Copy secret key
                                    {/if}
                                </button>
                            </div>
                            <p
                                class="text-foreground bg-background border-border/40 rounded border p-2 font-mono text-xs break-all select-all"
                            >
                                {setup.secret}
                            </p>
                        </div>
                        <form
                            bind:this={totpFormElement}
                            onsubmit={confirmEnrollment}
                            class="space-y-3 pt-2"
                        >
                            <Label for="totp-code" class="text-sm font-medium"
                                >Six-digit verification code</Label
                            >
                            <PinInput
                                inputId="totp-code"
                                bind:value={code}
                                onComplete={() =>
                                    totpFormElement?.requestSubmit()}
                                class="justify-center"
                            />
                            <Button
                                type="submit"
                                class="w-full"
                                disabled={code.length !== 6}
                            >
                                Verify authenticator
                            </Button>
                        </form>
                    {:else if enrolling}
                        <div
                            class="text-muted-foreground space-y-2 py-12 text-center text-sm"
                        >
                            <div
                                class="border-primary mx-auto size-6 animate-spin rounded-full border-2 border-r-transparent"
                            ></div>
                            <p>Preparing authenticator QR code…</p>
                        </div>
                    {:else}
                        <div
                            class="border-destructive/30 bg-destructive/5 space-y-3 rounded-lg border p-4 text-center"
                        >
                            <p class="text-destructive text-sm font-semibold">
                                {error ||
                                    'Authenticator setup could not be loaded.'}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                The setup session may have expired or is
                                awaiting verification.
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onclick={beginEnrollment}
                                class="gap-1.5"
                            >
                                <RefreshCw class="size-3.5" /> Retry setup
                            </Button>
                        </div>
                    {/if}
                </div>
            {:else if step === 3}
                <div class="space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">
                            Save your recovery codes
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            Each code works once as a fallback if you lose your
                            phone or authenticator app. Keep them in a safe
                            place.
                        </p>
                    </div>
                    {#if codes.length}
                        <div
                            class="bg-muted/40 grid grid-cols-1 gap-2 rounded-lg border p-4 font-mono text-sm sm:grid-cols-2"
                        >
                            {#each codes as recoveryCode (recoveryCode)}
                                <code
                                    class="bg-background/80 rounded p-1 text-center font-bold tracking-wider"
                                    >{recoveryCode}</code
                                >
                            {/each}
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onclick={copyCodes}
                                class="gap-1.5"
                            >
                                <Clipboard class="size-4" />
                                {copied ? 'Copied to clipboard' : 'Copy codes'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onclick={downloadCodes}
                                class="gap-1.5"
                            >
                                <Download class="size-4" />
                                Download as TXT
                            </Button>
                        </div>
                    {:else if two_factor_confirmed}
                        <div class="rounded-md border p-4">
                            <p class="text-sm">
                                Recovery codes were shown only once. If you did
                                not save them, contact an HR administrator to
                                reset your authenticator.
                            </p>
                        </div>
                    {:else}
                        <div class="rounded-md border p-4">
                            <p class="text-sm">
                                Authenticator enrollment is optional for your
                                account role. You can proceed without it.
                            </p>
                        </div>
                    {/if}
                    <Button class="w-full" onclick={() => (step = 4)}>
                        {codes.length
                            ? 'I have safely saved my recovery codes'
                            : 'Continue'}
                    </Button>
                </div>
            {:else}
                <div class="space-y-4 py-4 text-center">
                    <div
                        class="mx-auto flex size-14 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                    >
                        <Check class="size-7" />
                    </div>
                    <h2 class="text-xl font-bold">Review your setup</h2>
                    <div class="mx-auto max-w-sm space-y-1 text-left text-sm">
                        <p>Password: {password_set ? 'set' : 'not set'}</p>
                        <p>
                            Email ownership: {staff.email_verified
                                ? 'verified'
                                : 'not verified; mailbox recovery unavailable'}
                        </p>
                        <p>
                            Google sign-in: {google_linked
                                ? 'linked'
                                : 'not linked'}
                        </p>
                        <p>
                            Authenticator: {two_factor_confirmed || enrollmentConfirmed
                                ? 'enrolled'
                                : two_factor_required
                                  ? 'required but not enrolled'
                                  : 'not configured (optional)'}
                        </p>
                    </div>
                    <p class="text-muted-foreground mx-auto max-w-sm text-sm">
                        Browser password-manager saving is controlled by your
                        browser; this page cannot confirm that it saved. Do not
                        trust a shared device.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        onclick={downloadLoginCard}
                        >Download non-secret login card</Button
                    >
                    <Button class="w-full" onclick={completeSetup}>
                        <KeyRound class="mr-1.5 size-4" />
                        Enter Staff Portal
                    </Button>
                </div>
            {/if}
        </Card.Content>
    </Card.Root>
</GuestLayout>
