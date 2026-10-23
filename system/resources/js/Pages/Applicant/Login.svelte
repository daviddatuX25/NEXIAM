<script>
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import { Link, router, useForm, usePage } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import QrScanner from '@/Components/qr/QrScanner.svelte';
    import ApplicantBirthdateInput from '@/Components/ApplicantBirthdateInput.svelte';
    import { Label } from '@/Components/ui/label';
    import * as Card from '@/Components/ui/card';
    import { AlertCircle, KeyRound, QrCode } from 'lucide-svelte';
    import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
    import { parseMmDdYyyyBirthdate } from '@/lib/applicant-birthdate';
    const STORAGE_KEY = 'nexiam.applicant_access_key';

    let { page_state = 'ready', demo = null, errors = {} } = $props();

    const page = usePage();
    let keyVisible = $state(false);
    let voucherToken = $state('');
    let redeemingVoucher = $state(false);
    let scanOpen = $state(false);
    let scanError = $state('');
    let lastPortalHint = $state('');
    let tourOpen = $state(false);
    const voucherError = $derived(
        errors?.voucher ?? page.props.errors?.voucher ?? '',
    );

    const form = useForm({
        access_key: '',
        birthdate: '',
    });
    let birthdateInput = $state('');
    let birthdateFormatError = $state('');

    onMount(() => {
        try {
            const hint = localStorage.getItem('nexiam.last_portal');
            lastPortalHint = hint === 'staff' ? 'staff' : '';
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                form.access_key = saved;
            }
        } catch {
            // ignore
        }

        if (typeof window !== 'undefined' && window.location.hash) {
            const rawHash = window.location.hash.replace(/^#/, '');
            window.history.replaceState(
                null,
                '',
                window.location.pathname + window.location.search,
            );
            const params = new URLSearchParams(rawHash);
            const hashKey = params.get('k')?.trim() ?? '';
            if (hashKey) {
                form.access_key = hashKey;
                try {
                    localStorage.setItem(STORAGE_KEY, hashKey);
                } catch {
                    // ignore
                }
            }
            const hashVoucher = params.get('v')?.trim() ?? '';
            if (hashVoucher) {
                voucherToken = hashVoucher;
            }
        }
    });

    function redeemVoucher() {
        if (!voucherToken || redeemingVoucher) return;
        redeemingVoucher = true;
        router.post(
            '/applicant/login/voucher',
            { voucher_token: voucherToken },
            {
                onSuccess: () => {
                    try {
                        localStorage.setItem('nexiam.last_portal', 'applicant');
                    } catch {
                        // The hint never participates in authentication.
                    }
                },
                onFinish: () => {
                    redeemingVoucher = false;
                    voucherToken = '';
                },
            },
        );
    }

    function clearBirthdateError() {
        birthdateFormatError = '';
        form.clearErrors('birthdate');
    }

    function submit(event) {
        event.preventDefault();
        const birthdate = parseMmDdYyyyBirthdate(birthdateInput);

        if (!birthdate) {
            birthdateFormatError = 'Enter a valid birthdate as MM-DD-YYYY.';
            return;
        }

        birthdateFormatError = '';
        form.transform((data) => ({ ...data, birthdate })).post(
            '/applicant/login',
            {
                onSuccess: () => {
                    try {
                        localStorage.setItem('nexiam.last_portal', 'applicant');
                    } catch {
                        // The hint never participates in authentication.
                    }
                },
            },
        );
    }

    function handleLoginQrScan(decoded) {
        scanError = '';
        try {
            const target = new URL(String(decoded).trim());
            const localLoopback = window.location.protocol === 'http:'
                && ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
            if ((target.protocol !== 'https:' && !(localLoopback && target.protocol === 'http:')
                || target.origin !== window.location.origin
                || target.pathname !== '/applicant/login'
                || target.search)) {
                throw new Error('This is not an applicant login voucher.');
            }

            const fragment = new URLSearchParams(target.hash.slice(1));
            const keys = [...fragment.keys()];
            const voucher = fragment.get('v')?.trim() ?? '';
            const accessKey = fragment.get('k')?.trim() ?? '';
            if (keys.length !== 2 || !keys.includes('v') || !keys.includes('k') || !voucher || !accessKey) {
                throw new Error('This QR does not contain an applicant login voucher.');
            }

            voucherToken = voucher;
            form.access_key = accessKey;
            try {
                localStorage.setItem(STORAGE_KEY, accessKey);
            } catch {
                // The temporary voucher remains usable without saved storage.
            }
            scanOpen = false;
            redeemVoucher();
        } catch (error) {
            scanError = error instanceof Error ? error.message : 'This QR could not be used.';
            scanOpen = false;
        }
    }
</script>

<svelte:head>
    <title>Applicant sign in</title>
</svelte:head>

<GuestLayout showInquiryLauncher={false}>
    <div class="mx-auto max-w-md px-6 py-12">
        <Card.Root variant="glass" class="border border-border/60 shadow-xl backdrop-blur-xl">
            <Card.Header>
                <div
                    class="text-muted-foreground mb-1.5 flex items-center gap-1.5 text-xs font-semibold tracking-[0.16em] uppercase"
                >
                    <KeyRound
                        class="text-primary size-3.5"
                        aria-hidden="true"
                    />
                    <span>Admissions Gateway</span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <Card.Title class="text-2xl font-bold tracking-tight">Applicant Sign In</Card.Title>
                    <TourTriggerGlyph
                        tourKey="page:Applicant/Login"
                        label="Need help signing in? Ask Kuya Xiam!"
                        onclick={() => (tourOpen = true)}
                    />
                </div>
                <Card.Description>
                    Enter your 8-character access key (ABCD-EFGH) and birthdate
                    to view your application, examination schedule, and results.
                </Card.Description>
            </Card.Header>
            <Card.Content>
                {#if voucherError}
                    <div
                        role="alert"
                        class="border-destructive/40 bg-destructive/10 text-destructive mb-4 flex items-start gap-2 rounded-lg border p-3 text-sm"
                    >
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <span>{voucherError}</span>
                    </div>
                {/if}

                {#if voucherToken}
                    <div
                        class="mb-4 space-y-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4"
                    >
                        <div class="flex items-start gap-2.5">
                            <QrCode
                                class="text-primary mt-0.5 size-5 shrink-0"
                            />
                            <div class="space-y-1">
                                <p class="text-foreground text-sm font-semibold">
                                    Temporary QR Login Voucher detected
                                </p>
                                <p class="text-muted-foreground text-xs">
                                    Use your one-time 24-hour login voucher to
                                    open your applicant portal now without
                                    typing. Your durable access key has also
                                    been saved to this browser.
                                </p>
                            </div>
                        </div>
                        <Button
                            type="button"
                            class="min-h-11 w-full gap-1.5"
                            disabled={redeemingVoucher}
                            onclick={redeemVoucher}
                        >
                            <QrCode class="size-4" />
                            {redeemingVoucher
                                ? 'Signing in with QR voucher…'
                                : 'Sign in with QR voucher'}
                        </Button>
                    </div>
                {/if}
                <form onsubmit={submit} class="space-y-4" novalidate>
                    <div class="space-y-2">
                        <Label for="access_key">Access key</Label>
                        <div class="flex gap-2">
                            <Input
                                id="access_key"
                                type={keyVisible ? 'text' : 'password'}
                                bind:value={form.access_key}
                                required
                                autocomplete="off"
                                class="min-h-11 font-mono tracking-wider"
                                placeholder="ABCD-EFGH"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-11 shrink-0"
                                onclick={() => (keyVisible = !keyVisible)}
                            >
                                {keyVisible ? 'Hide' : 'Show'}
                            </Button>
                        </div>
                        {#if form.errors.access_key}
                            <p class="text-destructive text-sm">
                                {form.errors.access_key}
                            </p>
                        {/if}
                    </div>
                    <div class="space-y-2">
                        <ApplicantBirthdateInput
                            id="birthdate"
                            bind:value={birthdateInput}
                            error={birthdateFormatError ||
                                form.errors.birthdate}
                            oninput={clearBirthdateError}
                        />
                    </div>
                    <Button
                        type="submit"
                        class="min-h-11 w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </form>
                <div class="mt-3 space-y-2">
                    <Button type="button" variant="ghost" class="min-h-10 w-full text-sm" onclick={() => { scanError = ''; scanOpen = true; }}>
                        <QrCode class="size-4" />
                        Scan login QR
                    </Button>
                    {#if scanError}
                        <p class="text-center text-xs text-destructive" role="alert">{scanError}</p>
                    {/if}
                </div>
            </Card.Content>
        </Card.Root>

        <p class="text-muted-foreground mt-6 text-center text-sm">
            Lost your key? Contact the Registrar’s office to request a reissue.
            <br />
            <Link href="/apply" class="underline underline-offset-4">Apply</Link
            >
            ·
            <Link href="/login" class="underline underline-offset-4"
                >Staff sign in</Link
            >
        </p>
        {#if lastPortalHint === 'staff'}
            <p class="text-center text-[11px] text-muted-foreground">Last successful portal: Staff</p>
        {/if}
    </div>
    {#if scanOpen}
        <div class="mx-auto max-w-md space-y-2 px-6 pb-6">
            <Button type="button" variant="outline" class="min-h-11 w-full" onclick={() => (scanOpen = false)}>Cancel scanning</Button>
            <QrScanner active={scanOpen} onScan={handleLoginQrScan} />
        </div>
    {/if}

    <XiamNpcDialog
        tourKey="page:Applicant/Login"
        context={{ safetyKey: 'applicant-login', pageState: page_state }}
        bind:open={tourOpen}
    />
</GuestLayout>
